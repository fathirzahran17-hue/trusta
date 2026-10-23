<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menghubungkan akun ekuitas "Laba Tahun Berjalan" dan "Laba Ditahan" dengan hasil usaha sebenarnya.
 *
 *  - Laba Tahun Berjalan = laba/rugi dari awal tahun fiskal sampai tanggal laporan.
 *  - Laba Ditahan        = laba/rugi seluruh tahun fiskal sebelumnya (+ saldo akun itu sendiri dari jurnal manual).
 *
 * Tidak perlu jurnal penutup: tiap tahun otomatis bergeser. Kalau Anda tetap membuat jurnal penutup manual
 * (pendapatan/beban -> Laba Ditahan), angkanya tidak dobel karena laba/rugi dihitung dari jurnal yang sebenarnya.
 */
class EarningsLink
{
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** [id Laba Tahun Berjalan|null, id Laba Ditahan|null] */
    public static function accountIds(int $companyId): array
    {
        return self::$cache["ids$companyId"] ??= [
            optional(AccountResolver::currentYearEarnings($companyId))->id,
            optional(AccountResolver::retainedEarnings($companyId))->id,
        ];
    }

    /** Tanggal awal tahun fiskal yang memuat $asOf. */
    public static function fiscalYearStart(int $companyId, string $asOf): string
    {
        $m = (int) (Company::withoutGlobalScopes()->whereKey($companyId)->value('fiscal_year_start') ?: 1);
        $d = Carbon::parse($asOf);
        $year = $d->month >= $m ? $d->year : $d->year - 1;

        return Carbon::create($year, $m, 1)->toDateString();
    }

    /** Laba (+) / rugi (-) bersih dari jurnal terposting pada rentang tanggal. */
    public static function profit(int $companyId, ?string $from, string $to): float
    {
        $v = DB::table('journal_details')
            ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_details.account_id')
            ->where('journals.company_id', $companyId)
            ->where('journals.status', 'posted')
            ->whereIn('accounts.type', ['revenue', 'other_income', 'cost_of_sales', 'expense', 'other_expense'])
            ->whereDate('journals.transaction_date', '<=', $to)
            ->when($from, fn ($q) => $q->whereDate('journals.transaction_date', '>=', $from))
            ->selectRaw('COALESCE(SUM(journal_details.credit - journal_details.debit), 0) as p')
            ->value('p');

        return round((float) $v, 2);
    }

    /** @return array{start:string, prior:float, current:float} */
    public static function split(int $companyId, string $asOf): array
    {
        $start = self::fiscalYearStart($companyId, $asOf);

        return [
            'start' => $start,
            'prior' => self::profit($companyId, null, Carbon::parse($start)->subDay()->toDateString()),
            'current' => self::profit($companyId, $start, $asOf),
        ];
    }

    /** Tambahan (sisi kredit) untuk saldo akun ekuitas yang terhubung; 0 untuk akun lain. */
    public static function creditAdjustment(Account $a, ?string $asOf = null): float
    {
        [$cur, $ret] = self::accountIds((int) $a->company_id);
        if ($a->id !== $cur && $a->id !== $ret) {
            return 0.0;
        }
        $s = self::split((int) $a->company_id, $asOf ?: now()->toDateString());

        return $a->id === $cur ? $s['current'] : $s['prior'];
    }
}
