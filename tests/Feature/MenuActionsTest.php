<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\FixedAssetCategory;
use App\Models\Journal;
use App\Models\PaymentTerm;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProjectJob;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CoaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Aksi menu yang sebelumnya belum pernah diuji: master data, kas dan bank, pengguna, serta batas paket. */
class MenuActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;
    private User $other;
    private Plan $plan;
    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plan = Plan::create(['name' => 'Dasar', 'price' => 0, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 3,
            'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => false, 'trial_days' => 0, 'is_active' => true]);
        [$this->company, $this->user, $this->subscription] = $this->makeCompany('PT Uji', 'a@example.com');
        [, $this->other] = $this->makeCompany('PT Lain', 'b@example.com');
        $this->actingAs($this->user);
    }

    private function makeCompany(string $name, string $email): array
    {
        $company = Company::create(['name' => $name, 'email' => $email, 'fiscal_year_start' => 1, 'is_active' => true]);
        $company->forceFill(['is_premium' => true])->save();
        $subscription = Subscription::create(['company_id' => $company->id, 'plan_id' => $this->plan->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'amount_paid' => 0]);
        $user = User::create(['company_id' => $company->id, 'name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'), 'role' => 'client_admin', 'is_active' => true]);
        CoaSeeder::seed($company->id);

        return [$company, $user, $subscription];
    }

    private function acc(string $name, ?int $companyId = null): Account
    {
        return Account::withoutGlobalScopes()->where('company_id', $companyId ?? $this->company->id)->where('name', $name)->firstOrFail();
    }

    private function assertBalanced(Journal $journal, float $total): void
    {
        $details = $journal->details()->get();
        $this->assertEquals($total, round($details->sum('debit'), 2));
        $this->assertEquals($total, round($details->sum('credit'), 2));
    }

    public function test_cash_payment_posts_a_balanced_journal(): void
    {
        $this->post('/client/cash-bank/payments', ['pay_from' => $this->acc('Kas')->id, 'transaction_date' => date('Y-m-d'), 'description' => 'Sewa',
            'expense_accounts' => [$this->acc('Beban Sewa')->id], 'amounts' => [250000]])->assertSessionHas('success');

        $journal = Journal::firstOrFail();
        $this->assertBalanced($journal, 250000);
        $this->assertEquals(250000, $journal->details()->where('account_id', $this->acc('Beban Sewa')->id)->value('debit'));
        $this->assertEquals(250000, $journal->details()->where('account_id', $this->acc('Kas')->id)->value('credit'));
    }

    public function test_cash_payment_rejects_accounts_of_another_company(): void
    {
        $foreignCash = $this->acc('Kas', $this->other->company_id);
        $foreignRent = $this->acc('Beban Sewa', $this->other->company_id);

        $this->post('/client/cash-bank/payments', ['pay_from' => $foreignCash->id, 'transaction_date' => date('Y-m-d'), 'description' => 'x',
            'expense_accounts' => [$this->acc('Beban Sewa')->id], 'amounts' => [1000]])->assertSessionHasErrors('pay_from');

        // Rekening lawan tidak punya aturan exists, jadi ini ditahan di baris jurnal
        $this->post('/client/cash-bank/payments', ['pay_from' => $this->acc('Kas')->id, 'transaction_date' => date('Y-m-d'), 'description' => 'x',
            'expense_accounts' => [$foreignRent->id], 'amounts' => [1000]])->assertRedirect();

        $this->assertSame(0, \App\Models\JournalDetail::where('account_id', $foreignRent->id)->count());
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('company_id', $this->company->id)->count());
    }

    public function test_cash_transfer_moves_money_between_own_accounts_only(): void
    {
        $kas = $this->acc('Kas');
        $bank = $this->acc('Bank');

        $this->post('/client/cash-bank/transfers', ['from_account' => $kas->id, 'to_account' => $bank->id, 'transaction_date' => date('Y-m-d'), 'amount' => 75000])->assertSessionHas('success');
        $journal = Journal::firstOrFail();
        $this->assertBalanced($journal, 75000);
        $this->assertEquals(75000, $journal->details()->where('account_id', $bank->id)->value('debit'));

        $this->post('/client/cash-bank/transfers', ['from_account' => $kas->id, 'to_account' => $kas->id, 'transaction_date' => date('Y-m-d'), 'amount' => 1])->assertSessionHasErrors('to_account');
        $this->post('/client/cash-bank/transfers', ['from_account' => $kas->id, 'to_account' => $this->acc('Bank', $this->other->company_id)->id, 'transaction_date' => date('Y-m-d'), 'amount' => 1])->assertSessionHasErrors('to_account');
        $this->assertSame(1, Journal::count());
    }

    public function test_payment_terms_can_be_added_and_removed(): void
    {
        $this->post(route('client.settings.payment_terms.store'), ['name' => 'Net 21', 'days' => 21])->assertRedirect();
        $term = PaymentTerm::where('name', 'Net 21')->firstOrFail();
        $this->assertSame((int) $this->company->id, (int) $term->company_id);

        $this->delete(route('client.settings.payment_terms.destroy', $term))->assertRedirect();
        $this->assertNull(PaymentTerm::find($term->id));
    }

    public function test_customer_category_links_to_customers_and_filters_the_list(): void
    {
        $this->post(route('client.customers.categories.store'), ['name' => 'Grosir'])->assertRedirect();
        $category = CustomerCategory::firstOrFail();

        $this->post('/client/customers', ['name' => 'Budi', 'company_name' => 'CV Budi Jaya', 'customer_category_id' => $category->id])->assertSessionHasNoErrors();
        $this->post('/client/customers', ['name' => 'Sari'])->assertSessionHasNoErrors();
        $budi = Customer::where('name', 'Budi')->firstOrFail();
        $this->assertSame((int) $category->id, (int) $budi->customer_category_id);
        $this->assertSame('CV Budi Jaya', $budi->company_name);

        $this->get('/client/customers')->assertOk()->assertSee('CV Budi Jaya')->assertSee('Grosir')->assertSee('Sari');
        $this->get('/client/customers?kategori=' . $category->id)->assertOk()->assertSee('Budi')->assertDontSee('Sari');
        $this->get(route('client.customers.edit', $budi))->assertOk()->assertSee('Grosir');

        $this->delete(route('client.customers.categories.destroy', $category))->assertRedirect();
        $this->assertNull($budi->fresh()->customer_category_id);
        $this->assertSame(1, Customer::where('name', 'Budi')->count());
    }

    public function test_supplier_category_links_to_suppliers_and_filters_the_list(): void
    {
        $this->post(route('client.suppliers.categories.store'), ['name' => 'Impor'])->assertRedirect();
        $category = SupplierCategory::firstOrFail();

        $this->post('/client/suppliers', ['name' => 'Agus', 'company_name' => 'PT Agus', 'supplier_category_id' => $category->id])->assertSessionHasNoErrors();
        $this->post('/client/suppliers', ['name' => 'Dewi'])->assertSessionHasNoErrors();

        $this->get('/client/suppliers?kategori=' . $category->id)->assertOk()->assertSee('PT Agus')->assertDontSee('Dewi');

        $this->delete(route('client.suppliers.categories.destroy', $category))->assertRedirect();
        $this->assertNull(Supplier::where('name', 'Agus')->first()->supplier_category_id);
    }

    public function test_category_of_another_company_cannot_be_assigned(): void
    {
        $foreign = CustomerCategory::withoutGlobalScopes()->create(['company_id' => $this->other->company_id, 'name' => 'Asing']);
        $this->post('/client/customers', ['name' => 'Budi', 'customer_category_id' => $foreign->id])->assertSessionHasErrors('customer_category_id');
    }

    public function test_brand_links_to_goods(): void
    {
        $this->post(route('client.inventory.brands.store'), ['name' => 'Merek A'])->assertRedirect();
        $brand = Brand::firstOrFail();
        $this->post(route('client.inventory.units.store'), ['name' => 'Karton', 'abbreviation' => 'ktn'])->assertRedirect();
        $this->assertSame(1, Unit::where('abbreviation', 'ktn')->count());

        $this->get(route('client.inventory.create'))->assertOk()->assertSee('Merek A');
        $this->post('/client/inventory', ['sku' => 'BR-1', 'name' => 'Barang Bermerek', 'sell_price' => 1000, 'brand_id' => $brand->id, 'item_kind' => 'barang_dagang'])->assertSessionHasNoErrors();
        $product = Product::where('sku', 'BR-1')->firstOrFail();
        $this->assertSame((int) $brand->id, (int) $product->brand_id);
        $this->get('/client/inventory')->assertOk()->assertSee('Merek A');
        $this->get(route('client.inventory.show', $product))->assertOk()->assertSee('Merek A');

        $foreign = Brand::withoutGlobalScopes()->create(['company_id' => $this->other->company_id, 'name' => 'Asing']);
        $this->post('/client/inventory', ['sku' => 'BR-2', 'name' => 'Lain', 'sell_price' => 1000, 'brand_id' => $foreign->id, 'item_kind' => 'barang_dagang'])->assertSessionHasErrors('brand_id');

        $this->delete(route('client.inventory.brands.destroy', $brand))->assertRedirect();
        $this->assertNull($product->fresh()->brand_id);
    }

    public function test_product_categories_crud(): void
    {
        $this->post(route('client.inventory.categories.store'), ['name' => 'Alat'])->assertRedirect();
        $category = ProductCategory::firstOrFail();
        $this->put(route('client.inventory.categories.update', $category), ['name' => 'Peralatan'])->assertRedirect();
        $this->assertSame('Peralatan', $category->fresh()->name);
        $this->delete(route('client.inventory.categories.destroy', $category))->assertRedirect();
        $this->assertNull(ProductCategory::find($category->id));
    }

    public function test_warehouses_can_be_added_and_removed(): void
    {
        $this->post(route('client.inventory.warehouses.store'), ['code' => 'G1', 'name' => 'Gudang Satu'])->assertRedirect();
        $warehouse = Warehouse::where('code', 'G1')->firstOrFail();
        $this->delete(route('client.inventory.warehouses.destroy', $warehouse))->assertRedirect();
        $this->assertNull(Warehouse::find($warehouse->id));
    }

    public function test_stock_transfer_says_clearly_that_it_is_not_supported(): void
    {
        $this->post(route('client.inventory.transfers.store'), [])->assertSessionHas('error');
    }

    public function test_fixed_asset_categories_can_be_added_and_removed(): void
    {
        $this->post(route('client.fixed_assets.categories.store'), ['name' => 'Kendaraan', 'depreciation_method' => 'straight_line', 'useful_life_years' => 8])->assertRedirect();
        $category = FixedAssetCategory::firstOrFail();
        $this->delete(route('client.fixed_assets.categories.destroy', $category))->assertRedirect();
        $this->assertNull(FixedAssetCategory::find($category->id));
    }

    public function test_project_jobs_crud_and_tenant_guard(): void
    {
        $this->post(route('client.project-jobs.store'), ['code' => 'J1', 'name' => 'Proyek A'])->assertRedirect();
        $job = ProjectJob::firstOrFail();
        $this->get(route('client.project-jobs.show', $job))->assertOk()->assertSee('Proyek A');
        $this->put(route('client.project-jobs.update', $job), ['code' => 'J1', 'name' => 'Proyek B'])->assertRedirect();
        $this->assertSame('Proyek B', $job->fresh()->name);

        $this->actingAs($this->other);
        $this->delete(route('client.project-jobs.destroy', $job->id))->assertStatus(404);
        $this->actingAs($this->user);

        $this->delete(route('client.project-jobs.destroy', $job))->assertRedirect();
        $this->assertNull(ProjectJob::find($job->id));
    }

    public function test_taxes_crud_cannot_move_a_tax_or_use_foreign_accounts(): void
    {
        $sales = $this->acc('Pajak Keluaran (PPN)');
        $purchase = $this->acc('Pajak Masukan (PPN)');
        $this->post(route('client.taxes.store'), ['name' => 'PPN Uji', 'rate' => 11, 'sales_tax_account_id' => $sales->id, 'purchase_tax_account_id' => $purchase->id, 'is_active' => '1'])->assertRedirect();
        $tax = Tax::where('name', 'PPN Uji')->firstOrFail();

        $this->put(route('client.taxes.update', $tax), ['name' => 'PPN Baru', 'rate' => 12, 'company_id' => $this->other->company_id, 'is_active' => '1'])->assertRedirect();
        $fresh = $tax->fresh();
        $this->assertSame('PPN Baru', $fresh->name);
        $this->assertEquals(12, $fresh->rate);
        $this->assertSame((int) $this->company->id, (int) $fresh->company_id);

        $this->post(route('client.taxes.store'), ['name' => 'Asing', 'rate' => 11, 'sales_tax_account_id' => $this->acc('Pajak Keluaran (PPN)', $this->other->company_id)->id])->assertSessionHasErrors('sales_tax_account_id');

        $this->delete(route('client.taxes.destroy', $tax))->assertRedirect();
        $this->assertNull(Tax::find($tax->id));
    }

    public function test_staff_users_crud_and_the_plan_limit(): void
    {
        $data = fn (string $email) => ['name' => 'Staf', 'email' => $email, 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ'];
        $this->post(route('client.users.store'), $data('s1@example.com'))->assertSessionHas('success');
        $this->post(route('client.users.store'), $data('s2@example.com'))->assertSessionHas('success');
        // Admin + 2 staf = 3, batas paket
        $this->post(route('client.users.store'), $data('s3@example.com'))->assertSessionHas('error');
        $this->assertNull(User::where('email', 's3@example.com')->first());

        $staff = User::where('email', 's2@example.com')->firstOrFail();
        $this->assertSame('client_staff', $staff->role);
        $this->put(route('client.users.update', $staff), ['name' => 'Staf Dua', 'email' => 's2@example.com'])->assertSessionHas('success');
        $this->assertSame('Staf Dua', $staff->fresh()->name);
        $this->assertFalse((bool) $staff->fresh()->is_active);

        $this->post(route('client.users.store'), $data('s4@example.com'))->assertSessionHas('success');
        // Sudah penuh lagi: mengaktifkan kembali ikut ditahan
        $this->put(route('client.users.update', $staff), ['name' => 'Staf Dua', 'email' => 's2@example.com', 'is_active' => '1'])->assertSessionHas('error');
        $this->assertFalse((bool) $staff->fresh()->is_active);

        $this->delete(route('client.users.destroy', $staff))->assertRedirect();
        $this->assertNull(User::find($staff->id));

        $this->actingAs($this->other);
        $this->delete(route('client.users.destroy', User::where('email', 's1@example.com')->first()->id))->assertStatus(403);
    }

    public function test_plan_without_export_blocks_every_export(): void
    {
        $this->plan->update(['has_export' => false]);

        $this->get(route('client.reports.profit-loss'))->assertOk();
        $this->get(route('client.reports.profit-loss', ['export' => 'excel']))->assertRedirect()->assertSessionHas('premium_denied');
        $this->get('/client/journals?export=csv')->assertRedirect()->assertSessionHas('premium_denied');
    }

    public function test_plan_without_reports_or_cash_bank_blocks_those_menus(): void
    {
        $this->plan->update(['has_reports' => false, 'has_cash_bank' => false]);

        $this->get(route('client.reports.index'))->assertRedirect(route('client.dashboard'))->assertSessionHas('premium_denied');
        $this->get(route('client.cash-bank.transfers'))->assertRedirect(route('client.dashboard'))->assertSessionHas('premium_denied');
        $this->get('/client/customers')->assertOk();
    }

    public function test_company_without_a_running_plan_is_not_limited(): void
    {
        $this->plan->update(['has_reports' => false, 'has_export' => false, 'max_users' => 1]);
        // Premium yang diatur manual oleh super admin, tanpa catatan langganan
        $this->subscription->delete();

        $this->get(route('client.reports.index'))->assertOk();
        $this->post(route('client.users.store'), ['name' => 'Staf', 'email' => 's9@example.com', 'password' => 'Rahasia#2026xQ', 'password_confirmation' => 'Rahasia#2026xQ'])->assertSessionHas('success');
    }

    public function test_billing_page_no_longer_promises_automatic_backup(): void
    {
        $this->plan->update(['has_auto_backup' => true]);
        $this->get(route('client.billing'))->assertOk()->assertDontSee('Backup otomatis');
    }
}
