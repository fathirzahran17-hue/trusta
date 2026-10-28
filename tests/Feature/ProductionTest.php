<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use App\Services\InventoryAccounting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private function bal(int $accId): float
    {
        return round((float) \App\Models\JournalDetail::where('account_id', $accId)->sum('debit') - (float) \App\Models\JournalDetail::where('account_id', $accId)->sum('credit'), 2);
    }

    public function test_manufacturer_builds_items_from_bom_with_journal_and_can_cancel(): void
    {
        $this->post('/register', ['company_name' => 'PT Mebel', 'name' => 'Owner', 'email' => 'm@uji.test', 'password' => 'Rahasia#2026x', 'password_confirmation' => 'Rahasia#2026x', 'business_type' => 'manufaktur']);
        $u = User::where('email', 'm@uji.test')->firstOrFail();
        $u->company->forceFill(['is_premium' => true])->save();
        $this->actingAs($u);
        $cid = $u->company_id;
        $acc = fn ($n) => Account::where('company_id', $cid)->where('name', $n)->firstOrFail();
        $raw = $acc('Persediaan Bahan Baku'); $fin = $acc('Persediaan Barang Jadi');
        $labor = $acc('Biaya Tenaga Kerja Dibebankan ke Produksi');
        $equity = $acc('Ekuitas Saldo Awal');

        $this->get('/client/dashboard')->assertSee('Perintah Produksi')->assertSee('Resep Produk (BOM)');
        $mk = fn ($name, $acct, $price) => Product::create(['company_id' => $cid, 'sku' => strtoupper(substr($name, 0, 3)) . rand(10, 99), 'name' => $name, 'type' => 'goods', 'unit' => 'pcs', 'sell_price' => 0, 'buy_price' => $price, 'is_active' => true, 'inventory_account_id' => $acct->id]);
        $kaki = $mk('Kaki Meja', $raw, 10000);
        $papan = $mk('Papan', $raw, 50000);
        $meja = $mk('Meja', $fin, 0);
        $meja->update(['sell_price' => 200000]);
        // stok awal bahan (dijurnal ke persediaan bahan baku)
        InventoryAccounting::adjust($cid, $kaki, 40, date('Y-m-d'), $equity->id, 'adjustment', 'stok awal', $u->id);
        InventoryAccounting::adjust($cid, $papan, 10, date('Y-m-d'), $equity->id, 'adjustment', 'stok awal', $u->id);
        $this->assertEquals(900000, $this->bal($raw->id));

        // Resep: 1 meja = 4 kaki + 1 papan
        $this->get('/client/production/boms')->assertOk();
        $this->post('/client/production/boms', ['product_id' => $meja->id, 'output_qty' => 1, 'components' => [['product_id' => $kaki->id, 'quantity' => 4], ['product_id' => $papan->id, 'quantity' => 1]]])->assertSessionHas('success');
        $bom = \App\Models\Bom::firstOrFail();
        $this->assertSame(2, $bom->items()->count());
        $this->post('/client/production/boms', ['product_id' => $meja->id, 'output_qty' => 1, 'components' => [['product_id' => $meja->id, 'quantity' => 1]]])->assertSessionHas('error');
        $this->get('/client/production/orders')->assertOk()->assertSee($bom->name);

        // Produksi 5 meja: bahan 5*(4*10.000 + 50.000) = 450.000 + upah 50.000 = 500.000 -> 100.000/meja
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'bom_id' => $bom->id, 'product_id' => $meja->id, 'quantity' => 5,
            'components' => [['product_id' => $kaki->id, 'quantity' => 20], ['product_id' => $papan->id, 'quantity' => 5]],
            'costs' => [['account_id' => $labor->id, 'amount' => '50.000,00', 'description' => 'upah']]])->assertRedirect();
        $o = ProductionOrder::firstOrFail();
        $this->assertEquals(450000, (float) $o->material_cost);
        $this->assertEquals(500000, (float) $o->total_cost);
        $this->assertEquals(5, $meja->fresh()->stock);
        $this->assertEquals(20, $kaki->fresh()->stock);
        $this->assertEquals(100000, (float) $meja->fresh()->buy_price);
        $this->assertEquals(450000, $this->bal($raw->id));
        $this->assertEquals(500000, $this->bal($fin->id));
        $this->assertEquals(-50000, $this->bal($labor->id));
        $this->get('/client/production/orders/' . $o->id)->assertOk()->assertSee('Kaki Meja')->assertSee('Batalkan Produksi');
        $this->get('/client/journals/' . $o->journal_id)->assertSee('Batalkan Produksi')->assertDontSee('Void Jurnal');

        // bahan kurang ditolak
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $meja->id, 'quantity' => 50,
            'components' => [['product_id' => $kaki->id, 'quantity' => 200]]])->assertSessionHas('error');

        // jual 1 meja: HPP dikredit dari Persediaan Barang Jadi
        $cust = Customer::create(['company_id' => $cid, 'name' => 'Toko', 'default_term_days' => 0, 'is_active' => true]);
        $this->post('/client/sales-invoices', ['customer_id' => $cust->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$meja->id], 'quantities' => [1], 'prices' => [200000], 'discounts' => [0]])->assertRedirect();
        $this->assertEquals(400000, $this->bal($fin->id));
        $this->assertEquals(4, $meja->fresh()->stock);

        // batal produksi tidak bisa bila barang jadi sudah terpakai sebagian (stok 4 < 5)
        $this->post('/client/production/orders/' . $o->id . '/cancel')->assertSessionHas('error');
        // produksi lagi 1 meja lalu batalkan
        $this->post('/client/production/orders', ['date' => date('Y-m-d'), 'product_id' => $meja->id, 'quantity' => 1,
            'components' => [['product_id' => $kaki->id, 'quantity' => 4], ['product_id' => $papan->id, 'quantity' => 1]]])->assertRedirect();
        $o2 = ProductionOrder::latest('id')->first();
        $this->post('/client/production/orders/' . $o2->id . '/cancel')->assertSessionHas('success');
        $this->assertEquals(20, $kaki->fresh()->stock);
        $this->assertEquals(4, $meja->fresh()->stock);
        $this->assertEquals(450000, $this->bal($raw->id));
        $this->assertEquals(400000, $this->bal($fin->id));
        $this->assertNotNull($o2->fresh()->voided_at);
        $total = \App\Models\JournalDetail::sum('debit') - \App\Models\JournalDetail::sum('credit');
        $this->assertEqualsWithDelta(0, $total, 0.01);
    }
}
