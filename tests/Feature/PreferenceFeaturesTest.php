<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ItemReceipt;
use App\Models\Journal;
use App\Models\PaymentTerm;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Money;
use App\Support\PaymentTerms;
use App\Support\ReportFormat;
use App\Services\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Setiap pengaturan di Preferensi harus benar-benar mengubah perilaku sistem. */
class PreferenceFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private Customer $customer;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Test', 'price' => 0, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 5,
            'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => true, 'trial_days' => 14, 'is_active' => true]);
        [$this->company, $this->user] = $this->makeCompany($plan, 'PT Uji', 'a@example.com');
        $id = $this->company->id;
        $this->customer = Customer::create(['company_id' => $id, 'name' => 'Pelanggan', 'default_term_days' => 30, 'is_active' => true]);
        $this->supplier = Supplier::create(['company_id' => $id, 'name' => 'Pemasok', 'default_term_days' => 30, 'is_active' => true]);
        $this->warehouse = Warehouse::create(['company_id' => $id, 'code' => 'WH1', 'name' => 'Gudang', 'is_active' => true]);
        $revenue = Account::where('company_id', $id)->where('name', 'Pendapatan Penjualan')->first();
        $expense = Account::where('company_id', $id)->where('name', 'like', 'Beban Transportasi%')->first();
        $this->product = Product::create(['company_id' => $id, 'name' => 'Barang', 'type' => 'goods', 'unit' => 'pcs',
            'sell_price' => 15000, 'buy_price' => 10000, 'is_active' => true, 'revenue_account_id' => $revenue->id, 'expense_account_id' => $expense->id]);
        $this->actingAs($this->user);
    }

    private function makeCompany(Plan $plan, string $name, string $email): array
    {
        $company = Company::create(['name' => $name, 'email' => $email, 'fiscal_year_start' => 1, 'is_active' => true]);
        $company->forceFill(['is_premium' => true])->save();
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'amount_paid' => 0]);
        $user = User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'), 'role' => 'client_admin', 'is_active' => true]);
        CoaSeeder::seed($company->id);

        return [$company, $user];
    }

    private function setPref(array $values): void
    {
        $this->company->forceFill($values)->save();
        $this->user->unsetRelation('company');
    }

    private function createOrder(float $qty): PurchaseOrder
    {
        $this->post('/client/purchases/orders', ['supplier_id' => $this->supplier->id, 'po_number' => 'PO-T-1', 'order_date' => date('Y-m-d'),
            'products' => [$this->product->id], 'quantities' => [$qty], 'prices' => [10000], 'discounts' => [0]])->assertRedirect();

        return PurchaseOrder::firstOrFail();
    }

    private function receive(PurchaseOrder $order, float $qty, string $number = 'IR-T-1')
    {
        return $this->post('/client/purchases/receipts', ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id,
            'purchase_order_id' => $order->id, 'receipt_number' => $number, 'receipt_date' => date('Y-m-d'), 'status' => 'received',
            'products' => [$this->product->id], 'quantities' => [$qty]]);
    }

    public function test_default_terms_drive_the_due_date_on_new_invoices(): void
    {
        $this->setPref(['default_sales_term' => 'Net 14', 'default_purchase_term' => 'Net 7']);

        $this->get(route('client.sales-invoices.create'))->assertOk()->assertSee(date('Y-m-d', strtotime('+14 days')));
        $this->get(route('client.purchase-invoices.create'))->assertOk()->assertSee(date('Y-m-d', strtotime('+7 days')));
    }

    public function test_company_terms_are_offered_and_unknown_terms_are_rejected(): void
    {
        PaymentTerm::create(['company_id' => $this->company->id, 'name' => 'Net 21', 'days' => 21]);

        $this->assertSame(21, PaymentTerms::days('Net 21', $this->company->id));
        $this->assertSame(30, PaymentTerms::days('Tidak Ada', $this->company->id));

        $url = route('client.settings.preferences.update');
        $this->post($url, ['default_sales_term' => 'Net 21'])->assertSessionHasNoErrors();
        $this->assertSame('Net 21', $this->company->fresh()->default_sales_term);
        $this->post($url, ['default_sales_term' => 'Net 999'])->assertSessionHasErrors('default_sales_term');
    }

    public function test_below_cost_warning_follows_the_setting(): void
    {
        $this->setPref(['warn_below_cost' => true]);
        $this->get(route('client.sales-invoices.create'))->assertOk()->assertSee('cost-warn')->assertSee('data-cost="10000', false);

        $this->setPref(['warn_below_cost' => false]);
        $this->get(route('client.sales-invoices.create'))->assertOk()->assertDontSee('cost-warn');
    }

    public function test_over_receiving_is_blocked_when_the_setting_is_on(): void
    {
        $this->setPref(['prevent_over_receive' => true]);
        $order = $this->createOrder(5);

        $this->receive($order, 8)->assertSessionHas('error');

        $this->assertEquals(0, $this->product->fresh()->stock);
    }

    public function test_over_receiving_is_allowed_when_the_setting_is_off(): void
    {
        $this->setPref(['prevent_over_receive' => false]);
        $order = $this->createOrder(5);

        $this->receive($order, 8)->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertEquals(8, $this->product->fresh()->stock);
        $this->assertSame('received', $order->fresh()->status);
        $this->get('/client/purchases/orders/' . $order->id)->assertOk();
    }

    public function test_receipts_within_the_order_are_unaffected_by_the_setting(): void
    {
        $this->setPref(['prevent_over_receive' => true]);
        $order = $this->createOrder(5);

        $this->receive($order, 5)->assertSessionMissing('error');

        $this->assertEquals(5, $this->product->fresh()->stock);
    }

    public function test_transactions_before_the_data_start_date_are_rejected_only_when_enabled(): void
    {
        // Mengikuti kunci Tutup Buku: berlaku untuk dokumen yang memengaruhi pembukuan, bukan pesanan
        $receipt = ['supplier_id' => $this->supplier->id, 'warehouse_id' => $this->warehouse->id, 'receipt_number' => 'IR-OLD',
            'receipt_date' => date('Y-m-d', strtotime('-1 day')), 'status' => 'received',
            'products' => [$this->product->id], 'quantities' => [1], 'prices' => [10000]];

        $this->setPref(['data_start_date' => date('Y-m-d'), 'prevent_prior_transactions' => true]);
        $this->post('/client/purchases/receipts', $receipt)->assertSessionHas('error');
        $this->assertSame(0, ItemReceipt::count());
        $this->assertEquals(0, $this->product->fresh()->stock);

        $this->setPref(['prevent_prior_transactions' => false]);
        $this->post('/client/purchases/receipts', $receipt)->assertSessionMissing('error');
        $this->assertSame(1, ItemReceipt::count());
    }

    private function journal(): Journal
    {
        return Journal::create(['company_id' => $this->company->id, 'ref_number' => 'J-1', 'type' => 'general', 'transaction_date' => date('Y-m-d'), 'description' => 'uji', 'status' => 'posted', 'created_by' => $this->user->id]);
    }

    public function test_attachment_can_be_uploaded_listed_downloaded_and_deleted(): void
    {
        Storage::fake('local');
        $journal = $this->journal();

        $this->post(route('client.attachments.store', ['journal', $journal->id]), ['file' => UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')])->assertSessionHas('success');
        $file = Attachment::firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertStringStartsWith('attachments/' . $this->company->id . '/', $file->path);
        $this->assertStringNotContainsString('nota', $file->path);

        $this->get(route('client.journals.show', $journal))->assertOk()->assertSee('nota.pdf');
        $this->get(route('client.attachments.download', $file))->assertOk();

        $this->delete(route('client.attachments.destroy', $file))->assertSessionHas('success');
        $this->assertSame(0, Attachment::count());
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_attachment_rejects_disallowed_disguised_and_oversized_files(): void
    {
        Storage::fake('local');
        $journal = $this->journal();
        $url = route('client.attachments.store', ['journal', $journal->id]);
        $this->setPref(['allowed_attachment_types' => 'PDF,PNG', 'max_attachment_size' => 1]);

        $this->post($url, ['file' => UploadedFile::fake()->create('skrip.php', 1, 'text/x-php')])->assertSessionHasErrors('file');
        $this->post($url, ['file' => UploadedFile::fake()->create('laman.html', 1, 'text/html')])->assertSessionHasErrors('file');
        $this->post($url, ['file' => UploadedFile::fake()->create('tabel.xlsx', 1, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertSessionHasErrors('file');
        $this->post($url, ['file' => UploadedFile::fake()->create('menyamar.pdf', 1, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->post($url, ['file' => UploadedFile::fake()->create('besar.pdf', 2048, 'application/pdf')])->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::count());
    }

    public function test_attachment_of_another_company_cannot_be_reached(): void
    {
        Storage::fake('local');
        $journal = $this->journal();
        $this->post(route('client.attachments.store', ['journal', $journal->id]), ['file' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]);
        $file = Attachment::firstOrFail();

        [, $other] = $this->makeCompany(Plan::firstOrFail(), 'PT Lain', 'b@example.com');
        $this->actingAs($other);

        $this->get(route('client.attachments.download', $file->id))->assertNotFound();
        $this->delete(route('client.attachments.destroy', $file->id))->assertNotFound();
        $this->post(route('client.attachments.store', ['journal', $journal->id]), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->post(route('client.attachments.store', ['tidak-ada', 1]), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->assertSame(1, Attachment::withoutGlobalScopes()->count());
    }

    public function test_preferences_only_keep_safe_attachment_types_and_a_hosting_safe_size(): void
    {
        $url = route('client.settings.preferences.update');
        $this->post($url, ['allowed_attachment_types' => 'pdf, EXE, php, png'])->assertSessionHasNoErrors();
        $this->assertSame('PDF,PNG', $this->company->fresh()->allowed_attachment_types);
        $this->post($url, ['max_attachment_size' => 50])->assertSessionHasErrors('max_attachment_size');
    }

    public function test_custom_attributes_appear_only_when_named_and_are_saved(): void
    {
        $this->get(route('client.customers.create'))->assertOk()->assertDontSee('name="custom_attr_1"', false);

        $this->setPref(['custom_customer_attr_1' => 'Wilayah', 'custom_item_attr_1' => 'Merek Lokal']);

        $this->get(route('client.customers.create'))->assertOk()->assertSee('Wilayah')->assertSee('name="custom_attr_1"', false);
        $this->post('/client/customers', ['name' => 'Baru', 'custom_attr_1' => 'Jawa Barat'])->assertRedirect();
        $this->assertSame('Jawa Barat', Customer::where('name', 'Baru')->first()->custom_attr_1);

        $this->get(route('client.inventory.edit', $this->product))->assertOk()->assertSee('Merek Lokal');
    }

    public function test_price_format_setting_changes_amounts_everywhere(): void
    {
        $this->setPref(['price_decimal_format' => '2 Angka (0.00)']);
        $this->assertSame('1.234,50', Money::fmt(1234.5));
        $this->assertSame('1.234,50', ReportFormat::money(1234.5));

        $this->setPref(['price_decimal_format' => 'Tanpa Desimal']);
        $this->assertSame(0, Money::decimals());
        $this->assertSame('1.235', Money::fmt(1234.5));
        $this->assertSame('1.235', ReportFormat::money(1234.5));
        $this->assertSame('(1.235)', ReportFormat::money(-1234.5));
    }

    public function test_preference_page_has_no_fake_approval_tab_and_saves_start_date(): void
    {
        $this->get(route('client.settings.preferences'))->assertOk()->assertDontSee('Persetujuan')->assertDontSee('notify_approver');

        $this->post(route('client.settings.preferences.update'), ['data_start_date' => '2026-01-01', 'prevent_prior_transactions' => '1'])->assertSessionHasNoErrors();

        $fresh = $this->company->fresh();
        $this->assertSame('2026-01-01', substr((string) $fresh->data_start_date, 0, 10));
        $this->assertTrue((bool) $fresh->prevent_prior_transactions);
    }
}
