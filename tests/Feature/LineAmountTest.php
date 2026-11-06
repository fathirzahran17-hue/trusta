<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CoaSeeder;
use App\Support\LineAmount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Subtotal yang diketik, diskon rupiah, dan tahun tanggal yang salah ketik. */
class LineAmountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private int $cid;
    private Customer $customer;
    private Supplier $supplier;
    private Product $service;
    private Product $goods;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Test', 'price' => 0, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 5,
            'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => false, 'trial_days' => 0, 'is_active' => true]);
        $company = Company::create(['name' => 'PT Uji', 'email' => 'uji@example.com', 'fiscal_year_start' => 1, 'is_active' => true]);
        $company->forceFill(['is_premium' => true])->save();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'amount_paid' => 0]);
        $this->user = User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => 'a@example.com', 'password' => bcrypt('password'), 'role' => 'client_admin', 'is_active' => true]);
        CoaSeeder::seed($company->id);
        $this->cid = $company->id;
        $this->customer = Customer::create(['company_id' => $this->cid, 'name' => 'Pelanggan', 'default_term_days' => 30, 'is_active' => true]);
        $this->supplier = Supplier::create(['company_id' => $this->cid, 'name' => 'Pemasok', 'default_term_days' => 30, 'is_active' => true]);
        $revenue = Account::where('company_id', $this->cid)->where('name', 'Pendapatan Jasa')->first() ?? Account::where('company_id', $this->cid)->where('type', 'revenue')->get()->first(fn ($a) => ! $a->is_header);
        $expense = Account::where('company_id', $this->cid)->where('name', 'like', 'Beban Transportasi%')->first();
        $this->service = Product::create(['company_id' => $this->cid, 'sku' => 'J-1', 'name' => 'Jasa Pasang', 'type' => 'service', 'unit' => 'kali', 'sell_price' => 0, 'is_active' => true, 'revenue_account_id' => $revenue->id]);
        $this->goods = Product::create(['company_id' => $this->cid, 'sku' => 'B-1', 'name' => 'Bulu Mata', 'type' => 'goods', 'unit' => 'pcs', 'sell_price' => 0, 'buy_price' => 10000, 'is_active' => true,
            'revenue_account_id' => Account::where('company_id', $this->cid)->where('name', 'Pendapatan Penjualan')->value('id'), 'expense_account_id' => $expense->id]);
        $this->actingAs($this->user);
    }

    private function sale(array $line, array $extra = [])
    {
        return $this->post('/client/sales-invoices', array_merge(['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->service->id]], $line, $extra));
    }

    public function test_shown_total_is_used_only_when_it_matches_quantity_and_price(): void
    {
        $this->assertSame(100000.0, LineAmount::net(3, 33333.33, 0, '100000'));
        $this->assertSame(192499.0, LineAmount::net(10, 27500, 30.000363636, '192499'));
        $this->assertEqualsWithDelta(99999.99, LineAmount::net(3, 33333.33, 0, '150000'), 0.001);
        $this->assertEqualsWithDelta(99999.99, LineAmount::net(3, 33333.33, 0, null), 0.001);
        $this->assertEqualsWithDelta(99999.99, LineAmount::net(3, 33333.33, 0, '-5'), 0.001);
        $this->assertEqualsWithDelta(99999.99, LineAmount::net(3, 33333.33, 0, 'abc'), 0.001);
    }

    public function test_typed_subtotal_is_stored_exactly_on_a_sales_invoice(): void
    {
        $this->sale(['quantities' => [3], 'prices' => [33333.33], 'discounts' => [0], 'line_totals' => ['100000.00']])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $invoice = SalesInvoice::with('items')->firstOrFail();
        $this->assertEquals(100000, $invoice->items->first()->total);
        $this->assertEquals(100000, $invoice->subtotal);
        $this->assertEquals(100000, $invoice->total);
        $revenue = \App\Models\JournalDetail::whereHas('journal', fn ($q) => $q->where('company_id', $this->cid))->whereHas('account', fn ($q) => $q->where('type', 'revenue'))->sum('credit');
        $this->assertEquals(100000, $revenue);
    }

    public function test_rupiah_discount_keeps_the_exact_line_total(): void
    {
        // Rp275.000 dipotong Rp82.501: persennya tidak bulat, tapi nilai barisnya harus tepat Rp192.499
        $percent = 82501 / 275000 * 100;
        $this->sale(['quantities' => [10], 'prices' => [27500], 'discounts' => [$percent], 'line_totals' => ['192499.00']])->assertSessionHasNoErrors();

        $item = SalesInvoice::with('items')->firstOrFail()->items->first();
        $this->assertEquals(192499, $item->total);
        $this->assertEquals(30, round((float) $item->discount_percent));
    }

    public function test_a_total_that_does_not_match_the_line_is_ignored(): void
    {
        $this->sale(['quantities' => [10], 'prices' => [27500], 'discounts' => [0], 'line_totals' => ['1.00']])->assertSessionHasNoErrors();

        $this->assertEquals(275000, SalesInvoice::firstOrFail()->total);
    }

    public function test_discount_above_one_hundred_percent_is_rejected(): void
    {
        $this->sale(['quantities' => [10], 'prices' => [27500], 'discounts' => [-82500]])->assertSessionHasErrors('discounts.0');
        $this->sale(['quantities' => [10], 'prices' => [27500], 'discounts' => [150]])->assertSessionHasErrors('discounts.0');
        $this->assertSame(0, SalesInvoice::count());
    }

    public function test_orders_and_purchase_invoices_use_the_same_rule(): void
    {
        $this->post('/client/sales/orders', ['customer_id' => $this->customer->id, 'order_number' => 'SO-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->service->id], 'quantities' => [3], 'prices' => [33333.33], 'discounts' => [0], 'line_totals' => ['100000.00']])->assertSessionHasNoErrors();
        $order = SalesOrder::with('details')->firstOrFail();
        $this->assertEquals(100000, $order->details->first()->subtotal);
        $this->assertEquals(0, round((float) $order->details->first()->discount_amount, 2));

        // Mengubah pesanan: diskon rupiah tetap tepat
        $percent = 1001 / 100000 * 100;
        $this->put(route('client.sales.orders.update', $order), ['customer_id' => $this->customer->id, 'order_number' => 'SO-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->service->id], 'quantities' => [3], 'prices' => [33333.33], 'discounts' => [$percent], 'line_totals' => ['98999.00']])->assertSessionHasNoErrors();
        $detail = $order->fresh('details')->details->first();
        $this->assertEquals(98999, $detail->subtotal);
        $this->assertEqualsWithDelta(1000.99, (float) $detail->discount_amount, 0.02);

        $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->goods->id], 'quantities' => [3], 'prices' => [33333.33], 'discounts' => [0], 'line_totals' => ['100000.00']])->assertSessionHasNoErrors();
        $po = PurchaseOrder::with('items')->firstOrFail();
        $this->assertEquals(100000, $po->items->first()->total);
        $this->put(route('client.purchases.orders.update', $po), ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->goods->id], 'quantities' => [6], 'prices' => [16666.67], 'discounts' => [0], 'line_totals' => ['100000.00']])->assertSessionHasNoErrors();
        $this->assertEquals(100000, $po->fresh('items')->items->first()->total);

        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'supplier_invoice_no' => 'S-1', 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->goods->id], 'quantities' => [3], 'prices' => [33333.33], 'discounts' => [0], 'line_totals' => ['100000.00']])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertEquals(100000, PurchaseInvoice::firstOrFail()->total);
    }

    public function test_year_typed_wrong_is_rejected_before_anything_is_journaled(): void
    {
        $this->sale(['quantities' => [1], 'prices' => [50000], 'discounts' => [0]], ['invoice_date' => '0026-08-26', 'due_date' => '2026-08-31'])->assertSessionHas('error');

        $this->assertSame(0, SalesInvoice::count());
        $this->assertSame(0, \App\Models\Journal::count());
        $this->assertStringContainsString('tahunnya tidak wajar', session('error'));
    }

    public function test_goods_can_be_saved_without_a_selling_price(): void
    {
        $this->get(route('client.inventory.create'))->assertOk()->assertSee('Harga Jual (Rp) — opsional')->assertDontSee('name="sell_price" id="sell_price" value="0" required', false);
        $this->post('/client/inventory', ['type' => 'goods', 'sku' => 'B-9', 'name' => 'Tanpa Harga', 'item_kind' => 'barang_dagang', 'unit' => 'pcs'])->assertSessionHasNoErrors();
        $this->assertEquals(0, Product::where('sku', 'B-9')->value('sell_price'));
    }

    public function test_forms_offer_subtotal_and_discount_modes(): void
    {
        foreach (['/client/sales-invoices/create', '/client/sales/orders/create', '/client/purchase-invoices/create', '/client/purchases/orders/create'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('input-subtotal-display', false)
                ->assertSee('name="line_totals[]"', false)
                ->assertSee('disc-mode', false)
                ->assertSee('id="discount_display"', false)
                ->assertDontSee('Disk (%)');
        }
    }
}
