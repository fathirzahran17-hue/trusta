<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\ItemReceipt;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nilai persediaan manufaktur harus selalu cocok dengan Buku Besar: dari beli bahan, produksi, sampai HPP. */
class ManufacturingCostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Customer $customer;
    private Warehouse $warehouse;
    private int $cid;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post('/register', ['company_name' => 'PT Mebel', 'name' => 'Owner', 'email' => 'm@uji.test', 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ', 'business_type' => 'manufaktur']);
        $this->user = User::where('email', 'm@uji.test')->firstOrFail();
        $this->user->company->forceFill(['is_premium' => true])->save();
        $this->actingAs($this->user);
        $this->cid = $this->user->company_id;
        $this->supplier = Supplier::create(['company_id' => $this->cid, 'name' => 'Pemasok', 'is_active' => true]);
        $this->customer = Customer::create(['company_id' => $this->cid, 'name' => 'Pelanggan', 'default_term_days' => 0, 'is_active' => true]);
        $this->warehouse = Warehouse::create(['company_id' => $this->cid, 'code' => 'G1', 'name' => 'Gudang']);
    }

    private function account(string $name): Account
    {
        return Account::where('company_id', $this->cid)->where('name', $name)->firstOrFail();
    }

    private function balance(string $name): float
    {
        $id = $this->account($name)->id;

        return round((float) JournalDetail::where('account_id', $id)->sum('debit') - (float) JournalDetail::where('account_id', $id)->sum('credit'), 2);
    }

    private function material(string $name = 'Kayu', float $buy = 0): Product
    {
        return Product::create(['company_id' => $this->cid, 'sku' => 'B' . (++$this->seq), 'name' => $name, 'type' => 'goods', 'unit' => 'm', 'sell_price' => 0, 'buy_price' => $buy,
            'is_active' => true, 'inventory_account_id' => $this->account('Persediaan Bahan Baku')->id]);
    }

    private function finished(string $name = 'Meja'): Product
    {
        return Product::create(['company_id' => $this->cid, 'sku' => 'J' . (++$this->seq), 'name' => $name, 'type' => 'goods', 'unit' => 'pcs', 'sell_price' => 150000, 'buy_price' => 0,
            'is_active' => true, 'inventory_account_id' => $this->account('Persediaan Barang Jadi')->id]);
    }

    private function receipt(array $data)
    {
        return $this->post('/client/purchases/receipts', $data + ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
            'receipt_number' => 'RC-' . (++$this->seq), 'receipt_date' => date('Y-m-d'), 'status' => 'received']);
    }

    private function produce(Product $finished, float $qty, array $components, array $costs = [])
    {
        return $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $finished->id, 'quantity' => $qty, 'components' => $components, 'costs' => $costs]);
    }

    public function test_receipt_without_order_needs_a_price_and_uses_it(): void
    {
        $kayu = $this->material();

        $this->get('/client/purchases/receipts/create')->assertOk()->assertSee('Harga Beli (Rp)');
        $this->receipt(['products' => [$kayu->id], 'quantities' => [100]])->assertSessionHas('error');
        $this->assertSame(0.0, (float) $kayu->fresh()->stock);
        $this->assertSame(0, ItemReceipt::count());

        $this->receipt(['products' => [$kayu->id], 'quantities' => [100], 'prices' => [20000]])->assertSessionHas('success');
        $this->assertEquals(100, $kayu->fresh()->stock);
        $this->assertEquals(20000, (float) $kayu->fresh()->buy_price);
    }

    public function test_draft_receipt_does_not_need_a_price(): void
    {
        $kayu = $this->material();

        $this->receipt(['products' => [$kayu->id], 'quantities' => [100], 'status' => 'draft'])->assertSessionHas('success');
        $this->assertSame(0.0, (float) $kayu->fresh()->stock);
    }

    public function test_receipt_falls_back_to_master_price(): void
    {
        $kayu = $this->material('Kayu', 15000);

        $this->receipt(['products' => [$kayu->id], 'quantities' => [10]])->assertSessionHas('success');
        $this->assertEquals(15000, (float) $kayu->fresh()->buy_price);
    }

    public function test_production_rejects_material_without_cost(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        \App\Models\InventoryMovement::create(['company_id' => $this->cid, 'product_id' => $kayu->id, 'date' => date('Y-m-d'), 'type' => 'in', 'quantity' => 10, 'unit_cost' => 0, 'remaining_qty' => 10, 'reference_type' => 'lama', 'created_by' => $this->user->id]);

        $this->produce($meja, 1, [['product_id' => $kayu->id, 'quantity' => 2]])->assertSessionHas('error');
        $this->assertSame(0, ProductionOrder::count());
        $this->assertEquals(10, $kayu->fresh()->stock);
        $this->assertSame(0.0, (float) $meja->fresh()->stock);
    }

    public function test_stock_in_adjustment_needs_a_cost(): void
    {
        $kayu = $this->material();
        $contra = $this->account('Ekuitas Saldo Awal');

        $this->post('/client/inventory/adjustments', ['product_id' => $kayu->id, 'quantity' => 10, 'date' => date('Y-m-d'), 'account_id' => $contra->id])->assertSessionHas('error');
        $this->assertSame(0.0, (float) $kayu->fresh()->stock);

        $kayu->update(['buy_price' => 100]);
        $this->post('/client/inventory/adjustments', ['product_id' => $kayu->id, 'quantity' => 10, 'date' => date('Y-m-d'), 'account_id' => $contra->id])->assertSessionHas('success');
        $this->assertEquals(1000, $this->balance('Persediaan Bahan Baku'));
    }

    private function order(Product $p, float $qty, float $price): PurchaseOrder
    {
        $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-' . (++$this->seq), 'order_date' => date('Y-m-d'),
            'products' => [$p->id], 'quantities' => [$qty], 'prices' => [$price], 'discounts' => [0]])->assertSessionHas('success');

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    private function invoice(Product $p, float $qty, float $price, ?PurchaseOrder $po = null, ?ItemReceipt $rc = null): PurchaseInvoice
    {
        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$p->id], 'quantities' => [$qty], 'prices' => [$price], 'discounts' => [0],
            'row_orders' => [$po?->id], 'row_docs' => [$rc?->id]])->assertSessionHas('success');

        return PurchaseInvoice::latest('id')->firstOrFail();
    }

    /** Barang diterima 100 @ 20.000 lewat PO. */
    private function received(Product $kayu): array
    {
        $po = $this->order($kayu, 100, 20000);
        $this->receipt(['products' => [$kayu->id], 'quantities' => [100], 'row_orders' => [$po->id]])->assertSessionHas('success');

        return [$po, ItemReceipt::latest('id')->firstOrFail()];
    }

    public function test_invoice_price_becomes_the_final_cost_while_goods_are_in_stock(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);

        $this->invoice($kayu, 100, 22000, $po, $rc);

        $this->assertEquals(22000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(2200000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_price_difference_on_used_goods_goes_to_variance_account(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        [$po, $rc] = $this->received($kayu);
        $this->produce($meja, 8, [['product_id' => $kayu->id, 'quantity' => 20]])->assertSessionHas('success');

        $this->invoice($kayu, 100, 22000, $po, $rc);

        // 80 masih di stok: +160.000 ke harga pokok; 20 sudah terpakai: 40.000 jadi beban
        $this->assertEquals(22000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(1760000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(40000, $this->balance('Selisih Harga Pembelian'));
        $this->assertEquals(400000, $this->balance('Persediaan Barang Jadi'));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_cheaper_invoice_lowers_cost_and_credits_variance(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        [$po, $rc] = $this->received($kayu);
        $this->produce($meja, 8, [['product_id' => $kayu->id, 'quantity' => 20]])->assertSessionHas('success');

        $this->invoice($kayu, 100, 18000, $po, $rc);

        $this->assertEquals(18000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(1440000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(-40000, $this->balance('Selisih Harga Pembelian'));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_voiding_invoice_restores_receipt_cost_and_allows_new_invoice(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        [$po, $rc] = $this->received($kayu);
        $this->produce($meja, 8, [['product_id' => $kayu->id, 'quantity' => 20]])->assertSessionHas('success');
        $inv = $this->invoice($kayu, 100, 22000, $po, $rc);

        $this->post('/client/purchases/receipts/' . $rc->id . '/cancel')->assertSessionHas('error');
        $this->post('/client/purchase-invoices/' . $inv->id . '/void')->assertSessionHas('success');

        $this->assertEquals(20000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(0, $this->balance('Selisih Harga Pembelian'));
        $this->assertSame(0, \App\Models\PurchaseCostAdjustment::count());

        $this->invoice($kayu, 100, 21000, $po, $rc);
        $this->assertEquals(21000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(1680000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(20000, $this->balance('Selisih Harga Pembelian'));
    }

    public function test_invoice_from_order_without_receipt_link_still_adjusts_cost(): void
    {
        $kayu = $this->material();
        [$po] = $this->received($kayu);

        $this->invoice($kayu, 100, 22000, $po);

        $this->assertEquals(22000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(2200000, $this->balance('Persediaan Bahan Baku'));
    }

    public function test_invoice_before_receipt_is_applied_when_goods_arrive(): void
    {
        $kayu = $this->material();
        $po = $this->order($kayu, 100, 20000);
        $this->invoice($kayu, 100, 22000, $po);
        $this->assertEquals(0, (float) $kayu->fresh()->buy_price);

        $this->receipt(['products' => [$kayu->id], 'quantities' => [100], 'row_orders' => [$po->id]])->assertSessionHas('success');

        $this->assertEquals(22000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(2200000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_partial_invoices_match_each_part_of_the_receipt_once(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);

        $this->invoice($kayu, 60, 22000, $po, $rc);
        $this->invoice($kayu, 40, 25000, $po, $rc);

        $this->assertEquals(2320000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(23200, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(100, (float) \App\Models\PurchaseCostAdjustment::sum('quantity'));
    }

    public function test_fifo_batch_follows_invoice_price(): void
    {
        $this->user->company->forceFill(['inventory_costing_method' => 'fifo'])->save();
        $kayu = $this->material();
        $meja = $this->finished();
        $po = $this->order($kayu, 10, 100);
        $this->receipt(['products' => [$kayu->id], 'quantities' => [10], 'row_orders' => [$po->id]])->assertSessionHas('success');
        $rc = ItemReceipt::latest('id')->firstOrFail();
        $this->produce($meja, 1, [['product_id' => $kayu->id, 'quantity' => 4]])->assertSessionHas('success');

        $this->invoice($kayu, 10, 120, $po, $rc);

        // 4 sudah terpakai @100: selisih 80 jadi beban; 6 sisa naik ke @120
        $this->assertEquals(720, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(80, $this->balance('Selisih Harga Pembelian'));
        $this->produce($meja, 1, [['product_id' => $kayu->id, 'quantity' => 6]])->assertSessionHas('success');
        $this->assertEquals(720, (float) ProductionOrder::latest('id')->first()->material_cost);
        $this->assertEquals(0, $this->balance('Persediaan Bahan Baku'));
    }

    public function test_rebuilt_invoice_journal_keeps_price_difference(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        [$po, $rc] = $this->received($kayu);
        $this->produce($meja, 8, [['product_id' => $kayu->id, 'quantity' => 20]])->assertSessionHas('success');
        $inv = $this->invoice($kayu, 100, 22000, $po, $rc);

        JournalDetail::where('journal_id', $inv->journal_id)->delete();
        \App\Models\Journal::whereKey($inv->journal_id)->delete();
        $inv->update(['journal_id' => null]);

        \App\Services\InvoiceJournalRebuilder::purchase($inv->fresh(), $this->user->id);

        $this->assertEquals(1760000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(40000, $this->balance('Selisih Harga Pembelian'));
    }

    public function test_fractional_material_keeps_stock_and_ledger_equal(): void
    {
        $lem = $this->material('Lem', 0);
        $meja = $this->finished();
        $po = $this->order($lem, 10, 40000);
        $this->receipt(['products' => [$lem->id], 'quantities' => [10], 'row_orders' => [$po->id]])->assertSessionHas('success');
        $this->invoice($lem, 10, 40000, $po, ItemReceipt::latest('id')->firstOrFail());

        $this->produce($meja, 1, [['product_id' => $lem->id, 'quantity' => 0.125]])->assertSessionHas('success');

        $this->assertEqualsWithDelta(9.875, (float) $lem->fresh()->stock, 0.00001);
        $this->assertEquals(395000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(5000, $this->balance('Persediaan Barang Jadi'));
        $this->assertEqualsWithDelta(395000, (float) $lem->fresh()->stock * (float) $lem->fresh()->buy_price, 0.01);
    }

    public function test_cancelling_production_recalculates_material_cost(): void
    {
        $kayu = $this->material();
        $meja = $this->finished();
        foreach ([[10, 100], [5, 200]] as $n => [$qty, $price]) {
            $po = $this->order($kayu, $qty, $price);
            $this->receipt(['products' => [$kayu->id], 'quantities' => [$qty], 'row_orders' => [$po->id]])->assertSessionHas('success');
            $this->invoice($kayu, $qty, $price, $po, ItemReceipt::latest('id')->firstOrFail());
            if ($n === 0) {
                $this->produce($meja, 1, [['product_id' => $kayu->id, 'quantity' => 5]])->assertSessionHas('success');
            }
        }

        $this->post('/client/production/orders/' . ProductionOrder::first()->id . '/cancel')->assertSessionHas('success');

        $this->assertEquals(15, $kayu->fresh()->stock);
        $this->assertEquals(2000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEqualsWithDelta(2000, (float) $kayu->fresh()->stock * (float) $kayu->fresh()->buy_price, 1);
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_manufacturer_must_choose_inventory_account(): void
    {
        $base = ['type' => 'goods', 'sku' => 'BHN-1', 'name' => 'Paku', 'sell_price' => 0, 'buy_price' => 100];

        $this->post('/client/inventory', $base)->assertSessionHasErrors('inventory_account_id');
        $this->assertSame(0, Product::where('sku', 'BHN-1')->count());

        $this->post('/client/inventory', $base + ['inventory_account_id' => $this->account('Persediaan Bahan Baku')->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Product::where('sku', 'BHN-1')->count());
    }

    public function test_changing_inventory_account_moves_stock_value(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);
        $this->invoice($kayu, 100, 20000, $po, $rc);

        $this->put('/client/inventory/' . $kayu->id, ['sku' => $kayu->sku, 'name' => 'Kayu', 'sell_price' => 0, 'buy_price' => 20000, 'is_active' => 1,
            'inventory_account_id' => $this->account('Persediaan Barang Jadi')->id])->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(2000000, $this->balance('Persediaan Barang Jadi'));
    }

    public function test_cost_is_locked_while_stock_exists(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);
        $this->invoice($kayu, 100, 20000, $po, $rc);
        $data = ['sku' => $kayu->sku, 'name' => 'Kayu', 'sell_price' => 5, 'is_active' => 1, 'inventory_account_id' => $kayu->inventory_account_id];

        $this->get('/client/inventory/' . $kayu->id . '/edit')->assertOk()->assertSee('Terkunci karena masih ada stok');
        $this->put('/client/inventory/' . $kayu->id, $data + ['buy_price' => 500])->assertSessionHasErrors('buy_price');
        $this->assertEquals(20000, (float) $kayu->fresh()->buy_price);

        $this->put('/client/inventory/' . $kayu->id, $data + ['buy_price' => 20000])->assertSessionHasNoErrors();
        $this->assertEquals(5, (float) $kayu->fresh()->sell_price);
    }

    public function test_return_requires_invoice_with_clear_message(): void
    {
        $kayu = $this->material('Kayu', 100);
        $this->post('/client/purchases/returns', ['return_number' => 'RB-1', 'supplier_id' => $this->supplier->id, 'return_date' => date('Y-m-d'),
            'products' => [$kayu->id], 'quantities' => [1], 'prices' => [100]])->assertSessionHasErrors(['purchase_invoice_id' => 'Pilih Faktur Pembelian yang diretur.']);
        $this->post('/client/sales/returns', ['return_number' => 'RJ-1', 'customer_id' => $this->customer->id, 'return_date' => date('Y-m-d'),
            'products' => [$kayu->id], 'quantities' => [1], 'prices' => [100]])->assertSessionHasErrors(['sales_invoice_id' => 'Pilih Faktur Penjualan yang diretur.']);
    }

    public function test_fifo_stock_value_matches_ledger(): void
    {
        $this->user->company->forceFill(['inventory_costing_method' => 'fifo'])->save();
        $kayu = $this->material();
        $meja = $this->finished();
        foreach ([100, 200] as $price) {
            $po = $this->order($kayu, 10, $price);
            $this->receipt(['products' => [$kayu->id], 'quantities' => [10], 'row_orders' => [$po->id]])->assertSessionHas('success');
            $this->invoice($kayu, 10, $price, $po, ItemReceipt::latest('id')->firstOrFail());
        }
        $this->produce($meja, 5, [['product_id' => $kayu->id, 'quantity' => 15]])->assertSessionHas('success');

        $this->assertEquals(2000, (float) ProductionOrder::first()->material_cost);
        $this->assertEquals(1000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(1000, \App\Services\InventoryAccounting::stockValue($kayu->fresh()));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
    }

    public function test_production_date_must_be_after_material_arrived(): void
    {
        $kayu = $this->material('Kayu', 100);
        $meja = $this->finished();
        $this->receipt(['products' => [$kayu->id], 'quantities' => [10]])->assertSessionHas('success');

        $this->post('/client/production/orders', ['date' => date('Y-m-d', strtotime('-30 days')), 'product_id' => $meja->id, 'quantity' => 1,
            'components' => [['product_id' => $kayu->id, 'quantity' => 1]]])->assertSessionHas('error');
        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_closed_period_message_uses_readable_name(): void
    {
        $kayu = $this->material('Kayu', 100);
        $meja = $this->finished();
        $this->post('/client/inventory/adjustments', ['product_id' => $kayu->id, 'quantity' => 10, 'date' => date('Y-m-d', strtotime('-40 days')), 'account_id' => $this->account('Ekuitas Saldo Awal')->id])->assertSessionHas('success');
        $this->user->company->forceFill(['closing_date' => date('Y-m-d', strtotime('-10 days'))])->save();
        $this->user->refresh();

        $response = $this->actingAs($this->user)->post('/client/production/orders', ['date' => date('Y-m-d', strtotime('-20 days')), 'product_id' => $meja->id, 'quantity' => 1,
            'components' => [['product_id' => $kayu->id, 'quantity' => 1]]]);

        $response->assertSessionHas('error');
        $this->assertStringStartsWith('Perintah Produksi bertanggal', session('error'));
    }

    public function test_production_menu_is_only_for_manufacturers(): void
    {
        $this->get('/client/production/orders')->assertOk();

        $this->user->company->forceFill(['business_type' => 'dagang'])->save();
        $this->user->refresh();
        $this->actingAs($this->user)->get('/client/production/orders')->assertRedirect(route('client.dashboard'));
        $this->actingAs($this->user)->get('/client/production/boms')->assertRedirect(route('client.dashboard'));
    }

    public function test_changing_account_does_not_move_goods_not_yet_invoiced(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);

        $this->put('/client/inventory/' . $kayu->id, ['sku' => $kayu->sku, 'name' => 'Kayu', 'sell_price' => 0, 'buy_price' => 20000, 'is_active' => 1,
            'inventory_account_id' => $this->account('Persediaan Barang Jadi')->id])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(0, $this->balance('Persediaan Barang Jadi'));

        $this->invoice($kayu, 100, 20000, $po, $rc);
        $this->assertEquals(0, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(2000000, $this->balance('Persediaan Barang Jadi'));
    }

    public function test_deleting_company_removes_cost_adjustments(): void
    {
        $kayu = $this->material();
        [$po, $rc] = $this->received($kayu);
        $this->invoice($kayu, 100, 22000, $po, $rc);
        $this->assertSame(1, \App\Models\PurchaseCostAdjustment::count());

        \App\Services\CompanyPurger::purge($this->cid);

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('purchase_cost_adjustments')->count());
    }
}
