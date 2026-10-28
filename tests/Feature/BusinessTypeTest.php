<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use App\Services\AccountResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessTypeTest extends TestCase
{
    use RefreshDatabase;

    private function register(?string $type, string $email): Company
    {
        auth()->logout();
        $this->post('/register', array_filter(['company_name' => 'PT ' . $email, 'name' => 'Pemilik', 'email' => $email,
            'password' => 'Rahasia#2026x', 'password_confirmation' => 'Rahasia#2026x', 'business_type' => $type]));
        $u = User::where('email', $email)->firstOrFail();

        return $u->company;
    }

    public function test_registration_seeds_standard_coa_per_business_type(): void
    {
        $this->get('/register')->assertOk()->assertSee('Bidang usaha')->assertSee('Manufaktur');

        $expect = [
            'jasa' => ['has' => ['Pendapatan Jasa', 'Beban Pokok Pendapatan Jasa'], 'not' => ['Persediaan Barang Dagang', 'Persediaan Bahan Baku', 'HPP - Barang Dagang']],
            'dagang' => ['has' => ['Persediaan Barang Dagang', 'HPP - Barang Dagang', 'Beban Selisih Persediaan'], 'not' => ['Pendapatan Jasa', 'Persediaan Bahan Baku']],
            'dagang_jasa' => ['has' => ['Persediaan Barang Dagang', 'Pendapatan Jasa'], 'not' => ['Persediaan Bahan Baku']],
            'manufaktur' => ['has' => ['Persediaan Bahan Baku', 'Persediaan Barang Dalam Proses', 'Persediaan Barang Jadi', 'HPP - Barang Jadi', 'Overhead Pabrik Dibebankan ke Produksi'], 'not' => ['Persediaan Barang Dagang']],
        ];
        foreach ($expect as $type => $e) {
            $c = $this->register($type, $type . '@uji.test');
            $this->assertSame($type, $c->business_type);
            $names = Account::withoutGlobalScopes()->where('company_id', $c->id)->pluck('name')->all();
            foreach ($e['has'] as $n) $this->assertContains($n, $names, "$type harus punya $n");
            foreach ($e['not'] as $n) $this->assertNotContains($n, $names, "$type tidak perlu $n");
            // tidak ada akun dobel
            $this->assertSame(count($names), count(array_unique($names)), "$type: nama akun dobel");
            // akun sistem untuk jurnal otomatis tersedia
            foreach (['receivable', 'payable', 'salesAdvance', 'purchaseAdvance', 'outputTax', 'inputTax', 'retainedEarnings', 'currentYearEarnings', 'depreciationExpense', 'accumulatedDepreciation'] as $fn) {
                $this->assertNotNull(AccountResolver::$fn($c->id), "$type: $fn");
            }
            if ($type !== 'jasa') {
                $this->assertNotNull(AccountResolver::inventory($c->id), "$type: inventory");
                $this->assertNotNull(AccountResolver::cogs($c->id), "$type: cogs");
            }
            $this->assertSame(1, Account::withoutGlobalScopes()->where('company_id', $c->id)->where('type', 'equity')->where('name', 'like', '%Laba Ditahan%')->count());
            $this->assertTrue(Account::withoutGlobalScopes()->where('company_id', $c->id)->where('is_cash_bank', true)->exists(), "$type: kas/bank");
            $this->assertTrue(Account::withoutGlobalScopes()->where('company_id', $c->id)->where('is_settlement_deduction', true)->exists(), "$type: akun potongan");
        }
        $this->assertSame('manufaktur', Company::where('name', 'PT manufaktur@uji.test')->value('business_type'));
        // tanpa memilih -> Dagang & Jasa
        $this->assertSame('dagang_jasa', $this->register(null, 'skip@uji.test')->business_type);
        $this->actingAs(User::where('email', 'manufaktur@uji.test')->first());
        $this->assertSame('Persediaan Barang Jadi', AccountResolver::inventory(Company::where('name', 'PT manufaktur@uji.test')->value('id'))->name);
    }

    public function test_menu_follows_business_type_and_switching_adds_missing_accounts(): void
    {
        $c = $this->register('jasa', 'jasa2@uji.test');
        $u = User::where('email', 'jasa2@uji.test')->first();
        $c->forceFill(['is_premium' => true])->save();
        $this->actingAs($u);
        $html = $this->get('/client/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Pengiriman Barang', $html);
        $this->assertStringNotContainsString('Stok Opname', $html);
        $this->assertStringContainsString('Daftar Jasa', $html);

        $this->get('/client/settings/preferences?tab=bidang')->assertOk()->assertSee('Bidang Usaha');
        $this->post('/client/settings/business-type', ['business_type' => 'dagang_jasa'])->assertSessionHas('success');
        $this->assertSame('dagang_jasa', $c->fresh()->business_type);
        $names = Account::withoutGlobalScopes()->where('company_id', $c->id)->pluck('name')->all();
        $this->assertContains('Persediaan Barang Dagang', $names);
        $this->assertSame(count($names), count(array_unique($names)));
        $html = $this->get('/client/dashboard')->getContent();
        $this->assertStringContainsString('Pengiriman Barang', $html);
        $this->assertStringContainsString('Stok Opname', $html);
    }

    public function test_super_admin_manages_coa_template_per_industry(): void
    {
        $admin = User::create(['company_id' => null, 'name' => 'Super', 'email' => 'super@uji.test', 'password' => bcrypt('x'), 'role' => 'super_admin', 'is_active' => true]);
        $this->actingAs($admin);
        $this->get('/superadmin/default-accounts')->assertOk()->assertSee('Persediaan Bahan Baku');
        $this->get('/superadmin/default-accounts?tab=jasa')->assertOk()->assertDontSee('Persediaan Bahan Baku')->assertSee('Pendapatan Jasa');
        $this->post('/superadmin/default-accounts', ['code' => '6-2050', 'name' => 'Beban Bahan Bakar Armada', 'type' => 'expense', 'normal_balance' => 'debit',
            'parent_id' => \App\Models\DefaultAccount::where('code', '6-2000')->value('id'), 'industries' => ['dagang']])->assertSessionHas('success');
        $this->assertSame('dagang', \App\Models\DefaultAccount::where('code', '6-2050')->value('industries'));
        auth()->logout();
        $d = $this->register('dagang', 'dg@uji.test');
        $this->assertTrue(Account::withoutGlobalScopes()->where('company_id', $d->id)->where('name', 'Beban Bahan Bakar Armada')->exists());
        $j = $this->register('jasa', 'js@uji.test');
        $this->assertFalse(Account::withoutGlobalScopes()->where('company_id', $j->id)->where('name', 'Beban Bahan Bakar Armada')->exists());
        $this->actingAs($admin);
        $this->post('/superadmin/default-accounts/reset')->assertRedirect();
        $this->assertFalse(\App\Models\DefaultAccount::where('code', '6-2050')->exists());
    }
}
