<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['company_id' => null, 'name' => 'Super', 'email' => 'sa@uji.test', 'password' => bcrypt('x'), 'role' => 'super_admin', 'is_active' => true]);
    }

    private function registerClient(string $email = 'c@uji.test'): User
    {
        auth()->logout();
        $this->post('/register', ['company_name' => 'PT Klien', 'name' => 'Owner', 'email' => $email, 'password' => 'Rahasia#2026x', 'password_confirmation' => 'Rahasia#2026x']);

        return User::where('email', $email)->firstOrFail();
    }

    public function test_super_admin_sets_plans_and_settings_and_client_pays_and_gets_activated(): void
    {
        $this->actingAs($this->admin);
        foreach (['/superadmin/dashboard', '/superadmin/clients', '/superadmin/plans', '/superadmin/payments', '/superadmin/settings', '/superadmin/default-accounts'] as $u) {
            $this->get($u)->assertOk();
        }
        $this->post('/superadmin/plans', ['name' => 'Bisnis', 'price' => '150.000,00', 'price_yearly' => '1.500.000,00', 'max_users' => 5, 'features' => "Dukungan prioritas\nPelatihan",
            'has_reports' => 1, 'has_cash_bank' => 1, 'has_export' => 1, 'is_active' => 1, 'is_public' => 1])->assertSessionHas('success');
        $plan = Plan::where('name', 'Bisnis')->firstOrFail();
        $this->assertEquals(150000, (float) $plan->price);
        $this->assertEquals(1500000, $plan->yearlyPrice());
        $this->put('/superadmin/plans/' . $plan->id, ['name' => 'Bisnis', 'price' => '175.000', 'max_users' => 5, 'is_active' => 1, 'is_public' => 1])->assertSessionHas('success');
        $this->assertEquals(175000, (float) $plan->fresh()->price);
        $this->assertEquals(2100000, $plan->fresh()->yearlyPrice()); // tahunan kosong = 12 x bulanan
        $this->post('/superadmin/settings', ['bank_accounts' => 'BCA 123 a.n. TRUSTA', 'admin_whatsapp' => '0851', 'trial_days' => 14, 'trial_plan_id' => $plan->id])->assertSessionHas('success');

        // pendaftar baru dapat trial 14 hari
        $client = $this->registerClient();
        $company = $client->company->fresh();
        $this->assertTrue((bool) $company->is_premium);
        $trial = Subscription::where('company_id', $company->id)->firstOrFail();
        $this->assertSame('trial', $trial->status);
        $this->assertSame(now()->addDays(13)->toDateString(), $trial->end_date->toDateString());

        $this->actingAs($client);
        $this->get('/client/billing')->assertOk()->assertSee('Bisnis')->assertSee('BCA 123 a.n. TRUSTA')->assertSee('Masa Trial');
        // bayar tahunan dengan bukti
        $this->post('/client/billing', ['plan_id' => $plan->id, 'billing_cycle' => 'yearly', 'payer_name' => 'PT Klien', 'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg')])->assertSessionHas('success');
        $pay = SubscriptionPayment::firstOrFail();
        $this->assertEquals(2100000, (float) $pay->amount);
        $this->assertSame(12, (int) $pay->months);
        $this->get('/client/billing/payments/' . $pay->id . '/proof')->assertOk();
        // tidak bisa kirim lagi selama masih menunggu; file berbahaya ditolak
        $this->post('/client/billing', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'months' => 1, 'payer_name' => 'x', 'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('b.jpg')])->assertSessionHas('error');

        // admin menyetujui -> aktif 12 bulan, trial digantikan
        $this->actingAs($this->admin);
        $this->get('/superadmin/payments')->assertOk()->assertSee('PT Klien');
        $this->get('/superadmin/payments/' . $pay->id . '/proof')->assertOk();
        $this->post('/superadmin/payments/' . $pay->id . '/approve')->assertSessionHas('success');
        $this->assertSame('approved', $pay->fresh()->status);
        $sub = Subscription::where('company_id', $company->id)->where('status', 'active')->firstOrFail();
        $this->assertSame(now()->addMonthsNoOverflow(12)->subDay()->toDateString(), $sub->end_date->toDateString());
        $this->assertSame('cancelled', $trial->fresh()->status);
        $this->post('/superadmin/payments/' . $pay->id . '/approve')->assertSessionHas('error');

        // pembayaran berikutnya (bulanan x3) disambung setelah periode berjalan
        $this->actingAs($client);
        $this->post('/client/billing', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'months' => 3, 'payer_name' => 'PT Klien', 'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')])->assertSessionHas('success');
        $pay2 = SubscriptionPayment::latest('id')->first();
        $this->assertEquals(525000, (float) $pay2->amount);
        $this->actingAs($this->admin);
        $this->post('/superadmin/payments/' . $pay2->id . '/reject', ['admin_note' => 'Nominal tidak sesuai'])->assertSessionHas('success');
        $this->assertSame('rejected', $pay2->fresh()->status);

        // klien lain tidak bisa melihat bukti
        $other = $this->registerClient('o@uji.test');
        $this->actingAs($other);
        $this->get('/client/billing/payments/' . $pay->id . '/proof')->assertNotFound();
    }

    public function test_subscription_expires_after_grace_and_client_can_still_pay(): void
    {
        $client = $this->registerClient();
        $company = $client->company;
        $plan = Plan::create(['name' => 'Dasar', 'price' => 100000, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 2, 'is_active' => true, 'is_public' => true]);
        \App\Services\SubscriptionService::activate($company, $plan, 1, 100000);
        $this->assertTrue((bool) $company->fresh()->is_premium);
        // lewat masa toleransi
        Subscription::where('company_id', $company->id)->update(['end_date' => now()->subDays(\App\Services\SubscriptionService::GRACE_DAYS + 1)->toDateString()]);
        $this->actingAs($client);
        $this->get('/client/dashboard')->assertOk()->assertSee('Langganan tidak aktif');
        $this->assertFalse((bool) $company->fresh()->is_premium);
        $this->assertSame('expired', Subscription::where('company_id', $company->id)->value('status'));
        // menulis data ditolak, tapi halaman langganan tetap bisa mengirim pembayaran
        $this->post('/client/customers', ['name' => 'X'])->assertSessionHas('premium_denied');
        $this->post('/client/billing', ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'months' => 1, 'payer_name' => 'PT', 'paid_at' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('b.png')])->assertSessionHas('success');
        // masih dalam toleransi: tetap aktif
        $c2 = $this->registerClient('g@uji.test')->company;
        \App\Services\SubscriptionService::activate($c2, $plan, 1, 100000);
        Subscription::where('company_id', $c2->id)->update(['end_date' => now()->subDay()->toDateString()]);
        $this->assertFalse(\App\Services\SubscriptionService::expireIfOverdue($c2->fresh()));
        $this->assertTrue((bool) $c2->fresh()->is_premium);
    }

    public function test_admin_manages_client_subscription_manually_and_lists_filter(): void
    {
        $client = $this->registerClient('m@uji.test');
        $company = $client->company;
        $plan = Plan::create(['name' => 'Pro', 'price' => 200000, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 5, 'is_active' => true, 'is_public' => true]);
        $this->actingAs($this->admin);
        $this->get('/superadmin/clients?tab=inactive')->assertOk()->assertSee('PT Klien');
        $this->post('/superadmin/clients/' . $company->id . '/grant-premium', ['plan_id' => $plan->id, 'status' => 'active', 'months' => 3, 'amount_paid' => '600.000'])->assertSessionHas('success');
        $sub = Subscription::where('company_id', $company->id)->firstOrFail();
        $this->assertSame(now()->addMonthsNoOverflow(3)->subDay()->toDateString(), $sub->end_date->toDateString());
        $this->assertTrue((bool) $company->fresh()->is_premium);
        $this->get('/superadmin/clients?tab=active')->assertSee('PT Klien');
        $this->get('/superadmin/clients?tab=inactive')->assertDontSee('PT Klien');
        $this->get('/superadmin/clients?q=tidakada')->assertDontSee('PT Klien');
        $this->get('/superadmin/dashboard')->assertOk()->assertSee('Rp 200.000'); // MRR 600rb / 3 bulan
        $this->put('/superadmin/clients/' . $company->id . '/subscription', ['status' => 'expired', 'end_date' => now()->subDays(10)->toDateString()])->assertSessionHas('success');
        $this->assertFalse((bool) $company->fresh()->is_premium);
    }
}
