<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur utama: SO -> Pengiriman -> Faktur -> Pembayaran, PO -> Penerimaan -> Faktur -> Pembayaran,
 * ditambah pembatalan faktur dan validasi jumlah/stok.
 */
class DocumentFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $product;
    private Account $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create(['name' => 'Test', 'price' => 0, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 5,
            'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => true, 'trial_days' => 14, 'is_active' => true]);
        $company = Company::create(['name' => 'PT Uji', 'email' => 'uji@example.com', 'fiscal_year_start' => 1, 'is_active' => true]);
        $company->forceFill(['is_premium' => true])->save();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'amount_paid' => 0]);
        $this->user = User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => 'a@example.com',
            'password' => bcrypt('password'), 'role' => 'client_admin', 'is_active' => true]);

        CoaSeeder::seed($company->id);
        $this->cash = Account::where('company_id', $company->id)->where('name', 'like', 'Kas%')->where('type', 'asset')->get()
            ->first(fn ($a) => !$a->is_header);
        $this->cash->forceFill(['is_cash_bank' => true])->save();

        $this->customer = Customer::create(['company_id' => $company->id, 'name' => 'Pelanggan', 'default_term_days' => 30, 'is_active' => true]);
        $this->supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Pemasok', 'default_term_days' => 30, 'is_active' => true]);
        $this->warehouse = Warehouse::create(['company_id' => $company->id, 'code' => 'WH1', 'name' => 'Gudang', 'is_active' => true]);
        $rev = Account::where('company_id', $company->id)->where('type', 'revenue')->where('name', 'Pendapatan Penjualan')->first();
        $exp = Account::where('company_id', $company->id)->where('type', 'expense')->where('name', 'like', 'Biaya Transportasi%')->first();
        $this->product = Product::create(['company_id' => $company->id, 'name' => 'Barang', 'type' => 'goods', 'unit' => 'pcs',
            'sell_price' => 15000, 'buy_price' => 10000, 'is_active' => true, 'revenue_account_id' => $rev->id, 'expense_account_id' => $exp->id]);

        $this->actingAs($this->user);
    }

    private function assertNetZeroPerAccount(int $journalA, int $journalB): void
    {
        $net = \App\Models\JournalDetail::whereIn('journal_id', [$journalA, $journalB])->get()
            ->groupBy('account_id')->map(fn ($g) => round($g->sum('debit') - $g->sum('credit'), 2));
        $this->assertTrue($net->every(fn ($v) => $v == 0), 'Jurnal asli + jurnal balik harus bersih nol per akun');
    }

    private function createPurchaseAndReceive(float $qty = 10): PurchaseOrder
    {
        $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-T-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [$qty], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();
        $po = PurchaseOrder::firstOrFail();
        $this->post('/client/purchases/receipts', ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $po->id, 'receipt_number' => 'IR-T-1', 'receipt_date' => date('Y-m-d'), 'status' => 'received',
            'products' => [$this->product->id], 'quantities' => [$qty]])->assertRedirect();

        return $po->fresh();
    }

    public function test_purchase_chain_with_partial_payment_updates_status_and_journal(): void
    {
        $po = $this->createPurchaseAndReceive(10);
        $this->assertSame('received', $po->status);
        $this->assertEquals(10, $this->product->fresh()->stock);

        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();
        $inv = PurchaseInvoice::firstOrFail();
        $this->assertSame($po->id, $inv->po_id);
        $this->assertSame('converted', $po->fresh()->status);

        // Barang dagang didebit ke Persediaan, bukan akun biaya produk
        $debitAccounts = $inv->journal->details->where('debit', '>', 0)->pluck('account.name')->implode(',');
        $this->assertStringContainsString('Persediaan', $debitAccounts);

        $this->post("/client/purchase-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => 40000, 'payment_date' => date('Y-m-d')])
            ->assertSessionHas('success');
        $inv->refresh();
        $this->assertSame('partial', $inv->status);
        $this->assertEquals(60000, (float) $inv->amount_due);

        $this->post("/client/purchase-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => 60000, 'payment_date' => date('Y-m-d')]);
        $this->assertSame('paid', $inv->fresh()->status);

        // Tidak boleh bayar melebihi sisa, dan tidak boleh dibatalkan setelah dibayar
        $this->post("/client/purchase-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => 1, 'payment_date' => date('Y-m-d')])
            ->assertSessionHas('error');
        $this->post("/client/purchase-invoices/{$inv->id}/void")->assertSessionHas('error');
    }

    public function test_sales_chain_and_quantity_validation(): void
    {
        $this->createPurchaseAndReceive(10);

        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-T-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [5], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        $so = SalesOrder::firstOrFail();

        // Kirim lebih dari pesanan ditolak
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-X',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [6]])
            ->assertSessionHas('error');
        $this->assertSame(0, DeliveryOrder::count());

        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-T-1',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [5]])->assertRedirect();
        $this->assertEquals(5, $this->product->fresh()->stock);
        $this->assertSame('open', $so->fresh()->status); // belum ditagih

        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id,
            'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [5], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        $inv = SalesInvoice::firstOrFail();
        $this->assertSame($so->id, $inv->sales_order_id);
        $this->assertSame('closed', $so->fresh()->status);

        $this->post("/client/sales-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => 75000, 'payment_date' => date('Y-m-d')]);
        $this->assertSame('paid', $inv->fresh()->status);
        $this->get("/client/sales-invoices/{$inv->id}")->assertOk()->assertSee('LUNAS');
    }

    public function test_delivery_rejected_when_stock_insufficient(): void
    {
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'delivery_number' => 'DO-S', 'delivery_date' => date('Y-m-d'),
            'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [1]])->assertSessionHas('error');
        $this->assertSame(0, DeliveryOrder::count());
    }

    public function test_void_unpaid_invoice_creates_reversal_and_reopens_order(): void
    {
        $this->createPurchaseAndReceive(10);
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-T-2', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [2], 'prices' => [15000], 'discounts' => [0]]);
        $so = SalesOrder::firstOrFail();
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-T-2',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [2]]);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'invoice_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'), 'products' => [$this->product->id], 'quantities' => [2], 'prices' => [15000], 'discounts' => [0]]);
        $inv = SalesInvoice::firstOrFail();
        $this->assertSame('closed', $so->fresh()->status);

        $this->post("/client/sales-invoices/{$inv->id}/void")->assertSessionHas('success');
        $this->assertSame('void', $inv->fresh()->status);
        $this->assertSame('open', $so->fresh()->status);

        // jurnal balik: debit/kredit tertukar sehingga saldo akun kembali nol
        $orig = $inv->journal->details;
        $rev = \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->firstOrFail()->details;
        $this->assertEqualsWithDelta($orig->sum('debit'), $rev->sum('credit'), 0.01);
        $this->assertEqualsWithDelta($orig->sum('credit'), $rev->sum('debit'), 0.01);
        $this->assertNetZeroPerAccount($inv->journal_id, \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->first()->id);
    }

    public function test_show_pages_render(): void
    {
        $po = $this->createPurchaseAndReceive(3);
        $this->get('/client/purchases/orders/' . $po->id)->assertOk()->assertSee('Progres Pesanan');
        $this->get('/client/purchases/receipts/' . \App\Models\ItemReceipt::firstOrFail()->id)->assertOk();
        $this->get('/client/dashboard')->assertOk();
    }

    public function test_global_search_finds_documents_and_is_scoped_to_company(): void
    {
        $this->createPurchaseAndReceive(1);
        $this->getJson('/client/search?q=PO-T')->assertOk()->assertJsonFragment(['label' => 'PO-T-1']);
        $this->getJson('/client/search?q=Pelanggan')->assertOk()->assertJsonFragment(['group' => 'Pelanggan']);
        $this->getJson('/client/search?q=x')->assertOk()->assertExactJson(['results' => []]);

        // Perusahaan lain tidak boleh terlihat
        $other = Company::create(['name' => 'PT Lain', 'email' => 'o@example.com', 'fiscal_year_start' => 1, 'is_active' => true]);
        Customer::create(['company_id' => $other->id, 'name' => 'Rahasia Pelanggan', 'default_term_days' => 30, 'is_active' => true]);
        $this->getJson('/client/search?q=Rahasia')->assertOk()->assertExactJson(['results' => []]);
    }

    public function test_dashboard_shows_overdue_and_low_stock_alerts(): void
    {
        $this->createPurchaseAndReceive(2); // stok 2 (<= 5)
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d', strtotime('-40 days')),
            'due_date' => date('Y-m-d', strtotime('-10 days')), 'products' => [$this->product->id], 'quantities' => [1], 'prices' => [10000], 'discounts' => [0]]);
        $this->get('/client/dashboard')->assertOk()->assertSee('hutang jatuh tempo')->assertSee('stok menipis');
    }

    public function test_all_reports_render_and_export_in_every_format(): void
    {
        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-9',
            'invoice_date' => date('Y-m-d', strtotime('-50 days')), 'due_date' => date('Y-m-d', strtotime('-40 days')),
            'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();

        $routes = ['integrated', 'profit-loss', 'balance-sheet', 'general-ledger', 'trial-balance', 'receivable-aging', 'receivable-summary',
            'sales-report', 'payable-aging', 'payable-summary', 'purchase-report', 'inventory-stock'];

        foreach ($routes as $r) {
            $this->get("/client/reports/{$r}")->assertOk()->assertSee('PT Uji');
            $this->get("/client/reports/{$r}?export=pdf")->assertOk()->assertSee('window.print');
            $this->get("/client/reports/{$r}?export=csv")->assertOk();

            $xlsx = $this->get("/client/reports/{$r}?export=excel");
            $xlsx->assertOk();
            $tmp = tempnam(sys_get_temp_dir(), 'rpt') . '.xlsx';
            file_put_contents($tmp, $xlsx->streamedContent());
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
            $this->assertSame('PT UJI', $sheet->getCell('A1')->getValue(), $r);
            unlink($tmp);
        }

        // Hutang 100.000 belum jatuh tempo >30 hari -> muncul di umur hutang & rekap
        $this->get('/client/reports/payable-aging')->assertSee('100.000');
        $this->get('/client/reports/payable-summary')->assertSee('Pemasok')->assertSee('100.000');
        $this->get('/client/reports/inventory-stock')->assertSee('Barang');
        // Neraca seimbang setelah pembelian kredit
        $this->get('/client/reports/balance-sheet')->assertSee('Neraca seimbang');
        $this->get('/client/reports/trial-balance')->assertSee('Seimbang');
        $this->get('/client/reports')->assertOk()->assertSee('Umur Piutang');
    }

    public function test_preferences_tabs_save_independently_and_tolerate_empty_fields(): void
    {
        $company = $this->user->company;
        $this->post('/client/settings/preferences', ['name' => 'PT Uji', 'phone' => '0812', 'email' => '', 'npwp' => '', 'fiscal_year_start' => ''])
            ->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertSame(1, (int) $company->fiscal_year_start);
        $this->assertTrue((bool) $company->warn_below_cost, 'tab info tidak boleh mereset checkbox tab lain');

        $this->post('/client/settings/preferences', ['name' => 'PT Uji', 'warn_below_cost' => '0', 'default_tax_rate' => '12', 'fiscal_year_start' => 4])
            ->assertSessionHasNoErrors();
        $company->refresh();
        $this->assertFalse((bool) $company->warn_below_cost);
        $this->assertEquals(12, (float) $company->default_tax_rate);
        $this->assertSame(4, (int) $company->fiscal_year_start);
        $this->get('/client/settings/preferences')->assertOk();
    }

    public function test_company_logo_upload_shows_in_header_and_reports(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->image('logo.png', 120, 120);
        $this->post('/client/settings/preferences', ['name' => 'PT Uji', 'logo' => $file])->assertSessionHasNoErrors();
        $company = $this->user->company->fresh();
        $this->assertNotNull($company->logo_url);
        $this->get('/client/dashboard')->assertSee('company-badge-logo', false);
        $this->get('/client/reports/profit-loss?export=pdf')->assertSee('print-logo', false);
        @unlink(public_path($company->logo_path));
    }

    public function test_opening_balance_is_counted_once_and_balances(): void
    {
        $this->post('/client/accounts/beginning-balance', ['date' => date('Y-m-d'), 'balances' => [$this->cash->id => 100000000]])->assertSessionHas('success');
        $this->assertEquals(100000000, $this->cash->fresh()->balance, 'saldo awal tidak boleh terhitung dua kali');
        // simpan ulang tidak menggandakan
        $this->post('/client/accounts/beginning-balance', ['date' => date('Y-m-d'), 'balances' => [$this->cash->id => 100000000]]);
        $this->assertEquals(100000000, $this->cash->fresh()->balance);

        $po = $this->createPurchaseAndReceive(25);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'), 'products' => [$this->product->id], 'quantities' => [25], 'prices' => [10000], 'discounts' => [0]]);
        $pi = PurchaseInvoice::firstOrFail();
        $this->post("/client/purchase-invoices/{$pi->id}/payments", ['account_id' => $this->cash->id, 'amount' => $pi->total, 'payment_date' => date('Y-m-d')]);

        $inv = Account::where('company_id', $this->user->company_id)->where('name', 'like', 'Persediaan Barang%')->first();
        $this->assertEquals(99750000, $this->cash->fresh()->balance);
        $this->assertEquals(250000, $inv->fresh()->balance);
        $this->get('/client/reports/balance-sheet')->assertSee('Neraca seimbang');
        $this->get('/client/reports/trial-balance')->assertSee('Seimbang');

        // ubah lewat form akun juga tidak menggandakan
        $this->put("/client/accounts/{$this->cash->id}", ['name' => $this->cash->name, 'type' => 'asset', 'normal_balance' => 'debit', 'opening_balance' => 50000000]);
        $this->assertEquals(49750000, $this->cash->fresh()->balance);
    }

    private function assertBooksBalanced(): void
    {
        $this->get('/client/reports/trial-balance')->assertSee('Seimbang: total debit');
        $this->get('/client/reports/balance-sheet')->assertSee('Neraca seimbang');
    }

    public function test_full_cycle_with_discount_tax_returns_and_advances_keeps_books_balanced(): void
    {
        $cid = $this->user->company_id;
        $tax = \App\Models\Tax::create(['company_id' => $cid, 'name' => 'PPN 11%', 'rate' => 11, 'is_active' => true]); // tanpa akun terpilih -> dicari otomatis
        $today = date('Y-m-d');

        $this->post('/client/accounts/beginning-balance', ['date' => $today, 'balances' => [$this->cash->id => 100000000]]);

        // Pembelian 10 pcs @10.000, diskon 10% + PPN 11%
        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => $today, 'due_date' => $today, 'discount_percent' => 10, 'tax_id' => $tax->id,
            'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertSessionMissing('error');
        $pi = PurchaseInvoice::firstOrFail();
        $this->assertEquals(99900, (float) $pi->total); // 100.000 - 10% = 90.000 + 11% = 99.900
        $this->assertBooksBalanced();

        // Penjualan langsung 4 pcs @15.000, diskon 10%, PPN 11% -> stok keluar saat faktur
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'discount_percent' => 10, 'tax_id' => $tax->id,
            'products' => [$this->product->id], 'quantities' => [4], 'prices' => [15000], 'discounts' => [0]])->assertSessionMissing('error');
        $si = SalesInvoice::firstOrFail();
        $this->assertEquals(59940, (float) $si->total); // 60.000 - 10% = 54.000 + 11% = 59.940
        $this->assertEquals(6, $this->product->fresh()->stock);
        $this->assertBooksBalanced();

        // Stok tidak cukup ditolak
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [50], 'prices' => [15000], 'discounts' => [0]])->assertSessionHas('error');

        // Retur penjualan 1 pcs: stok +1, sisa tagihan faktur berkurang (13.500 + 11% = 14.985)
        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $si->id, 'return_number' => 'SR-1',
            'return_date' => $today, 'products' => [$this->product->id], 'quantities' => [1], 'prices' => [15000]])->assertSessionHas('success');
        $this->assertEquals(7, $this->product->fresh()->stock);
        $this->assertEquals(59940 - 14985, (float) $si->fresh()->amount_due);
        // Retur berlebih ditolak
        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $si->id, 'return_number' => 'SR-2',
            'return_date' => $today, 'products' => [$this->product->id], 'quantities' => [9], 'prices' => [15000]])->assertSessionHas('error');
        $this->assertBooksBalanced();

        // Retur pembelian 2 pcs: stok -2, hutang berkurang
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-1',
            'return_date' => $today, 'products' => [$this->product->id], 'quantities' => [2], 'prices' => [10000]])->assertSessionHas('success');
        $this->assertEquals(5, $this->product->fresh()->stock);
        $this->assertEquals(99900 - 19980, (float) $pi->fresh()->amount_due);

        // Uang muka tercatat sebagai kewajiban/aset, bukan mengurangi piutang/hutang
        $this->post('/client/sales/down-payments', ['customer_id' => $this->customer->id, 'payment_number' => 'DP-S-1', 'payment_date' => $today, 'amount' => 1000000, 'account_id' => $this->cash->id])->assertSessionHas('success');
        $this->post('/client/purchases/down-payments', ['supplier_id' => $this->supplier->id, 'payment_number' => 'DP-P-1', 'payment_date' => $today, 'amount' => 500000, 'account_id' => $this->cash->id])->assertSessionHas('success');
        $advSales = Account::where('company_id', $cid)->where('name', 'Uang Muka Penjualan')->first();
        $advPurch = Account::where('company_id', $cid)->where('name', 'Uang Muka Pembelian')->first();
        $this->assertEquals(1000000, $advSales->fresh()->balance);
        $this->assertEquals(500000, $advPurch->fresh()->balance);
        $this->assertBooksBalanced();

        // Laba rugi memuat HPP: laba kotor = pendapatan - HPP
        $b = new \App\Services\Reports\ReportBuilder($cid);
        $pl = $b->profitLoss(new \Illuminate\Http\Request());
        $vals = collect($pl['summary'])->pluck('value', 'label');
        $this->assertGreaterThan(0, $vals['Harga Pokok Penjualan']);
        $this->get('/client/reports/profit-loss')->assertSee('LABA KOTOR');
    }

    public function test_depreciation_lets_you_pick_month_and_accounts_and_contra_balance_reduces_its_group(): void
    {
        $cid = $this->user->company_id;
        $asset = \App\Models\FixedAsset::create(['company_id' => $cid, 'code' => 'FA-1', 'name' => 'Laptop', 'acquisition_date' => '2026-04-24',
            'acquisition_cost' => 12000000, 'useful_life_months' => 48, 'salvage_value' => 0, 'depreciation_method' => 'straight_line',
            'status' => 'aktif']);
        $exp = Account::where('company_id', $cid)->where('name', 'like', 'Biaya Penyusutan%')->first();
        $mk = fn ($code, $name, $type, $nb) => Account::create(['company_id' => $cid, 'code' => $code, 'name' => $name, 'type' => $type, 'normal_balance' => $nb]);
        $accNew = $mk('1-2010', 'Akumulasi Penyusutan Peralatan Kantor', 'asset', 'credit');
        $expNew = $mk('6-2010', 'Beban Penyusutan Peralatan Kantor', 'expense', 'debit');

        $this->get('/client/fixed-assets/depreciation?month=2026-04')->assertOk()->assertSee('Akumulasi Penyusutan Peralatan Kantor')->assertSee('250.000,00');
        // bulan sebelum perolehan tidak bisa disusutkan; akun tidak valid ditolak
        $this->post('/client/fixed-assets/run-depreciation', ['month' => '2026-03', 'date' => '2026-03-31', 'assets' => [$asset->id], 'exp' => [$asset->id => $expNew->id], 'acc' => [$asset->id => $accNew->id]])->assertSessionHas('error');
        $this->post('/client/fixed-assets/run-depreciation', ['month' => '2026-04', 'date' => '2026-04-30', 'assets' => [$asset->id], 'exp' => [$asset->id => $this->cash->id], 'acc' => [$asset->id => $accNew->id]])->assertSessionHas('error');
        $this->post('/client/fixed-assets/run-depreciation', ['month' => '2026-04', 'date' => '2026-05-02', 'assets' => [$asset->id], 'exp' => [$asset->id => $expNew->id], 'acc' => [$asset->id => $accNew->id]])->assertSessionHas('error');
        $this->assertEquals(0, (float) $asset->fresh()->accumulated_depreciation);

        // April ke akun pilihan
        $this->post('/client/fixed-assets/run-depreciation', ['month' => '2026-04', 'date' => '2026-04-30', 'assets' => [$asset->id], 'exp' => [$asset->id => $expNew->id], 'acc' => [$asset->id => $accNew->id]])->assertSessionHas('success');
        $this->assertEquals(250000, $expNew->fresh()->balance);
        $this->assertEquals(250000, $accNew->fresh()->balance);
        $this->assertEquals(0, $exp->fresh()->balance); // akun lama tidak tersentuh
        $this->assertSame($accNew->id, (int) $asset->fresh()->accumulated_account_id);
        $this->assertBooksBalanced();
        // bulan yang sama tidak dua kali; Mei memakai akun yang diingat
        $this->post('/client/fixed-assets/run-depreciation', ['month' => '2026-04', 'date' => '2026-04-30', 'assets' => [$asset->id], 'exp' => [$asset->id => $expNew->id], 'acc' => [$asset->id => $accNew->id]])->assertSessionHas('error');
        $this->assertEquals(250000, $expNew->fresh()->balance);
        $this->get('/client/fixed-assets/depreciation?month=2026-05')->assertOk()->assertSee('250.000,00');
        $this->assertSame($accNew->id, (int) \App\Models\FixedAsset::first()->accumulated_account_id);

        // Bagan Akun: akun lawan mengurangi induk (Aset Tetap) dan tampil minus
        $parent = Account::where('company_id', $cid)->where('name', 'Aset Tetap')->first();
        $accNew->forceFill(['parent_id' => $parent->id])->save();
        $before = $parent->fresh()->balance;
        $land = Account::where('company_id', $cid)->where('name', 'Tanah')->first();
        $this->assertEquals(-250000, $accNew->fresh()->display_balance);
        $this->assertTrue($accNew->fresh()->is_contra);
        $this->assertEquals($before, (float) $parent->fresh()->balance);
        $this->assertEquals(-250000, $before - 0); // hanya ada akun lawan di bawah Aset Tetap pada data uji
    }

    public function test_settlement_flow_multi_invoice_receipt_partial_void_and_filters(): void
    {
        $cid = $this->user->company_id;
        $today = date('Y-m-d');
        $this->post('/client/accounts/beginning-balance', ['date' => $today, 'balances' => [$this->cash->id => 100000000]]);

        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-9',
            'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertSessionMissing('error');
        foreach ([2, 3] as $q) {
            $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d', strtotime('-10 days')), 'due_date' => date('Y-m-d', strtotime('-3 days')),
                'products' => [$this->product->id], 'quantities' => [$q], 'prices' => [15000], 'discounts' => [0]])->assertSessionMissing('error');
        }
        [$a, $b] = SalesInvoice::orderBy('id')->get()->all();
        $this->assertEquals([30000, 45000], [(float) $a->total, (float) $b->total]);

        // halaman render
        $this->get('/client/sales/receipts')->assertOk()->assertSee('Terima Pembayaran');
        $this->get('/client/sales/settlement')->assertOk()->assertSee($a->invoice_number)->assertSee('Terlambat');
        $this->get('/client/purchases/payments')->assertOk();
        $this->get('/client/purchases/settlement?tab=all')->assertOk();
        $this->get('/client/sales-invoices')->assertOk()->assertSee('Sisa');

        // satu penerimaan untuk dua faktur: A lunas, B dicicil
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today,
            'alloc' => [$a->id => 30000, $b->id => 20000]])->assertSessionMissing('error')->assertSessionHas('success');
        $this->assertEquals('paid', $a->fresh()->status);
        $this->assertEquals('partial', $b->fresh()->status);
        $this->assertEquals(25000, (float) $b->fresh()->amount_due);
        $rc = \App\Models\InvoicePayment::where('sales_invoice_id', $a->id)->firstOrFail();
        $this->assertEquals(2, \App\Models\InvoicePayment::where('journal_id', $rc->journal_id)->count());
        $this->assertBooksBalanced();

        // filter lunas / belum lunas
        $this->get('/client/sales/settlement?tab=paid')->assertSee($a->invoice_number)->assertDontSee($b->invoice_number);
        $this->get('/client/sales/settlement?tab=unpaid')->assertSee($b->invoice_number)->assertDontSee($a->invoice_number);

        // kelebihan bayar & faktur milik pelanggan lain ditolak
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$b->id => 30000]])->assertSessionHas('error');
        $other = \App\Models\Customer::create(['company_id' => $cid, 'name' => 'Lain']);
        $this->post('/client/sales/receipts', ['party_id' => $other->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$b->id => 1000]])->assertSessionHas('error');

        // batalkan penerimaan: kedua faktur kembali seperti semula
        $this->post('/client/invoice-payments/' . $rc->id . '/void')->assertSessionHas('success');
        $this->assertEquals('unpaid', $a->fresh()->status);
        $this->assertEquals(30000, (float) $a->fresh()->amount_due);
        $this->assertEquals('unpaid', $b->fresh()->status);
        $this->assertEquals(45000, (float) $b->fresh()->amount_due);
        $this->post('/client/invoice-payments/' . $rc->id . '/void')->assertSessionHas('error'); // tidak bisa dua kali
        $this->assertBooksBalanced();
        // penerimaan yang dibatalkan: jurnal asli + jurnal balik, saldo bersih nol; nomor tidak dipakai ulang
        $this->assertSame(1, \App\Models\Journal::where('ref_number', 'like', 'RC-%')->count());
        $this->assertSame(1, \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->count());
        $this->assertNetZeroPerAccount($rc->journal_id, \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->first()->id);
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today,
            'alloc' => [$a->id => 1000]])->assertSessionHas('success');
        $this->assertNotSame($rc->payment_number, \App\Models\InvoicePayment::orderByDesc('id')->first()->payment_number);
        // jurnal otomatis milik faktur tidak boleh dihapus langsung dari Jurnal Umum
        $this->delete('/client/journals/' . $a->fresh()->journal_id)->assertSessionHas('error');
        $this->assertNotNull(\App\Models\Journal::find($a->fresh()->journal_id));

        // retur lalu bayar sisanya: retur tidak boleh "hidup lagi"
        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $b->id, 'return_number' => 'SR-9',
            'return_date' => $today, 'products' => [$this->product->id], 'quantities' => [1], 'prices' => [15000]])->assertSessionHas('success');
        $this->assertEquals(30000, (float) $b->fresh()->amount_due);
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$b->id => 10000]])->assertSessionHas('success');
        $this->assertEquals(20000, (float) $b->fresh()->amount_due);
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$b->id => 20000]])->assertSessionHas('success');
        $this->assertEquals('paid', $b->fresh()->status);

        // jurnal kas bebas ke akun Piutang ditolak (harus lewat menu penerimaan)
        $ar = \App\Services\AccountResolver::receivable($cid);
        $this->post('/client/cash-bank/receipts', ['receive_to' => $this->cash->id, 'transaction_date' => $today, 'description' => 'x',
            'income_accounts' => [$ar->id], 'amounts' => [1000]])->assertSessionHas('error');

        // pembelian: bayar faktur pemasok
        $pi = PurchaseInvoice::firstOrFail();
        $this->post('/client/purchases/payments', ['party_id' => $this->supplier->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$pi->id => 40000]])->assertSessionHas('success');
        $this->assertEquals('partial', $pi->fresh()->status);
        $this->get('/client/purchases/settlement?tab=unpaid')->assertSee($pi->invoice_number);
        $this->post('/client/purchases/payments', ['party_id' => $this->supplier->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$pi->id => 60000]])->assertSessionHas('success');
        $this->assertEquals('paid', $pi->fresh()->status);
        $this->assertBooksBalanced();
    }

    public function test_purging_a_company_removes_all_its_data_without_touching_others(): void
    {
        $cid = $this->user->company_id;
        $today = date('Y-m-d');
        $this->post('/client/accounts/beginning-balance', ['date' => $today, 'balances' => [$this->cash->id => 1000000]]);
        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]]);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [2], 'prices' => [15000], 'discounts' => [0]]);
        $si = SalesInvoice::firstOrFail();
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$si->id => 10000]]);

        $other = \App\Models\Company::create(['name' => 'Lain', 'is_active' => true]);
        \App\Models\Customer::create(['company_id' => $other->id, 'name' => 'Aman']);

        \App\Services\CompanyPurger::purge((int) $cid);

        $this->assertNull(\App\Models\Company::find($cid));
        foreach (\Illuminate\Support\Facades\Schema::getTableListing() as $t) {
            $t = preg_replace('/^.*\./', '', $t);
            if ($t !== 'companies' && \Illuminate\Support\Facades\Schema::hasColumn($t, 'company_id')) {
                $this->assertEquals(0, \Illuminate\Support\Facades\DB::table($t)->where('company_id', $cid)->count(), "$t masih berisi data");
            }
        }
        $this->assertEquals(0, \Illuminate\Support\Facades\DB::table('journal_details')->count());
        $this->assertEquals(1, \Illuminate\Support\Facades\DB::table('customers')->where('company_id', $other->id)->count());
    }

    /** Menelusuri semua halaman dan tautan (sidebar, detail dokumen, dokumen terkait, jurnal) dan memastikan tidak ada yang 404/500. */
    public function test_every_page_and_link_reachable_from_a_populated_company(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // penelusuran memukul ratusan halaman sekaligus
        $today = date('Y-m-d');
        $this->post('/client/accounts/beginning-balance', ['date' => $today, 'balances' => [$this->cash->id => 5000000]]);
        $po = $this->createPurchaseAndReceive(20);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id], 'quantities' => [20], 'prices' => [10000], 'discounts' => [0]]);
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-T-1', 'order_date' => $today,
            'products' => [$this->product->id], 'quantities' => [5], 'prices' => [15000], 'discounts' => [0]]);
        $so = SalesOrder::firstOrFail();
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-T-1',
            'delivery_date' => $today, 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [5]]);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [5], 'prices' => [15000], 'discounts' => [0]]);
        $si = SalesInvoice::firstOrFail();
        $this->post('/client/sales/receipts', ['party_id' => $this->customer->id, 'account_id' => $this->cash->id, 'payment_date' => $today, 'alloc' => [$si->id => 20000]]);
        $this->post('/client/sales/returns', ['customer_id' => $this->customer->id, 'sales_invoice_id' => $si->id, 'return_number' => 'SR-1',
            'return_date' => $today, 'products' => [$this->product->id], 'quantities' => [1], 'prices' => [15000]]);
        $this->post('/client/sales/down-payments', ['customer_id' => $this->customer->id, 'payment_number' => 'DP-1', 'payment_date' => $today, 'amount' => 1000, 'account_id' => $this->cash->id]);

        $skip = '#^/(logout|client/preferences/logo|storage)#';
        $queue = ['/client/dashboard'];
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $r) {
            if (in_array('GET', $r->methods()) && str_starts_with($r->uri(), 'client/') && !str_contains($r->uri(), '{')) {
                $queue[] = '/' . $r->uri();
            }
        }
        $seen = [];
        $bad = [];
        while ($queue) {
            $url = array_shift($queue);
            $key = preg_replace('/\?.*/', '', $url);
            if (isset($seen[$url]) || preg_match($skip, $url) || count($seen) > 600) {
                continue;
            }
            $seen[$url] = true;
            $res = $this->get($url);
            $code = $res->getStatusCode(); if ($code >= 500) { $bad[] = "ERR " . substr(strip_tags((string) ($res->exception ?? "")), 0, 300) . (isset($res->exception) ? $res->exception->getMessage() : ""); }
            if ($code >= 400) {
                $bad[] = "$code $url";
                continue;
            }
            if ($code === 200 && str_contains((string) $res->headers->get('Content-Type'), 'html')) {
                preg_match_all('/href="([^"#]+)"/', $res->getContent(), $m);
                foreach ($m[1] as $h) {
                    $h = html_entity_decode($h);
                    $path = parse_url($h, PHP_URL_PATH);
                    $host = parse_url($h, PHP_URL_HOST);
                    if (($host && $host !== 'localhost') || !$path || !str_starts_with($path, '/client')) {
                        continue;
                    }
                    $queue[] = $path . (($q = parse_url($h, PHP_URL_QUERY)) ? '?' . $q : '');
                }
            }
        }
        $this->assertSame([], $bad, "Tautan bermasalah:\n" . implode("\n", $bad));
        $this->assertGreaterThan(40, count($seen));

        // Super admin: daftar client menampilkan email pendaftar, lalu hapus client lewat tombol hapus
        $company = $this->user->company;
        $admin = User::create(['company_id' => null, 'name' => 'Super', 'email' => 'super@example.com', 'password' => bcrypt('password'), 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin);
        $this->get('/superadmin/dashboard')->assertOk();
        $this->get('/superadmin/default-accounts')->assertOk();
        $this->get('/superadmin/clients')->assertOk()->assertSee('uji@example.com');
        $this->delete('/superadmin/clients/' . $company->id)->assertSessionHas('success');
        $this->assertNull(Company::find($company->id));
    }

    public function test_document_numbers_are_unique_per_company_and_ignore_document_date_month(): void
    {
        $cid = $this->user->company_id;
        $other = Company::create(['name' => 'Perusahaan B', 'is_active' => true]);
        $ym = date('Ym');
        $row = fn ($company, $supplier, $wh, $no, $date) => ['company_id' => $company, 'supplier_id' => $supplier, 'warehouse_id' => $wh,
            'receipt_number' => $no, 'receipt_date' => $date, 'status' => 'received', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()];
        $supB = Supplier::create(['company_id' => $other->id, 'name' => 'PB']);
        $whB = Warehouse::create(['company_id' => $other->id, 'code' => 'W', 'name' => 'G', 'is_active' => true]);

        // nomor yang sama di dua perusahaan diperbolehkan
        \Illuminate\Support\Facades\DB::table('item_receipts')->insert($row($other->id, $supB->id, $whB->id, "IR-$ym-0001", date('Y-m-d')));
        \Illuminate\Support\Facades\DB::table('item_receipts')->insert($row($cid, $this->supplier->id, $this->warehouse->id, "IR-$ym-0001", date('Y-m-d', strtotime('-2 months'))));

        // dokumen bertanggal bulan lain tidak membuat nomor berikutnya mundur ke 0001
        $this->assertSame("IR-$ym-0002", \App\Services\DocumentNumber::next(\App\Models\ItemReceipt::class, 'receipt_number', 'IR-', (int) $cid));
        $this->assertSame("IR-$ym-0002", \App\Services\DocumentNumber::next(\App\Models\ItemReceipt::class, 'receipt_number', 'IR-', (int) $other->id));

        // nomor manual yang sudah dipakai ditolak dengan pesan validasi, bukan error SQL
        $this->post('/client/purchases/receipts', ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'receipt_number' => "IR-$ym-0001",
            'receipt_date' => date('Y-m-d'), 'status' => 'received', 'products' => [$this->product->id], 'quantities' => [1]])->assertSessionHasErrors('receipt_number');
    }

    public function test_service_and_goods_forms_save_with_correct_defaults_and_no_cogs_for_service(): void
    {
        $cid = $this->user->company_id;
        $today = date('Y-m-d');
        $this->get('/client/inventory')->assertOk()->assertSee('Tambah Barang');
        $this->get('/client/inventory/services')->assertOk()->assertSee('Tambah Jasa');
        $this->get('/client/inventory/create')->assertOk()->assertSee('Harga Beli');
        $this->get('/client/inventory/services/create')->assertOk()->assertSee('Biaya Subkontraktor')->assertDontSee('Akun HPP');

        // Jasa murni: satuan kosong tidak membuat error; tanpa HPP; akun pendapatan jasa otomatis
        $this->post('/client/inventory', ['type' => 'service', 'sku' => 'JSA-0001', 'name' => 'Jasa Servis', 'unit' => '', 'sell_price' => 250000, 'buy_price' => 99999,
            'expense_account_id' => '', 'revenue_account_id' => ''])->assertSessionHasNoErrors()->assertRedirect(route('client.inventory.services'));
        $svc = Product::where('sku', 'JSA-0001')->firstOrFail();
        $this->assertSame('service', $svc->type);
        $this->assertSame('kali', $svc->unit);
        $this->assertEquals(0, (float) $svc->buy_price);
        $this->assertNull($svc->expense_account_id);
        $this->assertSame('Pendapatan Jasa', $svc->revenueAccount->name);

        // Jasa dengan subkontraktor: biaya subkontrak tercatat dan akun HPP otomatis tersedia
        $this->post('/client/inventory', ['type' => 'service', 'sku' => 'JSA-0002', 'name' => 'Jasa Pasang', 'sell_price' => 500000, 'is_subcontract' => 1, 'buy_price' => 300000])->assertSessionHasNoErrors();
        $sub = Product::where('sku', 'JSA-0002')->firstOrFail();
        $this->assertEquals(300000, (float) $sub->buy_price);
        $this->assertStringContainsString('Subkontrak', $sub->expenseAccount->name);

        // Barang: akun pendapatan & HPP otomatis, satuan default pcs; kode ganda ditolak
        $this->post('/client/inventory', ['type' => 'goods', 'sku' => 'BRG-0009', 'name' => 'Barang Baru', 'sell_price' => 1000, 'buy_price' => 600])->assertSessionHasNoErrors();
        $g = Product::where('sku', 'BRG-0009')->firstOrFail();
        $this->assertSame('pcs', $g->unit);
        $this->assertSame('Pendapatan Penjualan', $g->revenueAccount->name);
        $this->assertStringContainsString('HPP', $g->expenseAccount->name);
        $this->post('/client/inventory', ['type' => 'goods', 'sku' => 'BRG-0009', 'name' => 'Lagi', 'sell_price' => 1])->assertSessionHasErrors('sku');

        // Ubah jasa -> matikan subkontraktor menghapus biaya
        $this->get("/client/inventory/{$sub->id}/edit")->assertOk();
        $this->put("/client/inventory/{$sub->id}", ['sku' => 'JSA-0002', 'name' => 'Jasa Pasang', 'sell_price' => 500000, 'is_subcontract' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertNull($sub->fresh()->expense_account_id);

        // Faktur jasa: pendapatan masuk Pendapatan Jasa walau produk tanpa akun, tidak ada HPP/stok, jurnal seimbang
        $svc->forceFill(['revenue_account_id' => null])->save();
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$svc->id], 'quantities' => [2], 'prices' => [250000], 'discounts' => [0]])->assertSessionMissing('error');
        $inv = SalesInvoice::firstOrFail();
        $this->assertEquals(500000, (float) $inv->total);
        $names = $inv->journal->details->map(fn ($d) => $d->account->name)->implode(',');
        $this->assertStringContainsString('Pendapatan Jasa', $names);
        $this->assertStringNotContainsString('HPP', $names);
        $this->assertBooksBalanced();
    }

    /** Pengguna perusahaan A tidak boleh bisa membuka/mengubah data perusahaan B lewat ID di URL (IDOR). */
    public function test_tenant_isolation_other_company_records_are_not_reachable(): void
    {
        $other = Company::create(['name' => 'PT Lain', 'email' => 'lain@example.com', 'fiscal_year_start' => 1, 'is_active' => true]);
        CoaSeeder::seed($other->id);
        $theirCustomer = Customer::create(['company_id' => $other->id, 'name' => 'Rahasia', 'default_term_days' => 30, 'is_active' => true]);
        $theirSupplier = Supplier::create(['company_id' => $other->id, 'name' => 'Rahasia S', 'default_term_days' => 30, 'is_active' => true]);
        $theirProduct = Product::create(['company_id' => $other->id, 'name' => 'Barang Lain', 'type' => 'goods', 'unit' => 'pcs', 'sell_price' => 1, 'buy_price' => 1, 'is_active' => true]);
        $theirAccount = Account::withoutGlobalScopes()->where('company_id', $other->id)->first();

        foreach ([
            "/client/customers/{$theirCustomer->id}", "/client/customers/{$theirCustomer->id}/edit",
            "/client/suppliers/{$theirSupplier->id}", "/client/suppliers/{$theirSupplier->id}/edit",
            "/client/inventory/{$theirProduct->id}", "/client/inventory/{$theirProduct->id}/edit",
            "/client/accounts/{$theirAccount->id}", "/client/accounts/{$theirAccount->id}/edit",
        ] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->put("/client/customers/{$theirCustomer->id}", ['name' => 'Diretas'])->assertNotFound();
        $this->delete("/client/customers/{$theirCustomer->id}")->assertNotFound();
        $this->assertSame('Rahasia', \Illuminate\Support\Facades\DB::table('customers')->where('id', $theirCustomer->id)->value('name'));

        // daftar milik sendiri tidak memuat data perusahaan lain
        $this->get('/client/customers')->assertOk()->assertDontSee('Rahasia');
    }

    public function test_security_hardening_headers_update_db_xss_and_inactive_login(): void
    {
        // header keamanan
        $r = $this->get('/client/dashboard');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');

        // /update-db: tamu & klien ditolak
        $this->get('/update-db')->assertStatus(403);
        auth()->logout();
        $this->get('/update-db')->assertRedirect('/login');

        // pesan error di-escape (tidak dirender sebagai HTML)
        $this->actingAs($this->user);
        $this->withSession(['error' => '<script>alert(1)</script>'])->get('/client/dashboard')
            ->assertDontSee('<script>alert(1)</script>', false);

        // akun nonaktif tidak bisa login
        auth()->logout();
        $this->user->forceFill(['is_active' => false])->save();
        $this->post('/login', ['email' => 'a@example.com', 'password' => 'password']);
        $this->assertGuest();
    }

    public function test_logo_upload_cannot_smuggle_php_extension(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('evil.php', $png);
        $this->post('/client/settings/preferences', ['company_name' => 'PT Uji', 'logo' => $file]);
        $files = glob(public_path('uploads/logos/company-*')) ?: [];
        foreach ($files as $f) {
            $this->assertStringEndsNotWith('.php', $f);
            @unlink($f);
        }
        $this->assertSame([], glob(public_path('uploads/logos/*.php')) ?: []);
    }

    public function test_sales_and_purchase_hubs_and_combined_product_list_render(): void
    {
        $this->get('/client/sales')->assertOk()->assertSee('Pusat Penjualan')->assertSee('Alur Penjualan')->assertSee('Terima Pembayaran');
        $this->get('/client/purchases')->assertOk()->assertSee('Pusat Pembelian')->assertSee('Catat Pembayaran');
        $this->get('/client/sales-invoices/create')->assertOk()->assertSee('Tarik dari Pengiriman (DO)');
        $this->get('/client/purchase-invoices/create')->assertOk()->assertSee('Tarik dari Penerimaan Barang');
        Product::create(['company_id' => $this->user->company_id, 'name' => 'Konsultasi Unik', 'type' => 'service', 'unit' => 'kali', 'sku' => 'JS-9', 'sell_price' => 500000, 'buy_price' => 0, 'is_active' => true]);
        Product::create(['company_id' => $this->user->company_id, 'name' => 'Widget Unik', 'type' => 'goods', 'unit' => 'pcs', 'sku' => 'BR-9', 'sell_price' => 1000, 'buy_price' => 500, 'is_active' => true]);
        $this->get('/client/inventory/all')->assertOk()->assertSee('Konsultasi Unik')->assertSee('Widget Unik');
        $this->get('/client/inventory/all?search=Widget')->assertOk()->assertSee('Widget Unik')->assertDontSee('Konsultasi Unik');
    }

    public function test_invoice_can_pull_open_sales_order_and_receipt_lists_customer(): void
    {
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-PULL-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [3], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        $so = SalesOrder::where('order_number', 'SO-PULL-1')->first();

        // pilihan tarik SO muncul bersama nama pelanggan, dan pelanggan + barang terisi saat dipilih
        $this->get('/client/sales-invoices/create')->assertOk()->assertSee('SO-PULL-1 — Pelanggan');
        $r = $this->get('/client/sales-invoices/create?sales_order_id[]=' . $so->id)->assertOk();
        $r->assertSee('"order_id":' . $so->id, false);
        $this->get('/client/sales/deliveries/create?sales_order_id=' . $so->id)->assertOk()->assertSee('SO-PULL-1 — Pelanggan');
    }

    public function test_invoices_can_be_pulled_from_delivery_and_item_receipt(): void
    {
        $po = $this->createPurchaseAndReceive(10);
        $receipt = \App\Models\ItemReceipt::firstOrFail();
        $r = $this->get('/client/purchase-invoices/create?item_receipt_id=' . $receipt->id)->assertOk();
        $r->assertSee('"order_id":' . $po->id, false)->assertSee($receipt->receipt_number . ' — Pemasok')
          ->assertSee('"quantity":10', false);

        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-PULL-2', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [4], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        $so = SalesOrder::where('order_number', 'SO-PULL-2')->first();
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-PULL-2',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [3]])->assertRedirect();
        $do = DeliveryOrder::where('delivery_number', 'DO-PULL-2')->first();

        // hanya 3 yang dikirim -> faktur menarik 3 (bukan 4 yang dipesan), harga dari SO
        $this->get('/client/sales-invoices/create?delivery_order_id=' . $do->id)->assertOk()
            ->assertSee('"order_id":' . $so->id, false)
            ->assertSee('"quantity":3', false)->assertSee('"price":15000', false)->assertSee('DO-PULL-2 — Pelanggan');
    }

    public function test_one_invoice_can_bill_several_sales_orders_and_one_payment_settles_several_invoices(): void
    {
        $this->createPurchaseAndReceive(20);
        foreach ([['SO-M-A', 3], ['SO-M-B', 2]] as [$no, $q]) {
            $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => $no, 'order_date' => date('Y-m-d'),
                'products' => [$this->product->id], 'quantities' => [$q], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        }
        $a = SalesOrder::where('order_number', 'SO-M-A')->first();
        $b = SalesOrder::where('order_number', 'SO-M-B')->first();

        // menarik dua SO sekaligus -> dua baris, masing-masing membawa pesanan asalnya
        $this->get('/client/sales-invoices/create?sales_order_id[]=' . $a->id . '&sales_order_id[]=' . $b->id)->assertOk()
            ->assertSee('"order_id":' . $a->id, false)->assertSee('"order_id":' . $b->id, false);

        $base = ['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id, $this->product->id], 'prices' => [15000, 15000], 'discounts' => [0, 0]];

        // melebihi sisa SO-A ditolak
        $this->post('/client/sales-invoices', $base + ['quantities' => [3, 2], 'row_orders' => [$a->id, $a->id]])->assertSessionHas('error');
        $this->assertSame(0, SalesInvoice::count());

        // pesanan milik pelanggan lain ditolak
        $other = Customer::create(['company_id' => $this->user->company_id, 'name' => 'Pelanggan Lain', 'default_term_days' => 30, 'is_active' => true]);
        $this->post('/client/sales-invoices', array_merge($base, ['customer_id' => $other->id, 'quantities' => [3, 2], 'row_orders' => [$a->id, $b->id]]))->assertSessionHas('error');
        $this->assertSame(0, SalesInvoice::count());

        // satu faktur untuk dua SO
        $this->post('/client/sales-invoices', $base + ['quantities' => [3, 2], 'row_orders' => [$a->id, $b->id]])->assertRedirect();
        $inv = SalesInvoice::firstOrFail();
        $this->assertEquals(75000, $inv->total);
        $this->assertSame([$a->id, $b->id], $inv->items()->orderBy('id')->pluck('sales_order_id')->map(fn ($x) => (int) $x)->all());
        $this->assertEquals(3, \App\Services\OrderProgress::forSalesOrder($a)[0]['invoiced']);
        $this->assertEquals(2, \App\Services\OrderProgress::forSalesOrder($b)[0]['invoiced']);
        // kedua SO tampil sebagai dokumen terkait faktur, dan faktur tampil di tiap SO
        $this->get('/client/sales-invoices/' . $inv->id)->assertOk()->assertSee('SO-M-A')->assertSee('SO-M-B');
        $this->get('/client/sales/orders/' . $b->id)->assertOk()->assertSee($inv->invoice_number);

        // membatalkan faktur mengembalikan kuota kedua SO
        $this->post('/client/sales-invoices/' . $inv->id . '/void')->assertRedirect();
        $this->assertEquals(0, \App\Services\OrderProgress::forSalesOrder($a)[0]['invoiced']);
        $this->assertEquals(0, \App\Services\OrderProgress::forSalesOrder($b)[0]['invoiced']);
    }

    public function test_service_lines_need_a_price_and_services_are_not_offered_for_delivery(): void
    {
        $svc = Product::create(['company_id' => $this->user->company_id, 'name' => 'Konsultasi', 'type' => 'service', 'unit' => 'kali', 'sell_price' => 0, 'buy_price' => 0, 'is_active' => true]);
        // jasa tanpa nilai ditolak
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-SV-0', 'order_date' => date('Y-m-d'),
            'products' => [$svc->id], 'quantities' => [1], 'prices' => [0], 'discounts' => [0]])->assertSessionHas('error');
        $this->assertSame(0, SalesOrder::count());
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-SV-1', 'order_date' => date('Y-m-d'),
            'products' => [$svc->id], 'quantities' => [1], 'prices' => [2500000], 'discounts' => [0]])->assertRedirect();
        $so = SalesOrder::firstOrFail();

        // SO khusus jasa tidak muncul di pilihan pengiriman dan tidak ada tombol Buat Pengiriman
        $this->get('/client/sales/deliveries/create')->assertOk()->assertDontSee('SO-SV-1');
        $this->get('/client/sales/orders/' . $so->id)->assertOk()->assertDontSee('Buat Pengiriman')->assertSee('Buat Faktur');
    }

    public function test_orders_can_be_edited_deleted_or_cancelled_and_delivery_receipt_cancel_returns_stock(): void
    {
        // PO tanpa turunan: bisa diubah & dihapus
        $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-L-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [4], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();
        $po = PurchaseOrder::firstOrFail();
        $this->get('/client/purchases/orders/' . $po->id . '/edit')->assertOk()->assertSee('PO-L-1');
        $this->put('/client/purchases/orders/' . $po->id, ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-L-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [6], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();
        $this->assertEquals(60000, $po->fresh()->total);
        $this->delete('/client/purchases/orders/' . $po->id)->assertRedirect();
        $this->assertSame(0, PurchaseOrder::count());

        // PO dengan penerimaan: tidak bisa dihapus/diubah, hanya dibatalkan
        $po = $this->createPurchaseAndReceive(10);
        $this->delete('/client/purchases/orders/' . $po->id)->assertSessionHas('error');
        $this->get('/client/purchases/orders/' . $po->id . '/edit')->assertSessionHas('error');
        $this->assertSame(1, PurchaseOrder::count());
        $receipt = \App\Models\ItemReceipt::firstOrFail();
        $this->assertEquals(10, $this->product->fresh()->stock);

        // SO + pengiriman
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-L-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [4], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        $so = SalesOrder::firstOrFail();
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-L-1',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [4]])->assertRedirect();
        $do = DeliveryOrder::firstOrFail();
        $this->assertEquals(6, $this->product->fresh()->stock);
        $this->delete('/client/sales/orders/' . $so->id)->assertSessionHas('error');

        // penerimaan tidak bisa dibatalkan bila stoknya sudah dipakai (stok 6 < 10)
        $this->post('/client/purchases/receipts/' . $receipt->id . '/cancel')->assertSessionHas('error');
        $this->assertSame('received', $receipt->fresh()->status);

        // batalkan pengiriman -> stok kembali
        $this->post('/client/sales/deliveries/' . $do->id . '/cancel')->assertRedirect();
        $this->assertSame('cancelled', $do->fresh()->status);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->get('/client/sales/deliveries/' . $do->id)->assertOk()->assertSee('Dibatalkan');

        // SO tanpa pengiriman aktif lagi bisa dihapus; kalau dibatalkan tidak bisa dikirim
        $this->post('/client/sales/orders/' . $so->id . '/cancel')->assertRedirect();
        $this->assertSame('cancelled', $so->fresh()->status);
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'sales_order_id' => $so->id, 'delivery_number' => 'DO-L-2',
            'delivery_date' => date('Y-m-d'), 'status' => 'shipped', 'products' => [$this->product->id], 'quantities' => [1]])->assertSessionHas('error');
        $this->get('/client/sales/orders/' . $so->id)->assertOk()->assertSee('Dibatalkan');

        // penerimaan bisa dibatalkan setelah stok bebas
        $this->post('/client/purchases/receipts/' . $receipt->id . '/cancel')->assertRedirect();
        $this->assertEquals(0, $this->product->fresh()->stock);
        $this->post('/client/purchases/orders/' . $po->id . '/cancel')->assertRedirect();
        $this->assertSame('cancelled', $po->fresh()->status);
        $this->get('/client/purchases/orders/' . $po->id)->assertOk();
    }

    public function test_settlement_can_be_split_to_deduction_accounts_for_sales_and_purchases(): void
    {
        $cid = $this->user->company_id;
        $prepaid = Account::where('company_id', $cid)->where('name', 'Pajak Dibayar Dimuka')->firstOrFail();
        $pph23 = Account::where('company_id', $cid)->where('name', 'Hutang PPh 23')->firstOrFail();
        $pph25 = Account::where('company_id', $cid)->where('name', 'Hutang PPh 25')->firstOrFail();
        $this->assertTrue((bool) $prepaid->is_settlement_deduction);
        $this->assertTrue((bool) $pph23->is_settlement_deduction);
        $this->assertFalse((bool) $pph25->is_settlement_deduction);
        $this->assertFalse((bool) $this->cash->fresh()->is_settlement_deduction);

        // ---- penjualan: pelanggan memotong PPh 2% dari 1.000.000
        $this->createPurchaseAndReceive(10);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [2], 'prices' => [500000], 'discounts' => [0]])->assertRedirect();
        $inv = SalesInvoice::firstOrFail();
        $this->assertEquals(1000000, $inv->total);

        // potongan lebih besar dari jumlah, atau akun yang tidak ditandai, ditolak
        $base = ['account_id' => $this->cash->id, 'amount' => 1000000, 'payment_date' => date('Y-m-d')];
        $this->post("/client/sales-invoices/{$inv->id}/payments", $base + ['deductions' => [['account_id' => $prepaid->id, 'amount' => 1500000]]])->assertSessionHas('error');
        $this->post("/client/sales-invoices/{$inv->id}/payments", $base + ['deductions' => [['account_id' => $pph25->id, 'amount' => 20000]]])->assertSessionHas('error');
        $this->post("/client/sales-invoices/{$inv->id}/payments", $base + ['deductions' => [['account_id' => $this->cash->id, 'amount' => 20000]]])->assertSessionHas('error');
        $this->assertSame('unpaid', $inv->fresh()->status);

        $this->post("/client/sales-invoices/{$inv->id}/payments", $base + ['deductions' => [['account_id' => $prepaid->id, 'amount' => 20000], ['account_id' => '', 'amount' => '']]])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame('paid', $inv->status); // faktur lunas penuh walau kas hanya 980.000
        $pay = \App\Models\InvoicePayment::firstOrFail();
        $this->assertEquals(1000000, $pay->amount);
        $this->assertEquals(20000, $pay->deduction_amount);
        $d = fn ($accId) => \App\Models\JournalDetail::where('journal_id', $pay->journal_id)->where('account_id', $accId);
        $this->assertEquals(980000, $d($this->cash->id)->sum('debit'));
        $this->assertEquals(20000, $d($prepaid->id)->sum('debit'));
        $this->assertEquals(1000000, \App\Models\JournalDetail::where('journal_id', $pay->journal_id)->sum('credit'));
        $this->assertBooksBalanced();
        $this->get("/client/sales-invoices/{$inv->id}")->assertOk()->assertSee('potongan Rp 20.000');
        $this->get('/client/sales/receipts')->assertOk()->assertSee('Potongan / split pelunasan');

        // batalkan -> jurnal balik termasuk akun potongan
        $this->post('/client/invoice-payments/' . $pay->id . '/void')->assertRedirect();
        $this->assertSame('unpaid', $inv->fresh()->status);
        $this->assertEquals(0, $prepaid->fresh()->balance);
        $this->assertBooksBalanced();

        // ---- pembelian: pemasok ditahan PPh 23, dibayar lewat halaman multi-faktur (dua faktur, potongan dibagi proporsional)
        foreach (['PI-S-1' => 600000, 'PI-S-2' => 400000] as $no => $amt) {
            $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_number' => $no, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
                'products' => [$this->product->id], 'quantities' => [1], 'prices' => [$amt], 'discounts' => [0]])->assertRedirect();
        }
        $invs = PurchaseInvoice::orderBy('id')->get();
        $this->post('/client/purchases/payments', ['party_id' => $this->supplier->id, 'account_id' => $this->cash->id, 'payment_date' => date('Y-m-d'),
            'alloc' => [$invs[0]->id => 600000, $invs[1]->id => 400000],
            'deductions' => [['account_id' => $pph23->id, 'amount' => 20000]]])->assertSessionHasNoErrors();
        $pays = \App\Models\InvoicePayment::whereNotNull('purchase_invoice_id')->orderBy('id')->get();
        $this->assertEquals([12000, 8000], $pays->pluck('deduction_amount')->map(fn ($x) => (float) $x)->all());
        $j = $pays->first()->journal_id;
        $this->assertEquals(980000, \App\Models\JournalDetail::where('journal_id', $j)->where('account_id', $this->cash->id)->sum('credit'));
        $this->assertEquals(20000, \App\Models\JournalDetail::where('journal_id', $j)->where('account_id', $pph23->id)->sum('credit'));
        $this->assertEquals(1000000, \App\Models\JournalDetail::where('journal_id', $j)->sum('debit'));
        $this->assertSame(['paid', 'paid'], PurchaseInvoice::orderBy('id')->pluck('status')->all());
        $this->assertBooksBalanced();
        $this->get('/client/purchases/payments')->assertOk()->assertSee('termasuk potongan');
    }

    public function test_purchase_invoice_can_record_expense_straight_to_an_account_without_product(): void
    {
        $cid = $this->user->company_id;
        $exp = Account::where('company_id', $cid)->where('type', 'expense')->get()->first(fn ($a) => !$a->is_header);
        $this->get('/client/purchase-invoices/create?mode=biaya')->assertOk()->assertSee($exp->name);
        $this->get('/client/purchase-invoices/create')->assertOk()->assertSee($exp->name);

        $base = ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'quantities' => [1], 'prices' => [350000.5], 'discounts' => [0]];
        // akun kas / akun milik perusahaan lain / baris kosong ditolak
        $this->post('/client/purchase-invoices', $base + ['products' => ['a' . $this->cash->id]])->assertSessionHas('error');
        $this->post('/client/purchase-invoices', $base + ['products' => ['']])->assertSessionHas('error');
        $this->assertSame(0, PurchaseInvoice::count());

        $this->post('/client/purchase-invoices', $base + ['products' => ['a' . $exp->id], 'row_descriptions' => ['Listrik Oktober']])->assertRedirect();
        $inv = PurchaseInvoice::firstOrFail();
        $this->assertEquals(350000.5, $inv->total);
        $item = $inv->items()->firstOrFail();
        $this->assertNull($item->product_id);
        $this->assertSame($exp->id, (int) $item->account_id);
        $d = \App\Models\JournalDetail::where('journal_id', $inv->journal_id)->where('account_id', $exp->id)->first();
        $this->assertEquals(350000.5, $d->debit);
        $this->assertBooksBalanced();
        $this->get('/client/purchase-invoices/' . $inv->id)->assertOk()->assertSee($exp->name)->assertSee('Listrik Oktober');

        // campuran produk + akun dalam satu faktur
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [(string) $this->product->id, 'a' . $exp->id], 'quantities' => [2, 1], 'prices' => [10000, 5000], 'discounts' => [0, 0]])->assertRedirect();
        $this->assertSame(2, PurchaseInvoice::count());
        $this->assertBooksBalanced();
    }

    public function test_money_is_displayed_with_two_decimals_and_form_amounts_accept_indonesian_format(): void
    {
        $this->createPurchaseAndReceive(10);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [1], 'prices' => [2000000.13], 'discounts' => [0]])->assertRedirect();
        $inv = SalesInvoice::firstOrFail();
        $this->get('/client/sales-invoices')->assertOk()->assertSee('2.000.000,13');
        $this->get('/client/sales-invoices/' . $inv->id)->assertOk()->assertSee('2.000.000,13');
        $this->get('/client/sales/receipts')->assertOk()->assertSee('2.000.000,13')->assertDontSee('Rp 2.000.000<');

        // angka berformat Indonesia dari form dipahami server
        $prepaid = Account::where('company_id', $this->user->company_id)->where('name', 'Pajak Dibayar Dimuka')->firstOrFail();
        $this->post("/client/sales-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => '2.000.000,13', 'payment_date' => date('Y-m-d'),
            'deductions' => [['account_id' => $prepaid->id, 'amount' => '40.000,00']]])->assertSessionHasNoErrors();
        $inv->refresh();
        $this->assertSame('paid', $inv->status);
        $pay = \App\Models\InvoicePayment::firstOrFail();
        $this->assertEquals(2000000.13, $pay->amount);
        $this->assertEquals(40000, $pay->deduction_amount);
        $this->assertBooksBalanced();
    }

    public function test_post_only_url_opened_as_get_with_hosting_challenge_param_redirects_back_with_message(): void
    {
        $url = '/client/cash-bank/' . $this->cash->id . '/transaction?i=1';
        $this->get($url)->assertRedirect('http://localhost/client/cash-bank/' . $this->cash->id)->assertSessionHas('error');
        // tanpa parameter tantangan tetap 405 seperti biasa
        $this->get('/client/cash-bank/' . $this->cash->id . '/transaction')->assertStatus(405);
    }

    public function test_retained_and_current_year_earnings_link_to_profit_per_fiscal_year_and_prive_is_contra(): void
    {
        $cid = $this->user->company_id;
        $acc = fn ($name) => Account::where('company_id', $cid)->where('name', $name)->firstOrFail();
        $rev = $acc('Pendapatan Penjualan');
        $exp = Account::where('company_id', $cid)->where('type', 'expense')->get()->first(fn ($a) => !$a->is_header);
        $prive = $acc('Prive Pemilik');
        $cur = $acc('Current Year Earnings');
        $ret = $acc('Laba Ditahan');
        $this->assertSame('debit', $prive->normal_balance);

        $post = function (string $date, array $lines) use ($cid) {
            $j = \App\Models\Journal::create(['company_id' => $cid, 'ref_number' => 'T-' . uniqid(), 'type' => 'general', 'transaction_date' => $date, 'description' => 't', 'status' => 'posted', 'created_by' => $this->user->id]);
            foreach ($lines as [$a, $d, $c]) {
                \App\Models\JournalDetail::create(['journal_id' => $j->id, 'account_id' => $a->id, 'debit' => $d, 'credit' => $c, 'description' => 't']);
            }
        };
        $year = (int) date('Y');
        $post(($year - 1) . '-06-10', [[$this->cash, 1000, 0], [$rev, 0, 1000]]);   // laba tahun lalu 1000
        $post($year . '-01-15', [[$this->cash, 500, 0], [$rev, 0, 500]]);            // tahun ini
        $post($year . '-02-15', [[$exp, 200, 0], [$this->cash, 0, 200]]);            // beban tahun ini -> laba berjalan 300
        $post($year . '-03-01', [[$prive, 100, 0], [$this->cash, 0, 100]]);          // prive 100

        $this->assertEquals(300, $cur->fresh()->balance);
        $this->assertEquals(1000, $ret->fresh()->balance);
        $this->assertEquals(-100, $prive->fresh()->display_balance);
        $this->assertTrue($prive->fresh()->is_contra);
        $equity = $acc('Ekuitas');
        $this->assertEquals(1000 + 300 - 100, $equity->fresh()->balance);

        // Neraca: dua baris laba terhubung, seimbang, dan ekuitas = 1200
        $b = new \App\Services\Reports\ReportBuilder($cid);
        $doc = $b->balanceSheet(new \Illuminate\Http\Request(['as_of_date' => $year . '-12-31']));
        $text = json_encode($doc['rows']);
        $this->assertStringContainsString('Laba Ditahan', $text);
        $this->assertStringContainsString('Current Year Earnings', $text);
        $this->assertSame('good', $doc['notes_tone']);
        $this->assertEquals(1200, $doc['summary'][2]['value']);
        // tahun fiskal berikutnya: semua laba lama jadi Laba Ditahan, laba berjalan kosong
        $next = $b->balanceSheet(new \Illuminate\Http\Request(['as_of_date' => ($year + 1) . '-02-01']));
        $this->assertSame('good', $next['notes_tone']);
        $this->assertEquals(1200, $next['summary'][2]['value']);

        // Laporan terpadu: Neraca dulu, baru Laba Rugi
        $int = json_encode($b->integrated(new \Illuminate\Http\Request(['start_date' => $year . '-01-01', 'end_date' => $year . '-12-31']))['rows']);
        $this->assertLessThan(strpos($int, 'II. LAPORAN LABA RUGI'), strpos($int, 'I. NERACA'));
        $this->get('/client/reports/integrated')->assertOk();
    }

    public function test_cash_bank_transaction_can_split_across_several_contra_accounts(): void
    {
        $cid = $this->user->company_id;
        $exps = Account::where('company_id', $cid)->where('type', 'expense')->get()->filter(fn ($a) => !$a->is_header)->values();
        [$a1, $a2] = [$exps[0], $exps[1]];
        $base = ['transaction_type' => 'spend', 'transaction_date' => date('Y-m-d'), 'description' => 'Belanja kantor'];

        // split 3 akun lawan (angka berformat Indonesia), total keluar dari kas
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', $base + [
            'contra_account_id' => [$a1->id, $a2->id, $a1->id], 'amounts' => ['100.000,50', '49.999,50', '50'], 'project_job_id' => ['', '', ''],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $j = \App\Models\Journal::latest('id')->first();
        $this->assertEquals(150050, \App\Models\JournalDetail::where('journal_id', $j->id)->where('account_id', $this->cash->id)->sum('credit'));
        $this->assertEquals(3, \App\Models\JournalDetail::where('journal_id', $j->id)->where('debit', '>', 0)->count());
        $this->assertBooksBalanced();

        // terima uang dengan split ke dua akun
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', ['transaction_type' => 'receive'] + $base + [
            'contra_account_id' => [$a1->id, $a2->id], 'amounts' => [300, 200], 'project_job_id' => ['', ''],
        ])->assertSessionHasNoErrors();
        $j2 = \App\Models\Journal::latest('id')->first();
        $this->assertEquals(500, \App\Models\JournalDetail::where('journal_id', $j2->id)->where('account_id', $this->cash->id)->sum('debit'));
        $this->assertBooksBalanced();

        // kas sebagai akun lawan sendiri / nominal 0 ditolak; form lama (satu akun) tetap jalan
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', $base + ['contra_account_id' => [$this->cash->id], 'amounts' => [10]])->assertSessionHas('error');
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', $base + ['contra_account_id' => [$a1->id], 'amounts' => [0]])->assertSessionHasErrors();
        $n = \App\Models\Journal::count();
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', $base + ['contra_account_id' => $a1->id, 'amount' => 1000])->assertSessionHasNoErrors();
        $this->assertSame($n + 1, \App\Models\Journal::count());
        $this->get('/client/cash-bank/' . $this->cash->id)->assertOk()->assertSee('Tambah akun lawan');
    }

    public function test_riwayat_mutasi_bisa_difilter_per_tanggal_dengan_saldo_awal_periode(): void
    {
        $cid = $this->user->company_id;
        $other = Account::where('company_id', $cid)->where('type', 'revenue')->where('name', 'Pendapatan Penjualan')->first();
        $this->cash->forceFill(['opening_balance' => 1000])->save();
        $mk = function (string $date, float $debit, float $credit, string $ref) use ($cid) {
            $j = \App\Models\Journal::create(['company_id' => $cid, 'ref_number' => $ref, 'type' => 'general', 'transaction_date' => $date, 'description' => $ref, 'status' => 'posted', 'created_by' => $this->user->id]);
            \App\Models\JournalDetail::create(['journal_id' => $j->id, 'account_id' => $this->cash->id, 'debit' => $debit, 'credit' => $credit, 'description' => $ref]);
        };
        $mk('2026-08-10', 500, 0, 'REF-AGU');
        $mk('2026-09-05', 0, 200, 'REF-SEP');
        $mk('2026-09-20', 300, 0, 'REF-SEP2');
        $mk('2026-10-02', 100, 0, 'REF-OKT');

        foreach (['/client/cash-bank/', '/client/accounts/'] as $base) {
            $r = $this->get($base . $this->cash->id . '?from=2026-09-01&to=2026-09-30')->assertOk();
            $r->assertSee('REF-SEP')->assertSee('REF-SEP2')->assertDontSee('REF-AGU')->assertDontSee('REF-OKT');
            // saldo awal periode = 1000 + 500 = 1.500,00 ; akhir = 1500 - 200 + 300 = 1.600,00
            $r->assertSee('1.500,00')->assertSee('Saldo Awal Periode');
            $r->assertSee('1.600,00');

            $this->get($base . $this->cash->id . '?range=all')->assertOk()->assertSee('REF-AGU')->assertSee('REF-OKT');
            $this->get($base . $this->cash->id . '?from=2030-01-01&to=2030-01-31')->assertOk()->assertSee('Tidak ada mutasi pada periode ini');
        }
    }

    public function test_consistency_check_flags_orphans_and_passes_after_proper_cancellation(): void
    {
        $po = $this->createPurchaseAndReceive(10);
        $today = date('Y-m-d');
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-1',
            'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertSessionMissing('error');
        $inv = PurchaseInvoice::firstOrFail();
        $this->post('/client/purchases/payments', ['party_id' => $this->supplier->id, 'account_id' => $this->cash->id, 'payment_date' => $today,
            'alloc' => [$inv->id => 50000]])->assertSessionHas('success');
        $this->get('/client/consistency')->assertOk()->assertSee('Semua konsisten');

        // bayar lalu batalkan: tetap konsisten, saldo hutang kembali ke tagihan semula
        $pay = \App\Models\InvoicePayment::firstOrFail();
        $this->post('/client/invoice-payments/' . $pay->id . '/void')->assertSessionHas('success');
        $this->get('/client/consistency')->assertOk()->assertSee('Semua konsisten');
        $this->assertBooksBalanced();

        // jurnal faktur hilang (mis. dihapus manual) => terdeteksi
        \App\Models\JournalDetail::where('journal_id', $inv->journal_id)->delete();
        \App\Models\Journal::where('id', $inv->journal_id)->delete();
        $this->get('/client/consistency')->assertOk()->assertSee('tanpa jurnal')->assertSee($inv->invoice_number)->assertSee('Buat ulang jurnal');

        // buat ulang jurnal dari isi faktur: seimbang, hutang = total faktur, pemeriksaan kembali bersih
        $this->post('/client/consistency/rebuild-purchase/' . $inv->id)->assertSessionHas('success');
        $j = $inv->fresh()->journal;
        $this->assertNotNull($j);
        $this->assertEqualsWithDelta(100000, (float) $j->details->sum('credit'), 0.01);
        $this->assertEqualsWithDelta(100000, (float) $j->details->sum('debit'), 0.01);
        $this->assertSame($inv->invoice_number, $j->ref_number);
        $this->get('/client/consistency')->assertOk()->assertSee('Semua konsisten');
        $this->assertBooksBalanced();
        $this->post('/client/consistency/rebuild-purchase/' . $inv->id)->assertSessionHas('error'); // sudah punya jurnal
    }

    public function test_fixed_asset_acquisition_journal_and_cancel_depreciation(): void
    {
        $cid = $this->user->company_id;
        $assetAcc = \App\Services\AssetAccounting::assetAccounts($cid)->first();
        $this->assertNotNull($assetAcc);
        $accNew = Account::create(['company_id' => $cid, 'code' => '1-2010', 'name' => 'Akumulasi Penyusutan Peralatan', 'type' => 'asset', 'normal_balance' => 'credit']);
        $expNew = Account::create(['company_id' => $cid, 'code' => '6-2010', 'name' => 'Beban Penyusutan Peralatan', 'type' => 'expense', 'normal_balance' => 'debit']);

        $base = ['code' => 'FA-1', 'name' => 'Laptop', 'acquisition_date' => '2026-04-10', 'acquisition_cost' => 3000000, 'useful_life_months' => 12,
            'salvage_value' => 0, 'depreciation_method' => 'straight_line', 'condition' => 'baik'];

        // jurnal perolehan: Debit akun aset, Kredit kas
        $cashBefore = (float) $this->cash->fresh()->balance;
        $this->post('/client/fixed-assets', $base + ['record_journal' => 1, 'asset_account_id' => $assetAcc->id, 'funding_account_id' => $this->cash->id])->assertSessionHas('success');
        $asset = \App\Models\FixedAsset::firstOrFail();
        $this->assertNotNull($asset->acquisition_journal_id);
        $this->assertEquals(3000000, (float) $assetAcc->fresh()->balance);
        $this->assertEquals($cashBefore - 3000000, (float) $this->cash->fresh()->balance);
        $this->assertBooksBalanced();

        // mengubah harga ikut memperbarui jurnal; jurnal otomatis tidak bisa dihapus dari Jurnal Umum
        $this->put('/client/fixed-assets/' . $asset->id, array_merge($base, ['status' => 'aktif', 'acquisition_cost' => 3600000]))->assertSessionHasNoErrors();
        $this->assertEquals(3600000, (float) $assetAcc->fresh()->balance);
        $this->delete('/client/journals/' . $asset->acquisition_journal_id)->assertSessionHas('error');

        // tanpa centang jurnal (aset dari saldo awal) tidak membuat jurnal
        $n = \App\Models\Journal::count();
        $this->post('/client/fixed-assets', array_merge($base, ['code' => 'FA-2', 'name' => 'Meja']))->assertSessionHas('success');
        $this->assertSame($n, \App\Models\Journal::count());
        // centang tetapi akun kosong ditolak
        $this->post('/client/fixed-assets', array_merge($base, ['code' => 'FA-3', 'record_journal' => 1]))->assertSessionHasErrors('asset_account_id');

        // penyusutan April & Mei, lalu batalkan berurutan
        $run = fn ($m, $d) => $this->post('/client/fixed-assets/run-depreciation', ['month' => $m, 'date' => $d, 'assets' => [$asset->id],
            'exp' => [$asset->id => $expNew->id], 'acc' => [$asset->id => $accNew->id]]);
        $run('2026-04', '2026-04-30')->assertSessionHas('success');
        $run('2026-05', '2026-05-31')->assertSessionHas('success');
        $this->assertEquals(600000, (float) $asset->fresh()->accumulated_depreciation);
        $apr = \App\Models\DepreciationEntry::where('period', '2026-04')->firstOrFail();
        $may = \App\Models\DepreciationEntry::where('period', '2026-05')->firstOrFail();
        $this->get('/client/fixed-assets/depreciation?month=2026-06')->assertOk()->assertSee('Riwayat Penyusutan');

        $this->post('/client/fixed-assets/depreciation/' . $apr->journal_id . '/cancel')->assertSessionHas('error'); // Mei harus dibatalkan dulu
        $this->post('/client/fixed-assets/depreciation/' . $may->journal_id . '/cancel')->assertSessionHas('success');
        $this->assertEquals(300000, (float) $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(300000, (float) $expNew->fresh()->balance);
        $this->assertSame('2026-04-30', substr((string) $asset->fresh()->last_depreciation_date, 0, 10));
        $this->post('/client/fixed-assets/depreciation/' . $apr->journal_id . '/cancel')->assertSessionHas('success');
        $this->assertEquals(0, (float) $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(0, (float) $expNew->fresh()->balance);
        $this->assertNull($asset->fresh()->last_depreciation_date);
        $this->assertBooksBalanced();
        $this->post('/client/fixed-assets/depreciation/' . $may->journal_id . '/cancel')->assertSessionHas('error'); // sudah dibatalkan

        // bisa disusutkan ulang setelah dibatalkan; aset yang sudah disusutkan tidak bisa dihapus
        $run('2026-04', '2026-04-30')->assertSessionHas('success');
        $this->delete('/client/fixed-assets/' . $asset->id)->assertSessionHas('error');
        \App\Models\DepreciationEntry::where('period', '2026-04')->whereNull('voided_at')->get()
            ->each(fn ($e) => $this->post('/client/fixed-assets/depreciation/' . $e->journal_id . '/cancel')->assertSessionHas('success'));

        // hapus aset: jurnal perolehan dibalik, aset kembali nol
        $this->delete('/client/fixed-assets/' . $asset->id)->assertSessionHas('success');
        $this->assertEquals(0, (float) $assetAcc->fresh()->balance);
        $this->assertEquals($cashBefore, (float) $this->cash->fresh()->balance);
        $this->assertBooksBalanced();
    }

    public function test_down_payments_can_be_applied_to_invoices_and_cancelled(): void
    {
        $cid = $this->user->company_id;
        $today = date('Y-m-d');
        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-DP',
            'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertSessionMissing('error');
        $inv = PurchaseInvoice::firstOrFail();

        $this->post('/client/purchases/down-payments', ['payment_number' => 'DP-P-T1', 'supplier_id' => $this->supplier->id, 'payment_date' => $today,
            'amount' => 30000, 'account_id' => $this->cash->id])->assertSessionHas('success');
        $dp = \App\Models\DownPayment::where('type', 'purchase')->firstOrFail();
        $advance = \App\Services\AccountResolver::purchaseAdvance($cid);
        $ap = \App\Services\AccountResolver::payable($cid);
        $this->assertEquals(30000, (float) $advance->fresh()->balance);

        // pakai uang muka sebagian untuk faktur
        $this->post('/client/purchase-invoices/' . $inv->id . '/down-payments', ['down_payment_id' => $dp->id, 'amount' => '20.000,00', 'applied_date' => $today])->assertSessionHas('success');
        $this->assertEquals(80000, (float) $inv->fresh()->amount_due);
        $this->assertEquals(20000, (float) $inv->fresh()->amount_paid);
        $this->assertSame('partial', $inv->fresh()->status);
        $this->assertEquals(10000, (float) $advance->fresh()->balance);
        $this->assertEquals(80000, (float) $ap->fresh()->balance); // hutang 100.000 - uang muka dipakai 20.000
        $this->assertEquals(10000, $dp->fresh()->remaining);
        $this->assertBooksBalanced();
        $this->get('/client/purchase-invoices/' . $inv->id)->assertOk()->assertSee('Uang Muka Terpakai');
        $this->get('/client/purchases/down-payments')->assertOk()->assertSee('10.000,00');
        $this->get('/client/consistency')->assertOk()->assertDontSee('jumlah dibayar tidak cocok');

        // melebihi sisa uang muka / sisa tagihan ditolak; jurnal otomatis tidak bisa dihapus
        $this->post('/client/purchase-invoices/' . $inv->id . '/down-payments', ['down_payment_id' => $dp->id, 'amount' => 15000, 'applied_date' => $today])->assertSessionHas('error');
        $app = \App\Models\DownPaymentApplication::firstOrFail();
        $this->delete('/client/journals/' . $app->journal_id)->assertSessionHas('error');

        // batalkan pemakaian: kembali seperti semula
        $this->post('/client/down-payment-applications/' . $app->id . '/void')->assertSessionHas('success');
        $this->assertEquals(100000, (float) $inv->fresh()->amount_due);
        $this->assertSame('unpaid', $inv->fresh()->status);
        $this->assertEquals(30000, (float) $advance->fresh()->balance);
        $this->assertEquals(30000, $dp->fresh()->remaining);
        $this->post('/client/down-payment-applications/' . $app->id . '/void')->assertSessionHas('error');
        $this->assertBooksBalanced();

        // uang muka penuh melunasi faktur
        $this->post('/client/purchases/down-payments', ['payment_number' => 'DP-P-T2', 'supplier_id' => $this->supplier->id, 'payment_date' => $today,
            'amount' => 80000, 'account_id' => $this->cash->id])->assertSessionHas('success');
        $dp2 = \App\Models\DownPayment::where('payment_number', 'DP-P-T2')->firstOrFail();
        $this->post('/client/purchase-invoices/' . $inv->id . '/down-payments', ['down_payment_id' => $dp->id, 'amount' => 30000, 'applied_date' => $today])->assertSessionHas('success');
        $this->post('/client/purchase-invoices/' . $inv->id . '/down-payments', ['down_payment_id' => $dp2->id, 'amount' => 70000, 'applied_date' => $today])->assertSessionHas('success');
        $this->assertSame('paid', $inv->fresh()->status);
        $this->assertEquals(0, (float) $inv->fresh()->amount_due);
        $this->assertEquals(10000, $dp2->fresh()->remaining);
        $this->post("/client/purchase-invoices/{$inv->id}/void")->assertSessionHas('error'); // sudah ada pembayaran
        $this->assertBooksBalanced();

        // penjualan
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [2], 'prices' => [15000], 'discounts' => [0]])->assertSessionMissing('error');
        $sinv = SalesInvoice::firstOrFail();
        $this->post('/client/sales/down-payments', ['payment_number' => 'DP-S-T1', 'customer_id' => $this->customer->id, 'payment_date' => $today,
            'amount' => 10000, 'account_id' => $this->cash->id])->assertSessionHas('success');
        $sdp = \App\Models\DownPayment::where('type', 'sales')->firstOrFail();
        $this->post('/client/sales-invoices/' . $sinv->id . '/down-payments', ['down_payment_id' => $sdp->id, 'amount' => 10000, 'applied_date' => $today])->assertSessionHas('success');
        $this->assertEquals(20000, (float) $sinv->fresh()->amount_due);
        $this->assertEquals(0, (float) \App\Services\AccountResolver::salesAdvance($cid)->fresh()->balance);
        $this->assertBooksBalanced();
        $this->get('/client/sales/down-payments')->assertOk();
    }

    public function test_closed_period_blocks_changes_but_allows_paying_old_invoices(): void
    {
        $old = date('Y-m-d', strtotime('-60 days'));
        $closeTo = date('Y-m-d', strtotime('-30 days'));
        $today = date('Y-m-d');
        $po = $this->createPurchaseAndReceive(10);
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'supplier_invoice_no' => 'S-OLD',
            'invoice_date' => $old, 'due_date' => $old, 'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertSessionMissing('error');
        $inv = PurchaseInvoice::firstOrFail();
        $a = Account::where('company_id', $this->user->company_id)->where('type', 'expense')->get()->first(fn ($x) => !$x->is_header);
        $j = \App\Models\Journal::withoutGlobalScopes()->create(['company_id' => $this->user->company_id, 'ref_number' => 'MAN-1', 'type' => 'general', 'transaction_date' => $old,
            'description' => 'lama', 'status' => 'posted', 'created_by' => $this->user->id]);
        $this->user->company->forceFill(['closing_date' => $closeTo])->save();

        // faktur lama (periode tutup) tetap bisa dibayar di periode terbuka
        $this->post('/client/purchase-invoices/' . $inv->id . '/payments', ['account_id' => $this->cash->id, 'amount' => 40000, 'payment_date' => $today])->assertSessionHas('success');
        $this->assertEquals(60000, (float) $inv->fresh()->amount_due);
        $pay = \App\Models\InvoicePayment::firstOrFail();
        $this->post('/client/invoice-payments/' . $pay->id . '/void')->assertSessionHas('success');
        $this->assertEquals(100000, (float) $inv->fresh()->amount_due);

        // transaksi baru bertanggal di periode tutup ditolak dengan pesan jelas
        $this->post('/client/purchase-invoices/' . $inv->id . '/payments', ['account_id' => $this->cash->id, 'amount' => 1000, 'payment_date' => $old])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Tutup Buku'));
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', ['transaction_type' => 'spend', 'transaction_date' => $old, 'description' => 'x',
            'contra_account_id' => [Account::where('company_id', $this->user->company_id)->where('type', 'expense')->get()->first(fn ($a) => !$a->is_header)->id], 'amounts' => [100]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Tutup Buku'));

        // jurnal di periode tutup tidak bisa diubah/dihapus, termasuk lewat isi barisnya
        $this->delete('/client/journals/' . $j->id)->assertSessionHas('error');
        $this->assertNotNull(\App\Models\Journal::find($j->id));
        $this->expectException(\App\Exceptions\PeriodClosedException::class);
        \App\Models\JournalDetail::create(['journal_id' => $j->id, 'account_id' => $a->id, 'debit' => 5, 'credit' => 0]);
    }

    public function test_admin_can_lock_and_unlock_period(): void
    {
        $this->post('/client/company/month-end', ['closing_date' => date('Y-m-d', strtotime('-10 days'))])->assertSessionHas('success');
        $this->assertNotNull($this->user->company->fresh()->closing_date);
        $this->post('/client/company/month-end', ['closing_date' => date('Y-m-d', strtotime('+10 days'))])->assertSessionHasErrors('closing_date');
        $this->post('/client/company/month-end', ['action' => 'unlock'])->assertSessionHas('success');
        $this->assertNull($this->user->company->fresh()->closing_date);
    }

    public function test_consistency_flags_duplicate_retained_earnings_only_when_used(): void
    {
        $cid = $this->user->company_id;
        $primary = \App\Services\AccountResolver::retainedEarnings($cid);
        $other = Account::where('company_id', $cid)->where('type', 'equity')->where('id', '!=', $primary->id)
            ->where(fn ($q) => $q->where('name', 'like', '%laba ditahan%')->orWhere('name', 'like', '%retained earning%'))->firstOrFail();
        $this->get('/client/consistency')->assertOk()->assertDontSee('Akun Laba Ditahan ganda');
        $other->forceFill(['opening_balance' => 5000])->save();
        $this->get('/client/consistency')->assertOk()->assertSee('Akun Laba Ditahan ganda');
    }

    public function test_service_edit_page_renders_with_services_menu_active(): void
    {
        $svc = \App\Models\Product::withoutGlobalScopes()->create(['company_id' => $this->user->company_id, 'name' => 'Jasa Uji', 'sku' => 'JSV-1', 'type' => 'service', 'unit' => 'Jam']);
        $html = $this->get('/client/inventory/' . $svc->id . '/edit')->assertOk()->getContent();
        $this->assertStringContainsString('Jasa (tanpa stok)', $html);
    }

    public function test_purchase_return_supports_expense_account_rows(): void
    {
        $cid = $this->user->company_id;
        $today = date('Y-m-d');
        $exp = Account::where('company_id', $cid)->where('type', 'expense')->get()->first(fn ($a) => !$a->is_header);
        $this->get('/client/purchases/returns/create')->assertOk()->assertSee($exp->name);

        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => ['a' . $exp->id], 'quantities' => [4], 'prices' => [25000], 'discounts' => [0], 'row_descriptions' => ['Servis AC']])->assertRedirect();
        $pi = PurchaseInvoice::firstOrFail();
        $this->assertEquals(100000, (float) $pi->amount_due);

        // retur berlebih ditolak
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-X1',
            'return_date' => $today, 'products' => ['a' . $exp->id], 'quantities' => [5], 'prices' => [25000]])->assertSessionHas('error');
        // akun kas ditolak
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-X2',
            'return_date' => $today, 'products' => ['a' . $this->cash->id], 'quantities' => [1], 'prices' => [25000]])->assertSessionHas('error');

        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-X3',
            'return_date' => $today, 'products' => ['a' . $exp->id], 'quantities' => [1], 'prices' => [25000]])->assertSessionHas('success');
        $this->assertEquals(75000, (float) $pi->fresh()->amount_due);
        $ret = \App\Models\PurchaseReturn::firstOrFail();
        $item = $ret->items()->firstOrFail();
        $this->assertNull($item->product_id);
        $this->assertSame($exp->id, (int) $item->account_id);
        $d = \App\Models\JournalDetail::where('journal_id', $ret->journal_id)->where('account_id', $exp->id)->firstOrFail();
        $this->assertEquals(25000, (float) $d->credit);
        $this->assertBooksBalanced();

        // sisa 3 saja; retur 4 lagi ditolak, 3 diterima
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-X4',
            'return_date' => $today, 'products' => ['a' . $exp->id], 'quantities' => [4], 'prices' => [25000]])->assertSessionHas('error');
        $this->post('/client/purchases/returns', ['supplier_id' => $this->supplier->id, 'purchase_invoice_id' => $pi->id, 'return_number' => 'PR-X5',
            'return_date' => $today, 'products' => ['a' . $exp->id], 'quantities' => [3], 'prices' => [25000]])->assertSessionHas('success');
        $this->assertEquals(0, (float) $pi->fresh()->amount_due);
        $this->assertBooksBalanced();
    }

    public function test_one_delivery_can_fulfil_several_sales_orders_and_be_invoiced(): void
    {
        $this->createPurchaseAndReceive(20);
        $today = date('Y-m-d');
        foreach (['SO-M-1' => 3, 'SO-M-2' => 4] as $no => $q) {
            $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => $no, 'order_date' => $today,
                'products' => [$this->product->id], 'quantities' => [$q], 'prices' => [15000], 'discounts' => [0]])->assertRedirect();
        }
        [$a, $b] = SalesOrder::orderBy('id')->get()->all();

        // form create menarik dua SO sekaligus
        $this->get('/client/sales/deliveries/create?sales_order_id[]=' . $a->id . '&sales_order_id[]=' . $b->id)->assertOk()->assertSee('SO-M-1, SO-M-2');

        // melebihi sisa salah satu SO ditolak
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'delivery_number' => 'DO-M-X', 'delivery_date' => $today, 'status' => 'shipped',
            'products' => [$this->product->id, $this->product->id], 'row_orders' => [$a->id, $b->id], 'quantities' => [4, 4]])->assertSessionHas('error');

        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'delivery_number' => 'DO-M-1', 'delivery_date' => $today, 'status' => 'shipped',
            'products' => [$this->product->id, $this->product->id], 'row_orders' => [$a->id, $b->id], 'quantities' => [3, 4]])->assertSessionHas('success');
        $do = DeliveryOrder::firstOrFail();
        $this->assertNull($do->sales_order_id);
        $this->assertEquals([$a->id, $b->id], $do->sourceOrderIds()->sort()->values()->all());
        $this->assertEquals(13, $this->product->fresh()->stock);
        $this->get('/client/sales/deliveries/' . $do->id)->assertOk()->assertSee('SO-M-1')->assertSee('SO-M-2')->assertSee('Buat Faktur');
        $this->get('/client/sales/deliveries')->assertOk()->assertSee('SO-M-2');
        // SO tidak bisa dihapus/diubah lagi karena sudah ada pengiriman
        $this->assertNotEmpty(\App\Services\OrderLifecycle::salesOrderBlockers($b));

        // faktur dari pengiriman itu: tiap baris tetap tercatat ke SO-nya
        $page = $this->get('/client/sales-invoices/create?delivery_order_id[]=' . $do->id)->assertOk()->getContent();
        $this->assertStringContainsString('"order_id":' . $a->id, $page);
        $this->assertStringContainsString('"order_id":' . $b->id, $page);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id, $this->product->id], 'row_orders' => [$a->id, $b->id], 'row_docs' => [$do->id, $do->id],
            'quantities' => [3, 4], 'prices' => [15000, 15000], 'discounts' => [0, 0]])->assertRedirect();
        $this->assertSame('closed', $a->fresh()->status);
        $this->assertSame('closed', $b->fresh()->status);

        // batal pengiriman: kedua SO terbuka kembali untuk pengiriman (faktur tetap)
        \App\Services\OrderLifecycle::cancelDelivery($do->fresh());
        $this->assertSame('open', $a->fresh()->status);
        $this->assertEquals(20, $this->product->fresh()->stock);
        $this->assertBooksBalanced();
    }

    public function test_delivery_without_order_can_be_invoiced_only_once(): void
    {
        $this->createPurchaseAndReceive(10);
        $today = date('Y-m-d');
        $this->post('/client/sales/deliveries', ['customer_id' => $this->customer->id, 'delivery_number' => 'DO-F-1', 'delivery_date' => $today, 'status' => 'shipped',
            'products' => [$this->product->id], 'quantities' => [4]])->assertSessionHas('success');
        $do = DeliveryOrder::firstOrFail();
        $this->get('/client/sales/deliveries/' . $do->id)->assertSee('Buat Faktur');
        $this->get('/client/sales-invoices/create')->assertSee('DO-F-1');
        $page = $this->get('/client/sales-invoices/create?delivery_order_id[]=' . $do->id)->assertOk()->getContent();
        $this->assertStringContainsString('"doc_id":' . $do->id, $page);

        $base = ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id],
            'row_docs' => [$do->id], 'prices' => [15000], 'discounts' => [0]];
        $this->post('/client/sales-invoices', $base + ['quantities' => [5]])->assertSessionHas('error');
        $this->post('/client/sales-invoices', $base + ['quantities' => [3]])->assertRedirect();
        $this->assertSame($do->id, (int) SalesInvoice::firstOrFail()->items()->first()->delivery_order_id);
        // sisa 1 yang belum ditagih
        $page = $this->get('/client/sales-invoices/create?delivery_order_id[]=' . $do->id)->getContent();
        $this->assertMatchesRegularExpression('/"quantity":1(\.0)?[,}]/', $page);
        $this->post('/client/sales-invoices', $base + ['quantities' => [2]])->assertSessionHas('error');
        $this->post('/client/sales-invoices', $base + ['quantities' => [1]])->assertRedirect();
        $this->assertEquals(6, $this->product->fresh()->stock); // stok hanya dikurangi pengiriman, bukan lagi oleh faktur
        $this->get('/client/sales-invoices/create?delivery_order_id[]=' . $do->id)->assertSee('sudah ditagih');
        $this->assertBooksBalanced();
    }

    public function test_one_receipt_can_receive_several_purchase_orders(): void
    {
        $today = date('Y-m-d');
        foreach (['PO-M-1' => [5, 10000], 'PO-M-2' => [5, 20000]] as $no => [$q, $p]) {
            $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => $no, 'order_date' => $today,
                'products' => [$this->product->id], 'quantities' => [$q], 'prices' => [$p], 'discounts' => [0]])->assertRedirect();
        }
        [$a, $b] = PurchaseOrder::orderBy('id')->get()->all();
        $a->update(['status' => 'sent']); $b->update(['status' => 'sent']);
        $this->get('/client/purchases/receipts/create?purchase_order_id[]=' . $a->id . '&purchase_order_id[]=' . $b->id)->assertOk()->assertSee('PO-M-1, PO-M-2');
        $this->post('/client/purchases/receipts', ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'receipt_number' => 'IR-M-1',
            'receipt_date' => $today, 'status' => 'received', 'products' => [$this->product->id, $this->product->id], 'row_orders' => [$a->id, $b->id], 'quantities' => [5, 5]])
            ->assertSessionHas('success');
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertEquals(15000, round((float) $this->product->fresh()->buy_price)); // harga rata-rata dari masing-masing PO
        $this->assertSame('received', $a->fresh()->status);
        $this->assertSame('received', $b->fresh()->status);
        $r = \App\Models\ItemReceipt::firstOrFail();
        $this->get('/client/purchases/receipts/' . $r->id)->assertOk()->assertSee('PO-M-2')->assertSee('Buat Faktur');
        $this->get('/client/purchases/receipts')->assertOk()->assertSee('PO-M-2');
    }

    public function test_receipt_without_order_can_be_invoiced_only_once(): void
    {
        $today = date('Y-m-d');
        $this->get('/client/purchases/receipts/create')->assertOk();
        $this->post('/client/purchases/receipts', ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'receipt_number' => 'IR-F-1',
            'receipt_date' => $today, 'status' => 'received', 'products' => [$this->product->id], 'quantities' => [6]])->assertSessionHas('success');
        $r = \App\Models\ItemReceipt::firstOrFail();
        $this->get('/client/purchase-invoices/create')->assertSee('IR-F-1');
        $this->assertStringContainsString('"doc_id":' . $r->id, $this->get('/client/purchase-invoices/create?item_receipt_id[]=' . $r->id)->getContent());
        $base = ['supplier_id' => $this->supplier->id, 'invoice_date' => $today, 'due_date' => $today, 'products' => [$this->product->id],
            'row_docs' => [$r->id], 'prices' => [10000], 'discounts' => [0]];
        $this->post('/client/purchase-invoices', $base + ['quantities' => [6]])->assertRedirect();
        $this->post('/client/purchase-invoices', $base + ['quantities' => [1]])->assertSessionHas('error');
        $this->assertSame(1, PurchaseInvoice::count());
        $this->assertBooksBalanced();
    }

    public function test_payment_can_be_cancelled_from_its_journal_page_and_invoice_reopens(): void
    {
        $today = date('Y-m-d');
        $this->createPurchaseAndReceive(10);
        $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [2], 'prices' => [50000], 'discounts' => [0]])->assertRedirect();
        $inv = SalesInvoice::firstOrFail();
        $this->post("/client/sales-invoices/{$inv->id}/payments", ['account_id' => $this->cash->id, 'amount' => 100000, 'payment_date' => $today])->assertSessionHas('success');
        $this->assertSame('paid', $inv->fresh()->status);
        $pay = \App\Models\InvoicePayment::firstOrFail();

        $this->get('/client/journals/' . $pay->journal_id)->assertOk()
            ->assertSee('Batalkan Pembayaran')->assertSee($pay->payment_number)->assertDontSee('Void Jurnal');

        $this->from('/client/journals/' . $pay->journal_id)->post('/client/invoice-payments/' . $pay->id . '/void')
            ->assertRedirect('/client/journals/' . $pay->journal_id)->assertSessionHas('success');
        $this->assertSame('unpaid', $inv->fresh()->status);
        $this->assertEquals(100000, (float) $inv->fresh()->amount_due);
        $this->assertNotNull(\App\Models\InvoicePayment::find($pay->id)->voided_at); // tidak dihapus
        $this->get('/client/sales/receipts')->assertSee($pay->payment_number)->assertSee('Dibatalkan');

        // jurnal asli & jurnal baliknya tetap terkunci sebagai jejak
        $this->get('/client/journals/' . $pay->journal_id)->assertSee('sudah dibatalkan')->assertDontSee('Void Jurnal');
        $this->delete('/client/journals/' . $pay->journal_id)->assertSessionHas('error');
        $rev = \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->firstOrFail();
        $this->delete('/client/journals/' . $rev->id)->assertSessionHas('error');
        $this->assertBooksBalanced();
    }

    private function inventoryBalance(): float
    {
        $inv = \App\Services\AccountResolver::inventory($this->user->company_id);
        return round((float) \App\Models\JournalDetail::where('account_id', $inv->id)->sum('debit') - (float) \App\Models\JournalDetail::where('account_id', $inv->id)->sum('credit'), 2);
    }

    public function test_stock_adjustment_usage_and_opname_are_journaled_and_cancellable(): void
    {
        $today = date('Y-m-d');
        $po = $this->createPurchaseAndReceive(10); // 10 x 10.000
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'po_id' => $po->id, 'invoice_date' => $today, 'due_date' => $today,
            'products' => [$this->product->id], 'quantities' => [10], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();
        $this->assertEquals(100000, $this->inventoryBalance());
        $acc = \App\Services\InventoryAccounting::defaultContra($this->user->company_id, 'adjustment');
        foreach (['/client/inventory/adjustments', '/client/inventory/requests', '/client/inventory/stock-opname'] as $u) {
            $this->get($u)->assertOk();
        }

        // Penyesuaian -2 (hilang): stok 8, persediaan berkurang 20.000
        $this->post('/client/inventory/adjustments', ['product_id' => $this->product->id, 'quantity' => -2, 'date' => $today, 'account_id' => $acc->id, 'description' => 'hilang'])->assertSessionHas('success');
        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertEquals(80000, $this->inventoryBalance());

        // Pemakaian 3: stok 5
        $this->post('/client/inventory/requests', ['product_id' => $this->product->id, 'quantity' => 3, 'date' => $today, 'account_id' => $acc->id])->assertSessionHas('success');
        $this->assertEquals(5, $this->product->fresh()->stock);
        $this->assertEquals(50000, $this->inventoryBalance());
        // melebihi stok ditolak
        $this->post('/client/inventory/requests', ['product_id' => $this->product->id, 'quantity' => 99, 'date' => $today, 'account_id' => $acc->id])->assertSessionHas('error');
        // akun kas tidak boleh jadi akun lawan
        $this->post('/client/inventory/adjustments', ['product_id' => $this->product->id, 'quantity' => 1, 'date' => $today, 'account_id' => $this->cash->id])->assertSessionHas('error');

        // Stok opname: fisik 7 -> +2
        $this->post('/client/inventory/stock-opname', ['date' => $today, 'account_id' => $acc->id, 'counts' => [$this->product->id => 7]])->assertSessionHas('success');
        $this->assertEquals(7, $this->product->fresh()->stock);
        $this->assertEquals(70000, $this->inventoryBalance());
        $this->get('/client/inventory/stock-opname')->assertSee('fisik 7');

        // Batalkan penyesuaian -2: stok +2, persediaan +20.000, catatan tetap ada
        $m = \App\Models\InventoryMovement::where('reference_type', 'adjustment')->firstOrFail();
        $this->post('/client/inventory/movements/' . $m->id . '/cancel')->assertSessionHas('success');
        $this->assertEquals(9, $this->product->fresh()->stock);
        $this->assertEquals(90000, $this->inventoryBalance());
        $this->assertNotNull($m->fresh()->voided_at);
        $this->post('/client/inventory/movements/' . $m->id . '/cancel')->assertSessionHas('error');
        // jurnal penyesuaian terkunci dari Jurnal Umum
        $this->get('/client/journals/' . $m->journal_id)->assertSee('Penyesuaian Persediaan')->assertDontSee('Void Jurnal');
        $this->assertBooksBalanced();

        // halaman lama diarahkan
        $this->get('/client/inventory/stock-opname-orders')->assertRedirect('/client/inventory/stock-opname');
    }

    public function test_fixed_asset_can_be_sold_with_gain_or_loss_and_cancelled(): void
    {
        $cid = $this->user->company_id;
        $assetAcc = \App\Services\AssetAccounting::assetAccounts($cid)->first();
        $asset = \App\Models\FixedAsset::withoutGlobalScopes()->create(['company_id' => $cid, 'code' => 'AT-1', 'name' => 'Laptop', 'acquisition_date' => date('Y-m-d', strtotime('-1 year')),
            'acquisition_cost' => 12000000, 'useful_life_months' => 48, 'salvage_value' => 0, 'depreciation_method' => 'straight_line', 'status' => 'aktif', 'asset_account_id' => $assetAcc->id]);
        [, $accum] = (fn () => $this->depreciationAccounts($cid))->call(new \App\Http\Controllers\Client\FixedAssetController());
        $asset->forceFill(['accumulated_depreciation' => 3000000, 'accumulated_account_id' => $accum->first()->id])->save();
        $this->get('/client/fixed-assets/dispositions')->assertOk()->assertSee('AT-1');

        // jual 10 jt: nilai buku 9 jt -> laba 1 jt
        $gain = \App\Services\AssetDisposal::gainAccounts($cid)->first();
        $loss = \App\Services\AssetDisposal::lossAccounts($cid)->first();
        $this->post('/client/fixed-assets/' . $asset->id . '/dispose', ['date' => date('Y-m-d'), 'proceeds' => '10.000.000,00', 'proceeds_account_id' => $this->cash->id,
            'asset_account_id' => $assetAcc->id, 'accumulated_account_id' => $accum->first()->id, 'gain_account_id' => $gain->id, 'loss_account_id' => $loss->id])->assertSessionHas('success');
        $asset->refresh();
        $this->assertSame('dijual', $asset->status);
        $j = \App\Models\Journal::findOrFail($asset->disposal_journal_id);
        $this->assertEquals(1000000, (float) $j->details()->where('account_id', $gain->id)->sum('credit'));
        $this->assertEquals(12000000, (float) $j->details()->where('account_id', $assetAcc->id)->sum('credit'));
        $this->assertBooksBalanced();
        // sudah dilepas -> tidak bisa dilepas lagi, tidak ikut penyusutan
        $this->post('/client/fixed-assets/' . $asset->id . '/dispose', ['date' => date('Y-m-d')])->assertSessionHas('error');
        $this->get('/client/journals/' . $j->id)->assertSee('Batalkan Pelepasan');

        // batalkan -> aktif lagi, jurnal asli tetap terkunci
        $this->post('/client/fixed-assets/' . $asset->id . '/cancel-disposal')->assertSessionHas('success');
        $this->assertSame('aktif', $asset->fresh()->status);
        $this->delete('/client/journals/' . $j->id)->assertSessionHas('error');

        // dihapus tanpa harga jual -> rugi sebesar nilai buku 9 jt
        $this->post('/client/fixed-assets/' . $asset->id . '/dispose', ['date' => date('Y-m-d'), 'proceeds' => 0, 'asset_account_id' => $assetAcc->id,
            'accumulated_account_id' => $accum->first()->id, 'gain_account_id' => $gain->id, 'loss_account_id' => $loss->id])->assertSessionHas('success');
        $asset->refresh();
        $this->assertSame('dihapus', $asset->status);
        $this->assertEquals(9000000, (float) \App\Models\JournalDetail::where('journal_id', $asset->disposal_journal_id)->where('account_id', $loss->id)->sum('debit'));
        $this->assertBooksBalanced();
        $this->get('/client/fixed-assets/tax-categories')->assertRedirect();
    }

    public function test_bank_reconciliation_requires_matching_statement_and_can_be_undone(): void
    {
        $today = date('Y-m-d');
        $exp = Account::where('company_id', $this->user->company_id)->where('type', 'expense')->get()->first(fn ($a) => !$a->is_header);
        $rev = Account::where('company_id', $this->user->company_id)->where('type', 'revenue')->get()->first(fn ($a) => !$a->is_header);
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', ['transaction_type' => 'receive', 'transaction_date' => $today, 'description' => 'masuk', 'contra_account_id' => [$rev->id], 'amounts' => [500000]])->assertSessionHas('success');
        $this->post('/client/cash-bank/' . $this->cash->id . '/transaction', ['transaction_type' => 'spend', 'transaction_date' => $today, 'description' => 'keluar', 'contra_account_id' => [$exp->id], 'amounts' => [200000]])->assertSessionHas('success');
        $lines = \App\Models\JournalDetail::where('account_id', $this->cash->id)->orderBy('id')->get();
        $opening = (float) $this->cash->fresh()->opening_balance;

        $this->get('/client/cash-bank/reconciliation?account_id=' . $this->cash->id . '&date=' . $today)->assertOk();
        $this->get('/client/cash-bank/reconciliation?account_id=' . $this->cash->id . '&date=' . $today)->assertSee('masuk')->assertSee('keluar');
        // rekening koran baru memuat penerimaan saja -> saldo opening + 500.000; mencentang keduanya tidak cocok
        $this->post('/client/cash-bank/reconciliation', ['account_id' => $this->cash->id, 'date' => $today, 'statement_balance' => $opening + 500000, 'lines' => $lines->pluck('id')->all()])->assertSessionHas('error');
        $this->post('/client/cash-bank/reconciliation', ['account_id' => $this->cash->id, 'date' => $today, 'statement_balance' => $opening + 500000, 'lines' => [$lines[0]->id]])->assertSessionHas('success');
        $rec = \App\Models\BankReconciliation::firstOrFail();
        $this->assertSame($rec->id, (int) $lines[0]->fresh()->bank_reconciliation_id);
        // yang belum cocok tetap muncul di rekonsiliasi berikutnya
        $this->get('/client/cash-bank/reconciliation?account_id=' . $this->cash->id . '&date=' . $today)->assertSee('keluar')->assertDontSee('>masuk<', false);
        $this->post('/client/cash-bank/reconciliation', ['account_id' => $this->cash->id, 'date' => $today, 'statement_balance' => $opening + 300000, 'lines' => [$lines[1]->id]])->assertSessionHas('success');
        // batal hanya yang terbaru
        $this->post('/client/cash-bank/reconciliation/' . $rec->id . '/void')->assertSessionHas('error');
        $latest = \App\Models\BankReconciliation::latest('id')->first();
        $this->post('/client/cash-bank/reconciliation/' . $latest->id . '/void')->assertSessionHas('success');
        $this->assertNull($lines[1]->fresh()->bank_reconciliation_id);
        $this->get('/client/cash-bank/statements')->assertRedirect();
    }
}
