<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bom;
use App\Models\Customer;
use App\Models\ItemReceipt;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\ReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Alur manufaktur ala MYOB: beli bahan lewat faktur, build, lalu jual barang jadi. */
class ManufacturingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Supplier $supplier;
    private Customer $customer;
    private int $cid;

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

    private function item(string $sku, string $name, string $kind, float $sell = 0): Product
    {
        $this->post('/client/inventory', ['type' => 'goods', 'sku' => $sku, 'name' => $name, 'item_kind' => $kind, 'sell_price' => $sell, 'buy_price' => 0, 'unit' => 'pcs'])->assertSessionHasNoErrors();

        return Product::where('sku', $sku)->firstOrFail();
    }

    private function buy(Product $p, float $qty, float $price, array $extra = [])
    {
        return $this->post('/client/purchase-invoices', $extra + ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$p->id], 'quantities' => [$qty], 'prices' => [$price], 'discounts' => [0], 'received_now' => 1]);
    }

    private function sell(Product $p, float $qty, float $price)
    {
        return $this->post('/client/sales-invoices', ['customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$p->id], 'quantities' => [$qty], 'prices' => [$price], 'discounts' => [0]]);
    }

    public function test_item_kind_decides_inventory_account(): void
    {
        foreach (['bahan_baku' => 'Persediaan Bahan Baku', 'bahan_penolong' => 'Persediaan Bahan Penolong', 'barang_jadi' => 'Persediaan Barang Jadi'] as $kind => $account) {
            $this->assertSame($this->account($account)->id, $this->item('X-' . $kind, 'Barang ' . $kind, $kind)->inventory_account_id);
        }
        $this->get('/client/inventory/create')->assertOk()->assertSee('Jenis Barang');
        $this->get('/client/inventory?jenis=bahan_baku')->assertOk()->assertSee('Barang bahan_baku')->assertDontSee('Barang barang_jadi');
    }

    public function test_trading_company_does_not_choose_item_kind(): void
    {
        $this->user->company->forceFill(['business_type' => 'dagang'])->save();
        Account::where('company_id', $this->cid)->where('name', 'like', 'Persediaan Bahan%')->delete();
        Account::where('company_id', $this->cid)->whereIn('name', ['Persediaan Barang Dalam Proses', 'Persediaan Barang Jadi'])->delete();
        Account::create(['company_id' => $this->cid, 'code' => '1-1399', 'name' => 'Persediaan Barang Dagang', 'type' => 'asset', 'normal_balance' => 'debit', 'is_active' => true]);

        $this->get('/client/inventory/create')->assertOk()->assertDontSee('Jenis Barang');
        $this->post('/client/inventory', ['type' => 'goods', 'sku' => 'D-1', 'name' => 'Sepatu', 'sell_price' => 10, 'buy_price' => 5])->assertSessionHasNoErrors();
        $this->assertSame('barang_dagang', Product::where('sku', 'D-1')->first()->item_kind);
    }

    public function test_lists_show_the_right_kind_first(): void
    {
        $this->item('B1', 'Kayu Mentah', 'bahan_baku');
        $this->item('J1', 'Meja Jadi', 'barang_jadi');

        $sales = $this->get('/client/sales-invoices/create')->assertOk()->getContent();
        $this->assertLessThan(strpos($sales, 'Kayu Mentah'), strpos($sales, 'Meja Jadi'));
        $this->assertStringContainsString('data-warn="1"', $sales);

        foreach (['/client/purchase-invoices/create', '/client/purchases/orders/create', '/client/purchases/receipts/create', '/client/production/boms'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            if ($url !== '/client/production/boms') {
                $this->assertLessThan(strpos($html, 'Meja Jadi'), strpos($html, 'Kayu Mentah'), $url);
            }
        }
        $orders = $this->get('/client/production/orders')->assertOk()->getContent();
        $this->assertStringContainsString('<optgroup label="Barang Jadi">', $orders);
    }

    public function test_invoice_forms_offer_layouts(): void
    {
        $this->get('/client/purchase-invoices/create')->assertOk()->assertSee('layout-tabs', false)->assertSee('Barang sudah diterima')->assertSee('id="opts-biaya"', false);
        $this->get('/client/purchase-invoices/create?mode=biaya')->assertOk()->assertSee("let current = 'biaya'", false);
        $this->get('/client/purchase-invoices/create?layout=jasa')->assertOk()->assertSee("let current = 'jasa'", false);
        $this->get('/client/sales-invoices/create')->assertOk()->assertSee('layout-tabs', false)->assertDontSee('id="opts-biaya"', false);
    }

    public function test_service_company_has_no_goods_layout(): void
    {
        $this->user->company->forceFill(['business_type' => 'jasa'])->save();
        $this->user->refresh();

        $this->actingAs($this->user)->get('/client/purchase-invoices/create')->assertOk()->assertSee("let current = 'jasa'", false)->assertDontSee('Barang sudah diterima');
        $this->actingAs($this->user)->get('/client/sales-invoices/create')->assertOk()->assertDontSee('layout-tabs', false);
    }

    public function test_invoice_marked_received_adds_stock_with_net_price(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');

        $this->buy($kayu, 100, 20000, ['discounts' => [10], 'discount_percent' => 50])->assertSessionHas('success');

        // 20.000 - 10% = 18.000, lalu diskon faktur 50% = 9.000
        $this->assertEquals(100, $kayu->fresh()->stock);
        $this->assertEquals(9000, (float) $kayu->fresh()->buy_price);
        $this->assertEquals(900000, $this->balance('Persediaan Bahan Baku'));

        $receipt = ItemReceipt::firstOrFail();
        $this->assertSame('received', $receipt->status);
        $this->assertSame(PurchaseInvoice::first()->id, $receipt->source_invoice_id);
        $this->assertSame($receipt->id, PurchaseInvoice::first()->items()->first()->item_receipt_id);
        $this->assertSame(0, Account::where('company_id', $this->cid)->where('name', 'Selisih Harga Pembelian')->count());
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));
        $this->get('/client/purchases/receipts/' . $receipt->id)->assertOk()->assertSee('Kayu');
    }

    public function test_invoice_not_marked_received_keeps_stock_until_receipt(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');

        $this->post('/client/purchase-invoices', ['supplier_id' => $this->supplier->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$kayu->id], 'quantities' => [5], 'prices' => [100], 'discounts' => [0]])->assertSessionHas('success');

        $this->assertSame(0.0, (float) $kayu->fresh()->stock);
        $this->assertSame(0, ItemReceipt::count());
    }

    public function test_received_option_ignores_services_and_linked_rows(): void
    {
        $this->post('/client/inventory', ['type' => 'service', 'sku' => 'J-1', 'name' => 'Ongkir', 'sell_price' => 0])->assertSessionHasNoErrors();
        $svc = Product::where('sku', 'J-1')->firstOrFail();
        $svc->update(['expense_account_id' => $this->account('Biaya Overhead Pabrik')->id]);

        $this->buy($svc, 1, 5000)->assertSessionHas('success');

        $this->assertSame(0, ItemReceipt::count());
    }

    public function test_voiding_invoice_removes_the_automatic_receipt(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $this->buy($kayu, 10, 100)->assertSessionHas('success');
        $invoice = PurchaseInvoice::firstOrFail();

        $this->post('/client/purchases/receipts/' . ItemReceipt::first()->id . '/cancel')->assertSessionHas('error');
        $this->post('/client/purchase-invoices/' . $invoice->id . '/void')->assertSessionHas('success');

        $this->assertSame(0.0, (float) $kayu->fresh()->stock);
        $this->assertSame('cancelled', ItemReceipt::first()->status);
        $this->assertEquals(0, $this->balance('Persediaan Bahan Baku'));
    }

    public function test_voiding_invoice_is_refused_when_goods_were_used(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $meja = $this->item('J1', 'Meja', 'barang_jadi', 1000);
        $this->buy($kayu, 10, 100)->assertSessionHas('success');
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $meja->id, 'quantity' => 1, 'components' => [['product_id' => $kayu->id, 'quantity' => 2]]])->assertSessionHas('success');

        $this->post('/client/purchase-invoices/' . PurchaseInvoice::first()->id . '/void')->assertSessionHas('error');

        $this->assertNotSame('void', PurchaseInvoice::first()->status);
        $this->assertEquals(8, $kayu->fresh()->stock);
        $this->assertSame('received', ItemReceipt::first()->status);
    }

    public function test_cost_of_sale_uses_account_of_the_item(): void
    {
        $custom = Account::create(['company_id' => $this->cid, 'code' => '5-9999', 'name' => 'HPP Khusus Meja', 'type' => 'cost_of_sales', 'normal_balance' => 'debit', 'is_active' => true]);
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $meja = $this->item('J1', 'Meja', 'barang_jadi', 1000);
        $kursi = $this->item('J2', 'Kursi', 'barang_jadi', 500);
        $meja->update(['expense_account_id' => $custom->id]);
        $this->buy($kayu, 20, 100)->assertSessionHas('success');
        foreach ([$meja, $kursi] as $p) {
            $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $p->id, 'quantity' => 5, 'components' => [['product_id' => $kayu->id, 'quantity' => 10]]])->assertSessionHas('success');
        }

        $this->sell($meja->fresh(), 2, 1000)->assertSessionHas('success');
        $this->sell($kursi->fresh(), 1, 500)->assertSessionHas('success');

        $this->assertEquals(400, $this->balance('HPP Khusus Meja'));
        $this->assertEquals(200, $this->balance('HPP - Barang Jadi'));
        $this->assertEquals(1400, $this->balance('Persediaan Barang Jadi'));

        $this->post('/client/sales/returns', ['return_number' => 'RJ-1', 'sales_invoice_id' => SalesInvoice::orderBy('id')->first()->id, 'customer_id' => $this->customer->id, 'return_date' => date('Y-m-d'),
            'products' => [$meja->id], 'quantities' => [1], 'prices' => [1000]])->assertSessionHas('success');
        $this->assertEquals(200, $this->balance('HPP Khusus Meja'));
        $this->assertEquals(1600, $this->balance('Persediaan Barang Jadi'));
    }

    public function test_invalid_cost_account_falls_back_to_standard(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $meja = $this->item('J1', 'Meja', 'barang_jadi', 1000);
        $meja->update(['expense_account_id' => $this->account('Beban Lain-lain')->id]);
        $this->buy($kayu, 10, 100)->assertSessionHas('success');
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $meja->id, 'quantity' => 1, 'components' => [['product_id' => $kayu->id, 'quantity' => 2]]])->assertSessionHas('success');

        $this->sell($meja->fresh(), 1, 1000)->assertSessionHas('success');

        $this->assertEquals(200, $this->balance('HPP - Barang Jadi'));
        $this->assertEquals(800, $this->balance('Persediaan Bahan Baku'));
    }

    public function test_requirements_show_shortage_and_prefill_purchase_order(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $lem = $this->item('B2', 'Lem', 'bahan_penolong');
        $meja = $this->item('J1', 'Meja', 'barang_jadi', 1000);
        $this->buy($kayu, 100, 200)->assertSessionHas('success');
        $this->buy($lem, 1, 400)->assertSessionHas('success');
        $this->post('/client/production/boms', ['product_id' => $meja->id, 'output_qty' => 2, 'components' => [['product_id' => $kayu->id, 'quantity' => 5], ['product_id' => $lem->id, 'quantity' => 0.125]]])->assertSessionHas('success');
        $bom = Bom::firstOrFail();

        $this->get('/client/production/requirements')->assertOk()->assertSee($bom->name)->assertDontSee('Buat Pesanan Pembelian');

        // 100 meja = 50 x resep: kayu 250 (punya 100), lem 6,25 (punya 1)
        $response = $this->get('/client/production/requirements?qty[' . $bom->id . ']=100')->assertOk()->assertSee('Buat Pesanan Pembelian');
        $rows = $response->viewData('rows')->keyBy(fn ($r) => $r['product']->sku);
        $this->assertEquals(250, $rows['B1']['need']);
        $this->assertEquals(150, $rows['B1']['short']);
        $this->assertEquals(30000, $rows['B1']['cost']);
        $this->assertEquals(5.25, $rows['B2']['short']);

        $po = $this->get('/client/purchases/orders/create?items[' . $kayu->id . ']=150&items[' . $lem->id . ']=5.25')->assertOk();
        $this->assertCount(2, $po->viewData('prefillRows'));
        $this->assertEquals(200, $po->viewData('prefillRows')[0]['price']);

        $this->get('/client/production/requirements?qty[' . $bom->id . ']=2')->assertOk()->assertSee('Mulai Produksi');
    }

    public function test_full_flow_and_production_cost_report(): void
    {
        $kayu = $this->item('B1', 'Kayu', 'bahan_baku');
        $lem = $this->item('B2', 'Lem', 'bahan_penolong');
        $meja = $this->item('J1', 'Meja', 'barang_jadi', 150000);

        $this->buy($kayu, 100, 20000)->assertSessionHas('success');
        $this->buy($lem, 10, 40000)->assertSessionHas('success');
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $meja->id, 'quantity' => 8,
            'components' => [['product_id' => $kayu->id, 'quantity' => 20], ['product_id' => $lem->id, 'quantity' => 1]],
            'costs' => [['account_id' => $this->account('Biaya Tenaga Kerja Dibebankan ke Produksi')->id, 'amount' => '160.000,00'],
                ['account_id' => $this->account('Overhead Pabrik Dibebankan ke Produksi')->id, 'amount' => '80.000,00']]])->assertSessionHas('success');
        $this->sell($meja->fresh(), 5, 150000)->assertSessionHas('success');

        $this->assertEquals(1600000, $this->balance('Persediaan Bahan Baku'));
        $this->assertEquals(360000, $this->balance('Persediaan Bahan Penolong'));
        $this->assertEquals(255000, $this->balance('Persediaan Barang Jadi'));
        $this->assertEquals(425000, $this->balance('HPP - Barang Jadi'));
        $this->assertEmpty($this->get('/client/consistency')->viewData('issues'));

        $doc = (new ReportBuilder($this->cid))->productionCost(Request::create('/', 'GET', ['start_date' => date('Y-m-01'), 'end_date' => date('Y-m-d')]));
        $byLabel = collect($doc['rows'])->filter(fn ($r) => isset($r['cells']))->mapWithKeys(fn ($r) => [$r['cells'][1] => $r['cells'][3]]);
        $this->assertEquals(440000, $byLabel['Total Bahan']);
        $this->assertEquals(240000, $byLabel['Total Tenaga Kerja dan Overhead']);
        $this->assertEquals(680000, $byLabel['HARGA POKOK PRODUKSI']);
        $this->assertEquals(2400000, $byLabel['Pembelian']);
        $this->assertEquals(-440000, $byLabel['Dipakai produksi']);
        $this->assertEquals(680000, $byLabel['Hasil produksi']);
        $this->assertEquals(-425000, $byLabel['Terjual (HPP)']);
        $this->assertFalse($byLabel->has('Penyesuaian, retur, dan lainnya'));
        $sections = collect($doc['rows'])->where('type', 'subtotal')->filter(fn ($r) => $r['cells'][1] === 'Persediaan akhir')->values();
        $this->assertEquals(1960000, $sections[0]['cells'][3]);
        $this->assertEquals(255000, $sections[1]['cells'][3]);

        foreach (['', '?export=excel', '?export=csv', '?export=pdf'] as $q) {
            $this->get('/client/reports/production-cost' . $q)->assertOk();
        }
        $this->get('/client/reports')->assertOk()->assertSee('Harga Pokok Produksi');
    }

    public function test_production_report_and_requirements_are_only_for_manufacturers(): void
    {
        $this->user->company->forceFill(['business_type' => 'dagang'])->save();
        $this->user->refresh();
        $this->actingAs($this->user)->get('/client/reports/production-cost')->assertRedirect(route('client.dashboard'));
        $this->actingAs($this->user)->get('/client/production/requirements')->assertRedirect(route('client.dashboard'));
        $this->actingAs($this->user)->get('/client/reports')->assertOk()->assertDontSee('Harga Pokok Produksi');
    }
}
