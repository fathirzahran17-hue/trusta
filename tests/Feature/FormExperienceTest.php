<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kenyamanan pengisian form: isi tidak hilang saat ada error dan semua form terbuka dengan susunan yang sama. */
class FormExperienceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Product $product;
    private Supplier $supplier;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->post('/register', ['company_name' => 'PT Mebel', 'name' => 'Owner', 'email' => 'm@uji.test', 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ', 'business_type' => 'manufaktur']);
        $this->user = User::where('email', 'm@uji.test')->firstOrFail();
        $this->user->company->forceFill(['is_premium' => true])->save();
        $this->actingAs($this->user);
        $cid = $this->user->company_id;
        $this->supplier = Supplier::create(['company_id' => $cid, 'name' => 'Pemasok', 'is_active' => true]);
        $this->customer = Customer::create(['company_id' => $cid, 'name' => 'Pelanggan', 'default_term_days' => 0, 'is_active' => true]);
        $this->product = Product::create(['company_id' => $cid, 'sku' => 'MEJA', 'name' => 'Meja Makan', 'type' => 'goods', 'item_kind' => 'barang_jadi', 'unit' => 'pcs', 'sell_price' => 1000, 'buy_price' => 500, 'is_active' => true,
            'inventory_account_id' => Account::where('company_id', $cid)->where('name', 'Persediaan Barang Jadi')->first()->id]);
    }

    public function test_lines_come_back_when_the_server_rejects_the_invoice(): void
    {
        // stok 0: faktur langsung ditolak
        $page = $this->from('/client/sales-invoices/create')->followingRedirects()->post('/client/sales-invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'), 'notes' => 'Catatan penting', 'discount_percent' => 5,
            'products' => [(string) $this->product->id], 'quantities' => ['7'], 'prices' => ['1234'], 'discounts' => ['3'], 'row_descriptions' => ['Warna coklat'],
        ]);

        $page->assertOk()->assertSee('tidak cukup');
        $page->assertSee('Catatan penting')->assertSee('value="5"', false);
        $page->assertSee('"quantity":"7"', false)->assertSee('"price":"1234"', false)->assertSee('"discount":"3"', false)->assertSee('Warna coklat');
    }

    public function test_validation_errors_are_listed_in_indonesian(): void
    {
        $page = $this->from('/client/purchases/orders/create')->followingRedirects()->post('/client/purchases/orders', ['order_date' => date('Y-m-d')]);

        $page->assertOk()->assertSee('Ada isian yang perlu diperbaiki')->assertSee('wajib diisi')->assertDontSee('The supplier id');
    }

    public function test_error_message_appears_once(): void
    {
        $page = $this->from('/client/sales-invoices/create')->followingRedirects()->post('/client/sales-invoices', [
            'customer_id' => $this->customer->id, 'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [7], 'prices' => [1234], 'discounts' => [0],
        ]);

        $this->assertSame(1, substr_count($page->getContent(), 'tidak cukup (tersedia'));
    }

    public function test_transaction_forms_share_the_same_layout(): void
    {
        foreach (['sales-invoices/create', 'purchase-invoices/create', 'purchases/orders/create', 'sales/orders/create', 'purchases/receipts/create', 'sales/deliveries/create',
            'sales/returns/create', 'purchases/returns/create', 'cash-bank/payments', 'cash-bank/receipts', 'cash-bank/transfers', 'sales/down-payments/create', 'purchases/down-payments/create'] as $url) {
            $this->get('/client/' . $url)->assertOk()->assertSee('doc-card', false)->assertSee('doc-actions', false);
        }
        foreach (['sales-invoices/create', 'purchase-invoices/create', 'purchases/orders/create', 'sales/orders/create', 'purchases/returns/create', 'sales/returns/create', 'cash-bank/payments', 'cash-bank/receipts'] as $url) {
            $this->get('/client/' . $url)->assertSee('doc-totals', false)->assertSee('id="displayGrandTotal"', false)->assertSee('data-total-mirror', false);
        }
        foreach (['sales-invoices/create', 'purchase-invoices/create', 'purchases/orders/create', 'sales/orders/create'] as $url) {
            $this->get('/client/' . $url)->assertSee('id="displaySubtotal"', false)->assertSee('id="displayTax"', false)->assertSee('id="discount_percent"', false)->assertSee('id="tax_id"', false);
        }
    }

    public function test_master_forms_render(): void
    {
        foreach (['customers/create', 'suppliers/create', 'accounts/create', 'fixed-assets/create', 'taxes/create', 'users/create', 'project-jobs/create', 'inventory/create', 'inventory/services/create',
            'journals/create', 'inventory/adjustments', 'inventory/requests', 'inventory/stock-opname', 'production/boms', 'production/orders', 'production/requirements', 'settings/preferences', 'billing'] as $url) {
            $this->get('/client/' . $url)->assertOk();
        }
        foreach (['customers/' . $this->customer->id . '/edit', 'suppliers/' . $this->supplier->id . '/edit'] as $url) {
            $this->get('/client/' . $url)->assertOk()->assertSee('doc-actions', false);
        }
        $this->get('/client/inventory/' . $this->product->id . '/edit')->assertOk()->assertSee('Jenis Barang');
    }
}
