<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CoaSeeder;
use App\Support\UiTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ganti bahasa: mode Indonesia tidak berubah sama sekali, mode Inggris hanya mengubah teks yang tampil. */
class LanguageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        UiTranslator::reset();
        $plan = Plan::create(['name' => 'Test', 'price' => 0, 'max_accounts' => 1000, 'max_transactions' => 1000, 'max_users' => 5,
            'has_reports' => true, 'has_cash_bank' => true, 'has_export' => true, 'has_auto_backup' => true, 'trial_days' => 14, 'is_active' => true]);
        $this->company = Company::create(['name' => 'PT Uji', 'email' => 'uji@example.com', 'fiscal_year_start' => 1, 'is_active' => true]);
        $this->company->forceFill(['is_premium' => true])->save();
        Subscription::create(['company_id' => $this->company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(), 'amount_paid' => 0]);
        $this->user = User::create(['company_id' => $this->company->id, 'name' => 'Admin', 'email' => 'a@example.com', 'password' => bcrypt('password'), 'role' => 'client_admin', 'is_active' => true]);
        CoaSeeder::seed($this->company->id);
        $this->actingAs($this->user);
    }

    public function test_indonesian_is_the_default_and_pages_stay_in_indonesian(): void
    {
        $response = $this->get(route('client.reports.index'))->assertOk();

        $response->assertSee('Laporan Keuangan Terpadu')->assertSee('lang="id"', false)->assertDontSee('Integrated Financial Report');
        $this->assertSame('id', app()->getLocale());
        $this->assertStringNotContainsString('locale/dictionary', $response->getContent());
    }

    public function test_switching_language_is_saved_on_the_user_and_translates_pages(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
        $this->assertSame('en', $this->user->fresh()->locale);

        $this->get(route('client.reports.index'))->assertOk()
            ->assertSee('Integrated Financial Report')->assertSee('lang="en"', false)->assertDontSee('Laporan Keuangan Terpadu')
            ->assertSee('locale/dictionary', false);
        $this->get('/client/cash-bank/payments')->assertOk()->assertSee('Cash &amp; Bank Spending', false);

        $this->post(route('locale.update'), ['locale' => 'id'])->assertRedirect();
        $this->get(route('client.reports.index'))->assertOk()->assertSee('Laporan Keuangan Terpadu');
    }

    public function test_unknown_language_is_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->assertNull($this->user->fresh()->locale);
    }

    public function test_company_default_applies_until_the_user_chooses(): void
    {
        $this->company->forceFill(['default_language' => 'English'])->save();
        $this->user->unsetRelation('company');
        $this->get(route('client.reports.index'))->assertOk()->assertSee('Integrated Financial Report');

        $this->post(route('locale.update'), ['locale' => 'id']);
        $this->get(route('client.reports.index'))->assertOk()->assertSee('Laporan Keuangan Terpadu');
    }

    public function test_guest_choice_is_remembered_in_a_cookie(): void
    {
        auth()->logout();
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect()->assertCookie('trusta_locale', 'en');
        $this->withCookie('trusta_locale', 'en')->get('/login')->assertOk()->assertSee('Sign in to your account');
        $this->defaultCookies = [];
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda');
    }

    public function test_translator_only_touches_visible_text(): void
    {
        $t = new UiTranslator(['Simpan' => 'Save', 'Cari' => 'Search', 'Kas & Bank' => 'Cash & Bank', 'Stok :x habis' => ':x out of stock', 'Pelanggan' => 'Customer']);

        $html = $t->html('<title>Kas &amp; Bank</title><script>var a="Simpan";</script><style>.Simpan{}</style><div x-show="a > 0" data-name="Simpan" title="Simpan"><span>Simpan</span>'
            . '<input type="text" name="Simpan" value="Simpan" placeholder="Cari"><input type="submit" value="Simpan"><textarea>Simpan</textarea><option value="Simpan">Simpan</option></div>');

        $this->assertStringContainsString('<title>Cash &amp; Bank</title>', $html);
        $this->assertStringContainsString('<script>var a="Simpan";</script>', $html);
        $this->assertStringContainsString('<style>.Simpan{}</style>', $html);
        $this->assertStringContainsString('x-show="a > 0" data-name="Simpan" title="Save"', $html);
        $this->assertStringContainsString('<span>Save</span>', $html);
        $this->assertStringContainsString('name="Simpan" value="Simpan" placeholder="Search"', $html);
        $this->assertStringContainsString('<input type="submit" value="Save">', $html);
        $this->assertStringContainsString('<textarea>Simpan</textarea>', $html);
        $this->assertStringContainsString('<option value="Simpan">Save</option>', $html);

        $this->assertSame('  Save : ', $t->text('  Simpan : '));
        $this->assertSame('SAVE', $t->text('SIMPAN'));
        $this->assertSame('+ Save', $t->text('+ Simpan'));
        $this->assertSame('Meja out of stock', $t->text('Stok Meja habis'));
        $this->assertSame('5322 Save', $t->text('5322 Simpan'));
        $this->assertSame('1-1101 - Save', $t->text('1-1101 - Simpan'));
        $this->assertSame('Save · Customer', $t->text('Simpan · Pelanggan'));
        $this->assertSame('Budi Santoso', $t->text('Budi Santoso'));
        $this->assertSame('1.234,50', $t->text('1.234,50'));
        $this->assertSame('01 May 2026', $t->text('01 Mei 2026'));
        $this->assertSame('TRUSTA - Save', $t->text('TRUSTA - Simpan'));
    }

    public function test_the_most_specific_pattern_wins(): void
    {
        $t = new UiTranslator(['Pajak Keluaran :x' => 'Output Tax :x', 'Pajak Keluaran Uang Muka :x' => 'Down Payment Output Tax :x']);

        $this->assertSame('Down Payment Output Tax DP-1', $t->text('Pajak Keluaran Uang Muka DP-1'));
        $this->assertSame('Output Tax INV-1', $t->text('Pajak Keluaran INV-1'));
    }

    public function test_user_text_with_html_stays_escaped_after_translation(): void
    {
        $t = new UiTranslator(['Stok :x habis' => ':x out of stock']);

        $this->assertSame('<b>&lt;img src=x&gt; out of stock</b>', $t->html('<b>Stok &lt;img src=x&gt; habis</b>'));
    }

    public function test_exports_follow_the_language(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en']);

        $csv = $this->get(route('client.reports.trial-balance', ['export' => 'csv']))->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString('Account', $content);
        $this->assertStringNotContainsString('Nama Akun', $content);
        $this->get(route('client.reports.coretax', ['export' => 'excel']))->assertOk();
        $this->get(route('client.reports.coretax'))->assertOk()->assertSee('Domestic Sales')->assertSee('Coretax Reconciliation');
    }

    public function test_dictionary_is_valid_and_served_for_scripts(): void
    {
        $dictionary = json_decode((string) file_get_contents(lang_path('en.json')), true);
        $this->assertIsArray($dictionary);
        $this->assertGreaterThan(2500, count($dictionary));
        foreach ($dictionary as $key => $value) {
            $this->assertNotSame('', trim($value), $key);
            $this->assertSame(substr_count($key, ':x'), substr_count($value, ':x'), $key);
            $this->assertStringNotContainsString('<script', $value, $key);
        }

        $this->get(route('locale.dictionary'))->assertOk()->assertJsonFragment(['Simpan' => 'Save']);
    }

    public function test_money_format_does_not_change_in_english(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en']);
        $this->assertSame('1.234,50', \App\Support\ReportFormat::money(1234.5));
    }
}
