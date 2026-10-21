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

        $orig = $inv->journal->details;
        $rev = \App\Models\Journal::where('ref_number', 'like', 'VOID-%')->firstOrFail()->details;
        $this->assertEqualsWithDelta($orig->sum('debit'), $rev->sum('credit'), 0.01);
        $this->assertEqualsWithDelta($orig->sum('credit'), $rev->sum('debit'), 0.01);
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

    public function test_depreciation_posts_balanced_journal_with_seeded_accounts(): void
    {
        $cid = $this->user->company_id;
        \App\Models\FixedAsset::create(['company_id' => $cid, 'code' => 'FA-1', 'name' => 'Laptop', 'acquisition_date' => date('Y-m-d'),
            'acquisition_cost' => 12000000, 'useful_life_months' => 48, 'salvage_value' => 0, 'depreciation_method' => 'straight_line',
            'status' => 'aktif']);
        $this->post('/client/fixed-assets/run-depreciation', ['date' => date('Y-m-t')])->assertSessionHas('success');
        $dep = Account::where('company_id', $cid)->where('name', 'like', 'Biaya Penyusutan%')->first();
        $this->assertEquals(250000, $dep->fresh()->balance);
        $this->assertEquals(250000, (float) \App\Models\FixedAsset::first()->accumulated_depreciation);
        // bulan yang sama tidak disusutkan dua kali
        $this->post('/client/fixed-assets/run-depreciation', ['date' => date('Y-m-t')]);
        $this->assertEquals(250000, $dep->fresh()->balance);
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
}
