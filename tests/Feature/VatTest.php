<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\DownPayment;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Models\Supplier;
use App\Models\Tax;
use App\Models\User;
use App\Services\AccountResolver;
use App\Services\DocumentTax;
use App\Services\DownPaymentService;
use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** PPN per barang, retur, uang muka, nomor Faktur Pajak, dan Laporan PPN yang harus cocok dengan Buku Besar. */
class VatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $cid;
    private Tax $tax;
    private Customer $customer;
    private Supplier $supplier;
    private Product $taxed;
    private Product $exempt;
    private Account $cash;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->today = date('Y-m-d');
        $this->user = $this->registerCompany('PT Pajak', 'pajak@uji.test');
        $this->actingAs($this->user);
        $this->cid = (int) $this->user->company_id;
        $this->tax = Tax::create(['company_id' => $this->cid, 'name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]);
        $this->customer = Customer::create(['company_id' => $this->cid, 'name' => 'Toko Maju', 'npwp' => '01.234.567.8-901.000', 'default_term_days' => 0, 'is_active' => true]);
        $this->supplier = Supplier::create(['company_id' => $this->cid, 'name' => 'PT Sumber', 'npwp' => '09.876.543.2-109.000', 'is_active' => true]);
        $expense = Account::where('company_id', $this->cid)->where('type', 'expense')->get()->first(fn ($a) => ! $a->is_header);
        $this->taxed = Product::create(['company_id' => $this->cid, 'sku' => 'JS-1', 'name' => 'Jasa Kena', 'type' => 'service', 'unit' => 'kali', 'expense_account_id' => $expense->id]);
        $this->exempt = Product::create(['company_id' => $this->cid, 'sku' => 'JS-2', 'name' => 'Jasa Bebas', 'type' => 'service', 'unit' => 'kali', 'is_taxable' => false, 'expense_account_id' => $expense->id]);
        $this->cash = Account::where('company_id', $this->cid)->where('name', 'Kas')->firstOrFail();
    }

    private function registerCompany(string $name, string $email): User
    {
        auth()->logout(); // halaman daftar hanya untuk yang belum login
        $this->post('/register', ['company_name' => $name, 'name' => 'Owner', 'email' => $email, 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ', 'business_type' => 'manufaktur']);
        $user = User::where('email', $email)->firstOrFail();
        $user->company->forceFill(['is_premium' => true])->save();
        auth()->logout();

        return $user;
    }

    private function saleLines(array $extra = []): array
    {
        return array_merge(['customer_id' => $this->customer->id, 'invoice_date' => $this->today, 'due_date' => $this->today, 'tax_id' => $this->tax->id,
            'products' => [$this->taxed->id, $this->exempt->id], 'quantities' => [1, 1], 'prices' => [1000000, 500000], 'discounts' => [0, 0]], $extra);
    }

    private function purchaseLines(array $extra = []): array
    {
        return array_merge(['supplier_id' => $this->supplier->id, 'invoice_date' => $this->today, 'due_date' => $this->today, 'tax_id' => $this->tax->id,
            'products' => [$this->taxed->id, $this->exempt->id], 'quantities' => [1, 1], 'prices' => [200000, 100000], 'discounts' => [0, 0]], $extra);
    }

    private function sell(array $extra = []): SalesInvoice
    {
        $this->post('/client/sales-invoices', $this->saleLines($extra))->assertSessionMissing('error')->assertSessionHasNoErrors();

        return SalesInvoice::latest('id')->firstOrFail();
    }

    private function buy(array $extra = []): PurchaseInvoice
    {
        $this->post('/client/purchase-invoices', $this->purchaseLines($extra))->assertSessionMissing('error')->assertSessionHasNoErrors();

        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    /** Debit dikurangi kredit pada akun, seluruh jurnal terposting. */
    private function ledger(Account $account): float
    {
        return round((float) JournalDetail::where('account_id', $account->id)->sum('debit') - (float) JournalDetail::where('account_id', $account->id)->sum('credit'), 2);
    }

    private function outputAccount(): Account
    {
        return AccountResolver::outputTax($this->cid);
    }

    private function inputAccount(): Account
    {
        return AccountResolver::inputTax($this->cid);
    }

    private function report(array $query = []): array
    {
        return (new ReportBuilder($this->cid))->vat(new Request($query));
    }

    private function summary(array $doc): \Illuminate\Support\Collection
    {
        return collect($doc['summary'])->pluck('value', 'label');
    }

    private function rowsOfType(array $doc, string $type): array
    {
        return array_values(array_filter($doc['rows'], fn ($r) => str_starts_with((string) ($r['cells'][5] ?? ''), $type)));
    }

    /* ------------------------------------------------------- PPN per baris */

    public function test_only_taxable_lines_enter_the_tax_base(): void
    {
        $inv = $this->sell(['discount_percent' => 10]);

        // Subtotal 1.500.000, diskon 10% = 1.350.000. DPP hanya baris kena PPN: 1.000.000 - 10% = 900.000
        $this->assertEquals(900000, (float) $inv->tax_base);
        $this->assertEquals(99000, (float) $inv->tax_amount);
        $this->assertEquals(1449000, (float) $inv->total);
        $this->assertEquals([true, false], $inv->items()->orderBy('id')->get()->map(fn ($i) => (bool) $i->is_taxable)->all());
        $this->assertEquals(-99000, $this->ledger($this->outputAccount()));
    }

    public function test_choice_on_the_form_overrides_the_item_setting(): void
    {
        $inv = $this->sell(['taxable' => ['0', '1']]);

        $this->assertEquals(500000, (float) $inv->tax_base);
        $this->assertEquals(55000, (float) $inv->tax_amount);
        $this->assertEquals([false, true], $inv->items()->orderBy('id')->get()->map(fn ($i) => (bool) $i->is_taxable)->all());
    }

    public function test_account_lines_are_taxable_unless_unticked(): void
    {
        $expense = \App\Http\Controllers\Client\PurchaseInvoiceController::expenseAccounts($this->cid)->first();
        $inv = $this->buy(['products' => ['a' . $expense->id, 'a' . $expense->id], 'prices' => [100000, 300000], 'taxable' => ['1', '0']]);

        $this->assertEquals(100000, (float) $inv->tax_base);
        $this->assertEquals(11000, (float) $inv->tax_amount);
        $this->assertEquals(11000, $this->ledger($this->inputAccount()));
    }

    public function test_item_setting_can_be_changed_on_the_item_form(): void
    {
        $revenue = Account::where('company_id', $this->cid)->where('type', 'revenue')->get()->first(fn ($a) => ! $a->is_header);
        $base = ['type' => 'service', 'name' => 'Jasa Baru', 'sell_price' => 0, 'revenue_account_id' => $revenue->id];

        $this->post('/client/inventory', $base + ['sku' => 'JS-3'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) Product::where('sku', 'JS-3')->firstOrFail()->fresh()->is_taxable, 'barang baru kena PPN bila tidak dipilih lain');

        $this->post('/client/inventory', $base + ['sku' => 'JS-4', 'is_taxable' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) Product::where('sku', 'JS-4')->firstOrFail()->is_taxable);

        $this->get('/client/inventory/services/create')->assertOk()->assertSee('Kena PPN');
    }

    public function test_rounding_setting_is_applied(): void
    {
        $line = ['products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [11223], 'discounts' => [0]];

        $this->assertEquals(1234.53, (float) $this->sell($line)->tax_amount);

        $this->user->company->forceFill(['tax_rounding' => DocumentTax::ROUND_DOWN])->save();
        $this->user->refresh();
        $this->actingAs($this->user);
        $this->assertEquals(1234, (float) $this->sell($line)->tax_amount);

        $this->user->company->forceFill(['tax_rounding' => DocumentTax::ROUND_UP])->save();
        $this->user->refresh();
        $this->actingAs($this->user);
        $inv = $this->sell($line);
        $this->assertEquals(1235, (float) $inv->tax_amount);
        $this->assertEquals(12458, (float) $inv->total);
    }

    public function test_rounding_is_not_confused_by_floating_point_noise(): void
    {
        // 1.000.000 x 11% tepat 110.000: tidak boleh naik menjadi 110.001 atau turun menjadi 109.999
        $this->assertEquals(110000, DocumentTax::amount(1000000, 11, DocumentTax::ROUND_UP));
        $this->assertEquals(110000, DocumentTax::amount(1000000, 11, DocumentTax::ROUND_DOWN));
        $this->assertEquals(33, DocumentTax::amount(300, 11, DocumentTax::ROUND_UP));
    }

    public function test_unknown_rounding_value_is_rejected(): void
    {
        $this->post('/client/settings/preferences', ['name' => 'PT Pajak', 'tax_rounding' => 'Sesuka Hati'])->assertSessionHasErrors('tax_rounding');
    }

    public function test_other_company_tax_is_ignored(): void
    {
        $other = $this->registerCompany('PT Lain', 'lain@uji.test');
        $foreign = Tax::create(['company_id' => $other->company_id, 'name' => 'PPN Lain', 'rate' => 50, 'is_active' => true]);
        $this->actingAs($this->user);

        $inv = $this->sell(['tax_id' => $foreign->id]);

        $this->assertEquals(0, (float) $inv->tax_amount);
        $this->assertNull($inv->tax_id);
    }

    /* ---------------------------------------------- pesanan dan tarikannya */

    public function test_editing_an_order_keeps_its_discount_and_tax(): void
    {
        $order = fn () => ['order_date' => $this->today, 'discount_percent' => 10, 'tax_id' => $this->tax->id,
            'products' => [$this->taxed->id, $this->exempt->id], 'quantities' => [1, 1], 'prices' => [1000000, 500000], 'discounts' => [0, 0]];

        $this->post('/client/sales/orders', $order() + ['customer_id' => $this->customer->id, 'order_number' => 'SO-1'])->assertSessionMissing('error');
        $so = SalesOrder::firstOrFail();
        $this->assertEquals(99000, (float) $so->tax_amount, 'PPN pesanan hanya dari baris kena PPN');
        $edit = $this->get('/client/sales/orders/' . $so->id . '/edit')->assertOk();
        $edit->assertSee('name="discount_percent" id="discount_percent" value="10"', false);
        $edit->assertSee('value="' . $this->tax->id . '" data-rate="11.00" selected', false);
        $edit->assertSee('"taxable":false', false);

        $this->post('/client/purchases/orders', $order() + ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-1'])->assertSessionMissing('error');
        $po = PurchaseOrder::firstOrFail();
        $this->assertEquals(99000, (float) $po->tax_amount);
        $this->assertEquals($this->tax->id, $po->tax_id);
        $edit = $this->get('/client/purchases/orders/' . $po->id . '/edit')->assertOk();
        $edit->assertSee('name="discount_percent" id="discount_percent" value="10"', false);
        $edit->assertSee('value="' . $this->tax->id . '" data-rate="11.00" selected', false);
        $edit->assertSee('"taxable":false', false);

        // Menyimpan ulang tanpa perubahan tidak mengubah nilai pesanan
        $this->put('/client/purchases/orders/' . $po->id, $order() + ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-1', 'taxable' => ['1', '0']])->assertSessionMissing('error');
        $this->assertEquals(99000, (float) $po->fresh()->tax_amount);
        $this->put('/client/sales/orders/' . $so->id, $order() + ['customer_id' => $this->customer->id, 'order_number' => 'SO-1', 'taxable' => ['1', '0']])->assertSessionMissing('error');
        $this->assertEquals(99000, (float) $so->fresh()->tax_amount);
        $this->assertEquals(0, (float) $so->fresh()->details()->where('product_id', $this->exempt->id)->first()->tax_amount);
    }

    public function test_invoice_pulled_from_an_order_follows_its_tax_and_line_status(): void
    {
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-1', 'order_date' => $this->today, 'tax_id' => $this->tax->id,
            'products' => [$this->taxed->id, $this->exempt->id], 'quantities' => [1, 1], 'prices' => [1000000, 500000], 'discounts' => [0, 0], 'taxable' => ['0', '1']])->assertSessionMissing('error');
        $so = SalesOrder::firstOrFail();

        $page = $this->get('/client/sales-invoices/create?sales_order_id=' . $so->id)->assertOk();
        $page->assertSee('value="' . $this->tax->id . '" data-rate="11.00" selected', false);
        $page->assertSee('"taxable":false', false)->assertSee('"taxable":true', false);
    }

    public function test_default_tax_rate_preselects_on_new_documents_only(): void
    {
        $selected = 'value="' . $this->tax->id . '" data-rate="11.00" selected';

        $this->get('/client/sales-invoices/create')->assertSee($selected, false);
        $this->get('/client/purchases/orders/create')->assertSee($selected, false);

        // Pesanan tanpa pajak yang dibuka lagi tetap tanpa pajak
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-1', 'order_date' => $this->today,
            'products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [1000], 'discounts' => [0]])->assertSessionMissing('error');
        $this->get('/client/sales/orders/' . SalesOrder::firstOrFail()->id . '/edit')->assertDontSee($selected, false);
        $this->get('/client/sales-invoices/create?sales_order_id=' . SalesOrder::firstOrFail()->id)->assertDontSee($selected, false);

        $this->user->company->forceFill(['default_tax_rate' => 0])->save();
        $this->user->refresh();
        $this->actingAs($this->user);
        $this->get('/client/sales-invoices/create')->assertDontSee($selected, false);
    }

    /* ---------------------------------------------------------------- retur */

    public function test_return_follows_the_invoice_line_status(): void
    {
        $inv = $this->sell(['discount_percent' => 10]);
        $this->assertEquals(1449000, (float) $inv->amount_due);

        // Status barang berubah setelah faktur dibuat: retur tetap mengikuti faktur
        $this->exempt->update(['is_taxable' => true]);

        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $inv->id, 'return_number' => 'SR-1', 'return_date' => $this->today,
            'products' => [$this->exempt->id], 'quantities' => [1], 'prices' => [500000]])->assertSessionHas('success');
        $free = SalesReturn::where('return_number', 'SR-1')->firstOrFail();
        $this->assertEquals(0, (float) $free->tax_amount);
        $this->assertEquals(450000, (float) $free->total);
        $this->assertEquals(999000, (float) $inv->fresh()->amount_due);
        $this->assertEquals(-99000, $this->ledger($this->outputAccount()), 'retur baris bebas PPN tidak menyentuh akun PPN');

        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $inv->id, 'return_number' => 'SR-2', 'return_date' => $this->today,
            'products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [1000000]])->assertSessionHas('success');
        $taxed = SalesReturn::where('return_number', 'SR-2')->firstOrFail();
        $this->assertEquals(900000, (float) $taxed->tax_base);
        $this->assertEquals(99000, (float) $taxed->tax_amount);
        $this->assertEquals(0, $this->ledger($this->outputAccount()));
        $this->assertEquals(0, (float) $inv->fresh()->amount_due);
    }

    public function test_purchase_return_follows_the_invoice_line_status(): void
    {
        $inv = $this->buy();
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $inv->id, 'return_number' => 'PR-1', 'return_date' => $this->today,
            'products' => [$this->exempt->id], 'quantities' => [1], 'prices' => [100000]])->assertSessionHas('success');
        $this->assertEquals(0, (float) PurchaseReturn::where('return_number', 'PR-1')->firstOrFail()->tax_amount);
        $this->assertEquals(22000, $this->ledger($this->inputAccount()));

        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $inv->id, 'return_number' => 'PR-2', 'return_date' => $this->today,
            'products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [200000]])->assertSessionHas('success');
        $this->assertEquals(22000, (float) PurchaseReturn::where('return_number', 'PR-2')->firstOrFail()->tax_amount);
        $this->assertEquals(0, $this->ledger($this->inputAccount()));
    }

    public function test_return_list_shows_the_party_and_keeps_the_note(): void
    {
        $inv = $this->sell();
        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $inv->id, 'return_number' => 'SR-1', 'return_date' => $this->today, 'notes' => 'Barang rusak',
            'products' => [$this->exempt->id], 'quantities' => [1], 'prices' => [500000]])->assertSessionHas('success');
        $this->assertSame('Barang rusak', SalesReturn::where('return_number', 'SR-1')->firstOrFail()->reason);
        $this->get('/client/sales/returns')->assertOk()->assertSee('Toko Maju');

        $buy = $this->buy();
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $buy->id, 'return_number' => 'PR-1', 'return_date' => $this->today, 'notes' => 'Salah kirim',
            'products' => [$this->exempt->id], 'quantities' => [1], 'prices' => [100000]])->assertSessionHas('success');
        $this->assertSame('Salah kirim', PurchaseReturn::where('return_number', 'PR-1')->firstOrFail()->reason);
        $this->get('/client/purchases/returns')->assertOk()->assertSee('PT Sumber');
    }

    public function test_return_form_shows_the_invoice_tax_rules(): void
    {
        $inv = $this->sell(['discount_percent' => 10]);
        $page = $this->get('/client/sales/returns/create')->assertOk();
        $page->assertSee('"disc":10', false)->assertSee('"rate":11', false);
        $page->assertSee('"' . $this->exempt->id . '":false', false);
        $this->assertNotNull($inv);
    }

    /* ------------------------------------------------------------ uang muka */

    private function advance(string $number, float $amount, ?int $taxId, string $kind = 'sales'): DownPayment
    {
        $party = $kind === 'sales' ? ['customer_id' => $this->customer->id] : ['supplier_id' => $this->supplier->id];
        $url = $kind === 'sales' ? '/client/sales/down-payments' : '/client/purchases/down-payments';
        $this->post($url, $party + ['payment_number' => $number, 'payment_date' => $this->today, 'amount' => $amount, 'account_id' => $this->cash->id, 'tax_id' => $taxId])
            ->assertSessionMissing('error')->assertSessionHasNoErrors();

        return DownPayment::where('payment_number', $number)->firstOrFail();
    }

    public function test_sales_advance_with_vat_splits_the_amount_and_reverses_on_use(): void
    {
        $advanceAccount = AccountResolver::salesAdvance($this->cid);
        $dp = $this->advance('DP-S-1', 1110000, $this->tax->id);

        $this->assertEquals(1000000, (float) $dp->tax_base);
        $this->assertEquals(110000, (float) $dp->tax_amount);
        $this->assertEquals(-1000000, $this->ledger($advanceAccount));
        $this->assertEquals(-110000, $this->ledger($this->outputAccount()));
        $this->assertEquals(1110000, $this->ledger($this->cash));

        // Faktur DPP 2.000.000, PPN 220.000. Uang muka dipakai dua kali sampai habis.
        $inv = $this->sell(['products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [2000000], 'discounts' => [0]]);
        $this->assertEquals(-330000, $this->ledger($this->outputAccount()));

        $first = DownPaymentService::apply($dp, $inv, 'sales', 555000, $this->today, $this->user->id);
        $this->assertEquals(55000, (float) $first->tax_amount);
        $this->assertEquals(-500000, $this->ledger($advanceAccount));
        $this->assertEquals(-275000, $this->ledger($this->outputAccount()));

        $second = DownPaymentService::apply($dp->fresh(), $inv->fresh(), 'sales', 555000, $this->today, $this->user->id);
        $this->assertEquals(55000, (float) $second->tax_amount);
        $this->assertEquals(0, $this->ledger($advanceAccount));
        $this->assertEquals(-220000, $this->ledger($this->outputAccount()), 'total PPN transaksi sama dengan PPN faktur');
        $this->assertEquals(0, $dp->fresh()->remaining);
        $this->assertEquals(2220000 - 1110000, (float) $inv->fresh()->amount_due);

        // Pembatalan pemakaian mengembalikan akun uang muka dan PPN
        DownPaymentService::void($second, $this->user->id);
        $this->assertEquals(-500000, $this->ledger($advanceAccount));
        $this->assertEquals(-275000, $this->ledger($this->outputAccount()));
    }

    public function test_last_use_of_an_advance_takes_the_remaining_tax_without_rounding_gap(): void
    {
        $dp = $this->advance('DP-S-1', 100000, $this->tax->id); // PPN 9.909,91
        $this->assertEquals(9909.91, (float) $dp->tax_amount);
        $inv = $this->sell(['products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [1000000], 'discounts' => [0]]);

        foreach ([33333.33, 33333.33, 33333.34] as $part) {
            DownPaymentService::apply($dp->fresh(), $inv->fresh(), 'sales', $part, $this->today, $this->user->id);
        }

        $this->assertEquals(0, $this->ledger(AccountResolver::salesAdvance($this->cid)));
        $this->assertEquals(-110000, $this->ledger($this->outputAccount()));
        $this->assertEquals(9909.91, round((float) $dp->fresh()->applications()->sum('tax_amount'), 2));
    }

    public function test_purchase_advance_with_vat_splits_the_amount_and_reverses_on_use(): void
    {
        $advanceAccount = AccountResolver::purchaseAdvance($this->cid);
        $dp = $this->advance('DP-P-1', 111000, $this->tax->id, 'purchase');

        $this->assertEquals(100000, $this->ledger($advanceAccount));
        $this->assertEquals(11000, $this->ledger($this->inputAccount()));
        $this->assertEquals(-111000, $this->ledger($this->cash));

        $inv = $this->buy(['products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [100000], 'discounts' => [0]]);
        $this->assertEquals(22000, $this->ledger($this->inputAccount()));

        DownPaymentService::apply($dp, $inv, 'purchase', 111000, $this->today, $this->user->id);
        $this->assertEquals(0, $this->ledger($advanceAccount));
        $this->assertEquals(11000, $this->ledger($this->inputAccount()));
        $this->assertEquals(0, (float) $inv->fresh()->amount_due);
    }

    public function test_advance_without_vat_keeps_the_old_journal(): void
    {
        $dp = $this->advance('DP-S-1', 1000000, null);

        $this->assertEquals(0, (float) $dp->tax_amount);
        $this->assertEquals(-1000000, $this->ledger(AccountResolver::salesAdvance($this->cid)));
        $this->assertEquals(0, $this->ledger($this->outputAccount()));

        $inv = $this->sell(['tax_id' => null]);
        $app = DownPaymentService::apply($dp, $inv, 'sales', 1000000, $this->today, $this->user->id);
        $this->assertEquals(0, (float) $app->tax_amount);
        $this->assertEquals(0, $this->ledger(AccountResolver::salesAdvance($this->cid)));
        $this->assertEquals(0, $this->ledger($this->outputAccount()));
    }

    /* ------------------------------------------------------ No. Faktur Pajak */

    public function test_tax_invoice_number_can_be_added_later_and_is_validated(): void
    {
        $inv = $this->sell(['tax_invoice_number' => '04002500000000001']);
        $this->assertEquals('04002500000000001', $inv->tax_invoice_number);

        $other = $this->sell();
        $this->from('/client/sales-invoices/' . $other->id)->post('/client/sales-invoices/' . $other->id . '/tax-number', ['tax_invoice_number' => 'BUKAN ANGKA'])
            ->assertSessionHasErrors('tax_invoice_number');
        $this->post('/client/sales-invoices/' . $other->id . '/tax-number', ['tax_invoice_number' => '04002500000000001'])
            ->assertSessionHasErrors('tax_invoice_number');
        $this->post('/client/sales-invoices/' . $other->id . '/tax-number', ['tax_invoice_number' => '010.000-26.12345678'])
            ->assertSessionHas('success');
        $this->assertEquals('010.000-26.12345678', $other->fresh()->tax_invoice_number);

        $this->get('/client/sales-invoices/' . $other->id)->assertOk()->assertSee('010.000-26.12345678');

        // Pemasok yang sama boleh punya nomor yang sama dengan nomor penjualan: dua pihak berbeda
        $pi = $this->buy(['tax_invoice_number' => '04002500000000001']);
        $this->assertEquals('04002500000000001', $pi->tax_invoice_number);
        $this->post('/client/purchase-invoices/' . $pi->id . '/tax-number', ['tax_invoice_number' => ''])->assertSessionHas('success');
        $this->assertNull($pi->fresh()->tax_invoice_number);
        $this->get('/client/purchase-invoices/' . $pi->id)->assertOk()->assertSee('No. Faktur Pajak Pemasok');

        $dp = $this->advance('DP-S-1', 111000, $this->tax->id);
        $this->post('/client/down-payments/' . $dp->id . '/tax-number', ['tax_invoice_number' => '04002500000000009'])->assertSessionHas('success');
        $this->assertEquals('04002500000000009', $dp->fresh()->tax_invoice_number);
        $this->get('/client/sales/down-payments')->assertOk()->assertSee('04002500000000009');
    }

    public function test_number_of_a_voided_invoice_can_be_used_again(): void
    {
        $void = $this->sell(['tax_invoice_number' => '04002500000000005']);
        $this->post('/client/sales-invoices/' . $void->id . '/void')->assertSessionMissing('error');

        $this->assertEquals('04002500000000005', $this->sell(['tax_invoice_number' => '04002500000000005'])->tax_invoice_number);
    }

    public function test_lines_keep_their_tax_status_when_the_server_rejects_the_invoice(): void
    {
        $goods = Product::create(['company_id' => $this->cid, 'sku' => 'BRG', 'name' => 'Barang Kosong', 'type' => 'goods', 'unit' => 'pcs']);

        $page = $this->from('/client/sales-invoices/create')->followingRedirects()->post('/client/sales-invoices', $this->saleLines([
            'products' => [$goods->id], 'quantities' => [5], 'prices' => [1000], 'discounts' => [0], 'taxable' => ['0'],
        ]));

        $page->assertOk()->assertSee('tidak cukup')->assertSee('"taxable":"0"', false);
    }

    public function test_tax_invoice_number_stays_editable_after_closing_the_period(): void
    {
        $inv = $this->sell();
        $this->user->company->forceFill(['closing_date' => $this->today])->save();
        $this->user->refresh();
        $this->actingAs($this->user);

        $this->post('/client/sales-invoices/' . $inv->id . '/tax-number', ['tax_invoice_number' => '04002500000000002'])->assertSessionHas('success');
        $this->assertEquals('04002500000000002', $inv->fresh()->tax_invoice_number);
    }

    public function test_other_company_cannot_touch_the_tax_invoice_number(): void
    {
        $inv = $this->sell();
        $dp = $this->advance('DP-S-1', 111000, $this->tax->id);
        $other = $this->registerCompany('PT Lain', 'lain@uji.test');
        $this->actingAs($other);

        $this->post('/client/sales-invoices/' . $inv->id . '/tax-number', ['tax_invoice_number' => '1'])->assertNotFound();
        $this->post('/client/down-payments/' . $dp->id . '/tax-number', ['tax_invoice_number' => '1'])->assertNotFound();
        auth()->logout();
        $this->assertNull(SalesInvoice::withoutGlobalScopes()->find($inv->id)->tax_invoice_number);
    }

    /* --------------------------------------------------------- Laporan PPN */

    public function test_report_matches_documents_and_ledger(): void
    {
        $sale = $this->sell(['discount_percent' => 10, 'tax_invoice_number' => '04002500000000001']); // DPP 900.000, PPN 99.000
        $buy = $this->buy(['tax_invoice_number' => '04002500000000002']); // DPP 200.000, PPN 22.000

        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $sale->id, 'return_number' => 'SR-1', 'return_date' => $this->today,
            'products' => [$this->taxed->id], 'quantities' => [0.5], 'prices' => [1000000]])->assertSessionHas('success'); // DPP 450.000, PPN 49.500
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $buy->id, 'return_number' => 'PR-1', 'return_date' => $this->today,
            'products' => [$this->taxed->id], 'quantities' => [0.5], 'prices' => [200000]])->assertSessionHas('success'); // DPP 100.000, PPN 11.000

        $dp = $this->advance('DP-S-1', 111000, $this->tax->id); // DPP 100.000, PPN 11.000
        DownPaymentService::apply($dp, $sale->fresh(), 'sales', 55500, $this->today, $this->user->id); // DPP -50.000, PPN -5.500

        $doc = $this->report();
        $s = $this->summary($doc);
        $this->assertEquals(99000 - 49500 + 11000 - 5500, $s['PPN Keluaran']);
        $this->assertEquals(22000 - 11000, $s['PPN Masukan']);
        $this->assertEquals(55000 - 11000, $s['Kurang Bayar']);
        $this->assertEquals(0, $s['Selisih dengan Buku Besar']);
        $this->assertStringContainsString('cocok dengan Buku Besar', $doc['notes'][0]['text']);
        $this->assertSame('good', $doc['notes'][0]['tone']);

        // Saldo akun sama dengan laporan
        $this->assertEquals(-55000, $this->ledger($this->outputAccount()));
        $this->assertEquals(11000, $this->ledger($this->inputAccount()));

        $faktur = $this->rowsOfType($doc, 'Faktur');
        $this->assertCount(2, $faktur);
        $this->assertSame([$this->today, $sale->invoice_number, '04002500000000001', 'Toko Maju', '01.234.567.8-901.000', 'Faktur', 900000.0, '11%', 99000.0], $faktur[0]['cells']);
        $this->assertEquals(-450000, $this->rowsOfType($doc, 'Retur')[0]['cells'][6]);
        $this->assertEquals(-49500, $this->rowsOfType($doc, 'Retur')[0]['cells'][8]);
        $this->assertEquals(100000, $this->rowsOfType($doc, 'Uang Muka')[0]['cells'][6]);
        $this->assertEquals(-5500, $this->rowsOfType($doc, 'Pakai Uang Muka')[0]['cells'][8]);
    }

    public function test_report_shows_lebih_bayar_when_input_tax_is_larger(): void
    {
        $this->buy();

        $s = $this->summary($this->report());

        $this->assertEquals(22000, $s['Lebih Bayar']);
        $this->assertArrayNotHasKey('Kurang Bayar', $s->all());
    }

    public function test_report_skips_void_documents_other_periods_and_other_companies(): void
    {
        $void = $this->sell();
        $this->post('/client/sales-invoices/' . $void->id . '/void')->assertSessionMissing('error');
        $this->assertEquals('void', $void->fresh()->status);

        $this->sell(['invoice_date' => date('Y-m-d', strtotime('first day of last month')), 'due_date' => $this->today]);

        $other = $this->registerCompany('PT Lain', 'lain@uji.test');
        $this->actingAs($other);
        $tax = Tax::create(['company_id' => $other->company_id, 'name' => 'PPN', 'rate' => 11, 'is_active' => true]);
        $customer = Customer::create(['company_id' => $other->company_id, 'name' => 'Pihak Lain', 'default_term_days' => 0, 'is_active' => true]);
        $svc = Product::create(['company_id' => $other->company_id, 'sku' => 'X', 'name' => 'Jasa X', 'type' => 'service', 'unit' => 'kali']);
        $this->post('/client/sales-invoices', ['customer_id' => $customer->id, 'invoice_date' => $this->today, 'due_date' => $this->today, 'tax_id' => $tax->id,
            'products' => [$svc->id], 'quantities' => [1], 'prices' => [100000], 'discounts' => [0]])->assertSessionMissing('error');
        $this->actingAs($this->user);

        $doc = $this->report();
        $s = $this->summary($doc);
        $this->assertEquals(0, $s['PPN Keluaran']);
        $this->assertEquals(0, $s['Selisih dengan Buku Besar'], 'pembatalan pada periode yang sama saling meniadakan');
        $this->assertCount(0, $this->rowsOfType($doc, 'Jurnal lain'));

        $last = $this->report(['start_date' => date('Y-m-d', strtotime('first day of last month')), 'end_date' => date('Y-m-d', strtotime('last day of last month'))]);
        $this->assertEquals(110000, $this->summary($last)['PPN Keluaran']);
    }

    public function test_report_explains_a_difference_from_manual_journals(): void
    {
        $this->sell();
        $manual = Journal::create(['company_id' => $this->cid, 'ref_number' => 'JV-MANUAL', 'type' => 'general', 'transaction_date' => $this->today,
            'description' => 'Koreksi PPN manual', 'status' => 'posted', 'created_by' => $this->user->id]);
        JournalDetail::create(['journal_id' => $manual->id, 'account_id' => $this->outputAccount()->id, 'debit' => 5000, 'credit' => 0, 'description' => 'x']);
        JournalDetail::create(['journal_id' => $manual->id, 'account_id' => $this->cash->id, 'debit' => 0, 'credit' => 5000, 'description' => 'x']);

        $doc = $this->report();

        $this->assertEquals(5000, $this->summary($doc)['Selisih dengan Buku Besar']);
        $this->assertSame('bad', $doc['notes'][0]['tone']);
        $this->assertStringContainsString('Ada selisih', $doc['notes'][0]['text']);
        $other = $this->rowsOfType($doc, 'Jurnal lain');
        $this->assertCount(1, $other);
        $this->assertSame('JV-MANUAL', $other[0]['cells'][1]);
        $this->assertEquals(-5000, $other[0]['cells'][8]);
    }

    public function test_long_list_of_other_journals_is_capped(): void
    {
        for ($i = 1; $i <= 32; $i++) {
            $j = Journal::create(['company_id' => $this->cid, 'ref_number' => 'JV-' . $i, 'type' => 'general', 'transaction_date' => $this->today,
                'description' => 'Jurnal ' . $i, 'status' => 'posted', 'created_by' => $this->user->id]);
            JournalDetail::create(['journal_id' => $j->id, 'account_id' => $this->outputAccount()->id, 'debit' => 100, 'credit' => 0, 'description' => 'x']);
            JournalDetail::create(['journal_id' => $j->id, 'account_id' => $this->cash->id, 'debit' => 0, 'credit' => 100, 'description' => 'x']);
        }

        $doc = $this->report();

        $this->assertCount(30, $this->rowsOfType($doc, 'Jurnal lain'));
        $this->assertEquals(3200, $this->summary($doc)['Selisih dengan Buku Besar'], 'selisih tetap menghitung semua jurnal');
        $this->assertNotNull(collect($doc['rows'])->first(fn ($r) => str_contains((string) ($r['cells'][3] ?? ''), 'Dan 2 jurnal lain')));
    }

    public function test_income_tax_on_an_invoice_is_not_reported_as_vat(): void
    {
        $account = Account::create(['company_id' => $this->cid, 'code' => '2-1199', 'name' => 'Utang Pajak Lain', 'type' => 'liability', 'normal_balance' => 'credit', 'is_active' => true]);
        $pph = Tax::create(['company_id' => $this->cid, 'name' => 'PPh 23', 'rate' => 2, 'sales_tax_account_id' => $account->id, 'purchase_tax_account_id' => $account->id, 'is_active' => true]);

        $this->sell(['tax_id' => $pph->id, 'products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [1000000], 'discounts' => [0]]);
        $this->sell(['products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [100000], 'discounts' => [0]]);

        $doc = $this->report();
        $this->assertEquals(11000, $this->summary($doc)['PPN Keluaran']);
        $this->assertCount(1, $this->rowsOfType($doc, 'Faktur'));
        $this->assertEquals(0, $this->summary($doc)['Selisih dengan Buku Besar'], 'akun PPh bukan akun PPN');

        $page = $this->get('/client/sales/down-payments/create')->assertOk();
        $page->assertSee('PPN 11%')->assertDontSee('PPh 23');
    }

    public function test_report_handles_one_account_for_both_output_and_input(): void
    {
        $shared = $this->outputAccount();
        $this->tax->update(['sales_tax_account_id' => $shared->id, 'purchase_tax_account_id' => $shared->id]);
        // Akun masukan standar tidak dipakai perusahaan ini
        $this->inputAccount()->update(['is_active' => false]);

        $this->sell();
        $this->buy();

        $doc = $this->report();
        $s = $this->summary($doc);
        $this->assertEquals(88000, $s['Kurang Bayar']);
        $this->assertEquals(0, $s['Selisih dengan Buku Besar']);
    }

    public function test_report_counts_documents_without_a_tax_invoice_number(): void
    {
        $this->sell();
        $this->buy(['tax_invoice_number' => '04002500000000002']);
        $this->advance('DP-S-1', 111000, $this->tax->id);

        $doc = $this->report();

        $this->assertSame('good', $doc['notes'][0]['tone'], 'kalimat cocok tetap berwarna baik walau ada nomor yang belum diisi');
        $this->assertStringContainsString('2 dokumen ber-PPN belum punya No. Faktur Pajak', $doc['notes'][1]['text']);
        $this->assertSame('bad', $doc['notes'][1]['tone']);
    }

    public function test_report_lists_sales_without_vat_as_information_only(): void
    {
        $this->sell(); // baris bebas PPN 500.000
        $this->sell(['tax_id' => null, 'products' => [$this->taxed->id], 'quantities' => [1], 'prices' => [300000], 'discounts' => [0]]); // faktur tanpa pajak

        $doc = $this->report();
        $info = collect($doc['rows'])->first(fn ($r) => str_contains((string) ($r['cells'][3] ?? ''), 'Penjualan tanpa PPN'));

        $this->assertEquals(800000, $info['cells'][6]);
        $this->assertEquals(110000, $this->summary($doc)['PPN Keluaran']);
    }

    public function test_report_filter_and_exports(): void
    {
        $this->sell(['tax_invoice_number' => '04002500000000001']);
        $this->buy();

        $page = $this->get('/client/reports/ppn')->assertOk();
        $page->assertSee('Laporan PPN')->assertSee('PAJAK KELUARAN')->assertSee('PAJAK MASUKAN')->assertSee('KURANG BAYAR')->assertSee('KECOCOKAN DENGAN BUKU BESAR');
        $page->assertSee('Toko Maju')->assertSee('04002500000000001')->assertSee('01.234.567.8-901.000');

        $this->get('/client/reports/ppn?jenis=keluaran')->assertOk()->assertSee('PAJAK KELUARAN')->assertDontSee('PAJAK MASUKAN');
        $this->get('/client/reports/ppn?jenis=masukan')->assertOk()->assertSee('PAJAK MASUKAN')->assertDontSee('PAJAK KELUARAN');

        $this->get('/client/reports/ppn?export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $xlsx = $this->get('/client/reports/ppn?export=excel')->assertOk();
        $this->assertStringContainsString('spreadsheet', (string) $xlsx->headers->get('content-type'));
        $this->get('/client/reports/ppn?export=pdf')->assertOk()->assertSee('Laporan PPN')->assertSee('Halaman');

        $this->get('/client/reports/spt-ppn')->assertRedirect('/client/reports/ppn');
        $this->get('/client/reports')->assertOk()->assertSee('Laporan PPN')->assertDontSee('SPT PPN');
    }

    public function test_empty_report_still_renders(): void
    {
        $this->get('/client/reports/ppn')->assertOk()->assertSee('Tidak ada transaksi ber-PPN pada periode ini.')->assertSee('cocok dengan Buku Besar');
    }

    /* ------------------------------------------------------------ Preferensi */

    public function test_each_preferences_tab_saves_on_its_own(): void
    {
        $company = $this->user->company;

        $this->post('/client/settings/preferences', ['default_tax_rate' => '12', 'tax_rounding' => DocumentTax::ROUND_DOWN])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(12, (float) $company->fresh()->default_tax_rate);
        $this->assertSame(DocumentTax::ROUND_DOWN, $company->fresh()->tax_rounding);
        $this->assertSame('PT Pajak', $company->fresh()->name, 'tab lain tidak boleh mengubah nama perusahaan');

        $this->post('/client/settings/preferences', ['address' => 'Jl. Merdeka 1'])->assertSessionHasNoErrors();
        $this->assertSame('Jl. Merdeka 1', $company->fresh()->address);

        // Tab Info tetap mewajibkan nama
        $this->post('/client/settings/preferences', ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_period_lock_only_changes_through_month_end(): void
    {
        $company = $this->user->company;
        $company->forceFill(['closing_date' => '2026-01-31'])->save();

        // Mengosongkan atau memajukan tanggal dari Preferensi tidak berpengaruh
        $this->post('/client/settings/preferences', ['closing_date' => ''])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-31', substr((string) $company->fresh()->closing_date, 0, 10));
        $this->post('/client/settings/preferences', ['closing_date' => '2099-12-31'])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-31', substr((string) $company->fresh()->closing_date, 0, 10));

        $this->get('/client/settings/preferences')->assertOk()->assertDontSee('name="closing_date"', false);
    }

    public function test_costing_method_locks_once_stock_has_moved(): void
    {
        $company = $this->user->company;
        $this->post('/client/settings/preferences', ['inventory_costing_method' => 'fifo'])->assertSessionHasNoErrors();
        $this->assertSame('fifo', $company->fresh()->inventory_costing_method);
        $this->post('/client/settings/preferences', ['inventory_costing_method' => 'average'])->assertSessionHasNoErrors();

        $goods = Product::create(['company_id' => $this->cid, 'sku' => 'BRG', 'name' => 'Barang', 'type' => 'goods', 'unit' => 'pcs']);
        \App\Models\InventoryMovement::create(['company_id' => $this->cid, 'product_id' => $goods->id, 'date' => $this->today, 'type' => 'in', 'quantity' => 1, 'unit_cost' => 100,
            'remaining_qty' => 1, 'reference_type' => 'manual', 'reference_id' => 1, 'created_by' => $this->user->id]);

        $this->post('/client/settings/preferences', ['inventory_costing_method' => 'fifo'])->assertSessionHasErrors('inventory_costing_method');
        $this->assertSame('average', $company->fresh()->inventory_costing_method);
        // Mengirim metode yang sama tetap boleh
        $this->post('/client/settings/preferences', ['inventory_costing_method' => 'average'])->assertSessionHasNoErrors();
    }

    /* ---------------------------------------------------------- pembersihan */

    public function test_fake_settings_are_gone_and_calculator_is_honest(): void
    {
        $prefs = $this->get('/client/settings/preferences')->assertOk();
        $prefs->assertDontSee("activeMenu = 'akun'", false)->assertDontSee('Simpan Pemetaan')->assertDontSee('Multi Mata Uang')->assertDontSee('Selisih Kurs');

        $calc = $this->get('/client/reports/tax-calculator')->assertOk();
        $calc->assertDontSee('Masa depan UU HPP')->assertDontSee('static.pajak.go.id')->assertDontSee('standar Direktorat Jenderal Pajak');
        $calc->assertSee('1,75%')->assertSee('2,65%')->assertSee('TER');
        $this->get('/client/reports')->assertDontSee('PPh 21, 22, 23');
    }
}
