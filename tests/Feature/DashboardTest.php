<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CoaSeeder;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Angka dashboard: satuan naik sendiri dan isi kartunya cocok dengan pembukuan. */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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
        $this->actingAs($this->user);
    }

    private function journal(array $lines): void
    {
        $journal = Journal::create(['company_id' => $this->user->company_id, 'ref_number' => 'J-' . uniqid(), 'type' => 'general', 'transaction_date' => date('Y-m-d'), 'description' => 'uji', 'status' => 'posted', 'created_by' => $this->user->id]);
        foreach ($lines as [$name, $debit, $credit]) {
            $journal->details()->create(['account_id' => Account::where('company_id', $this->user->company_id)->where('name', $name)->firstOrFail()->id, 'debit' => $debit, 'credit' => $credit]);
        }
    }

    public function test_compact_amount_moves_to_the_next_unit(): void
    {
        $cases = [
            [0, '0'], [999, '999'], [1000, '1 Rb'], [1500, '1,5 Rb'], [999999, '1 Jt'], [1000000, '1 Jt'], [56700000, '56,7 Jt'],
            [999000000, '999 Jt'], [999990000, '999,99 Jt'], [999999000, '1 M'], [1000000000, '1 M'], [1250000000, '1,25 M'],
            [999000000000, '999 M'], [1000000000000, '1 T'], [-1250000000, '-1,25 M'],
        ];
        foreach ($cases as [$value, $expected]) {
            $this->assertSame($expected, Money::compact($value), (string) $value);
        }
    }

    public function test_compact_units_follow_the_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('1,25 B', Money::compact(1250000000));
        $this->assertSame('56,7 M', Money::compact(56700000));
        $this->assertSame('1,5 K', Money::compact(1500));
    }

    public function test_narrow_columns_stay_exact_below_one_billion(): void
    {
        $this->assertSame('999.000.000,00', Money::fit(999000000));
        $this->assertSame('1,5 M', Money::fit(1500000000));
    }

    public function test_dashboard_shows_billions_as_miliar_and_keeps_the_exact_value_on_hover(): void
    {
        $this->journal([['Kas', 1500000000, 0], ['Pendapatan Penjualan', 0, 1500000000]]);

        $this->get(route('client.dashboard'))->assertOk()
            ->assertSee('Rp 1,5 M')
            ->assertSee('Rp 1.500.000.000,00')
            ->assertSee('+1,5 M')
            ->assertDontSee('1000 Jt')
            ->assertDontSee("'Jt';", false);
    }

    public function test_amounts_below_one_billion_keep_their_full_form_in_the_cash_flow_title(): void
    {
        $cash = Account::where('company_id', $this->user->company_id)->where('name', 'Kas')->firstOrFail();
        $cash->forceFill(['is_cash_bank' => true])->save();
        $this->journal([['Kas', 999000000, 0], ['Pendapatan Penjualan', 0, 999000000]]);

        $this->get(route('client.dashboard'))->assertOk()->assertSee('+999.000.000')->assertDontSee('+999 Jt');
    }

    public function test_loss_is_labelled_as_a_loss(): void
    {
        $this->journal([['Beban Sewa', 2500000, 0], ['Kas', 0, 2500000]]);

        $response = $this->get(route('client.dashboard'))->assertOk();
        $response->assertSee('Rugi Bersih')->assertSee('-2,5 Jt')->assertDontSee('Laba Bersih');
    }

    public function test_expense_card_total_matches_the_books_when_there_are_many_accounts(): void
    {
        foreach (['Beban Sewa' => 600000, 'Beban Gaji & Upah' => 500000, 'Beban BPJS' => 400000, 'Beban Asuransi' => 300000, 'Beban Perlengkapan Kantor' => 200000, 'Beban Perizinan & Legal' => 100000] as $name => $amount) {
            $this->journal([[$name, $amount, 0], ['Kas', 0, $amount]]);
        }

        $view = $this->get(route('client.dashboard'))->assertOk()->viewData('topExpenses');

        $this->assertCount(5, $view);
        $this->assertSame('Lainnya', $view->last()->name);
        $this->assertEquals(300000, $view->last()->total);
        $this->assertEquals(2100000, $view->sum('total'));
    }

    public function test_opening_balance_is_not_counted_as_cash_coming_in(): void
    {
        $cash = Account::where('company_id', $this->user->company_id)->where('name', 'Kas')->firstOrFail();
        $cash->forceFill(['is_cash_bank' => true])->save();
        $this->post('/client/accounts/beginning-balance', ['date' => date('Y-m-d'), 'balances' => [$cash->id => 5000000]])->assertSessionHas('success');
        $this->journal([['Kas', 200000, 0], ['Pendapatan Penjualan', 0, 200000]]);

        $view = $this->get(route('client.dashboard'))->assertOk();

        $this->assertEquals(200000, $view->viewData('cashTotals')['in']);
        $this->assertTrue($view->viewData('recentActivities')->contains(fn ($a) => $a->act_label === 'Saldo Awal'));
    }

    public function test_empty_dashboard_still_renders(): void
    {
        $this->get(route('client.dashboard'))->assertOk()->assertSee('Belum ada data beban')->assertSee('Aktivitas Terakhir Anda');
    }
}
