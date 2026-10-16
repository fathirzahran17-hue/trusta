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
        $rev = Account::where('company_id', $company->id)->where('type', 'revenue')->first();
        $exp = Account::where('company_id', $company->id)->whereIn('type', ['cost_of_sales', 'expense'])->first();
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
}
