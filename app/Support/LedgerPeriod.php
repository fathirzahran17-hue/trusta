<?php

namespace App\Support;

use App\Models\Account;
use App\Models\JournalDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filter periode untuk riwayat mutasi akun (Kelola Kas & Buku Besar).
 * Saldo awal periode = saldo awal akun + mutasi sebelum tanggal "Dari".
 */
class LedgerPeriod
{
    /** @return array{from:?string,to:?string,preset:string} */
    public static function resolve(Request $request): array
    {
        // Pilihan terakhir diingat selama sesi, agar tidak kembali ke "Bulan ini" setiap membuka halaman / selesai input.
        $explicit = $request->filled('from') || $request->filled('to') || $request->filled('range');
        $session = $request->hasSession() ? $request->session() : null;
        if (!$explicit && $session && is_array($saved = $session->get('ledger_period'))) {
            $request = new Request($saved);
        }
        $result = self::compute($request);
        if ($explicit && $session) {
            $session->put('ledger_period', array_filter($request->only(['range', 'from', 'to']), fn ($v) => $v !== null && $v !== ''));
        }

        return $result;
    }

    private static function compute(Request $request): array
    {
        $today = Carbon::today();
        $preset = (string) $request->input('range', '');

        if ($request->filled('from') || $request->filled('to')) {
            $from = self::date($request->input('from'));
            $to = self::date($request->input('to'));
            if ($from && $to && $from > $to) { [$from, $to] = [$to, $from]; }
            return ['from' => $from, 'to' => $to, 'preset' => 'custom'];
        }

        switch ($preset) {
            case 'all':
                return ['from' => null, 'to' => null, 'preset' => 'all'];
            case 'last_month':
                $m = $today->copy()->subMonthNoOverflow();
                return ['from' => $m->startOfMonth()->toDateString(), 'to' => $m->endOfMonth()->toDateString(), 'preset' => 'last_month'];
            case 'year':
                return ['from' => $today->copy()->startOfYear()->toDateString(), 'to' => $today->copy()->endOfYear()->toDateString(), 'preset' => 'year'];
            case 'today':
                return ['from' => $today->toDateString(), 'to' => $today->toDateString(), 'preset' => 'today'];
            default:
                return ['from' => $today->copy()->startOfMonth()->toDateString(), 'to' => $today->copy()->endOfMonth()->toDateString(), 'preset' => 'month'];
        }
    }

    private static function date($v): ?string
    {
        if (!$v) return null;
        try { return Carbon::parse($v)->toDateString(); } catch (\Throwable $e) { return null; }
    }

    /** Tampilkan transaksi yang dibatalkan (asli + jurnal baliknya)? Default disembunyikan; pilihan diingat selama sesi. */
    public static function showVoided(Request $request): bool
    {
        if ($request->has('void')) {
            $on = $request->boolean('void');
            if ($request->hasSession()) $request->session()->put('ledger_show_void', $on);

            return $on;
        }

        return $request->hasSession() ? (bool) $request->session()->get('ledger_show_void', false) : false;
    }

    /**
     * Id jurnal yang dibatalkan beserta jurnal baliknya (saling meniadakan), untuk disembunyikan dari riwayat & saldo berjalan.
     * Ditautkan lewat reverses_journal_id; jurnal balik lama yang belum tertaut dicocokkan dari keterangannya ("Pembatalan ...").
     */
    public static function cancelledPairIds(int $companyId): array
    {
        $DB = \Illuminate\Support\Facades\DB::class;
        $ids = [];
        foreach ($DB::table('journals')->where('company_id', $companyId)->whereNotNull('reverses_journal_id')->get(['id', 'reverses_journal_id']) as $r) {
            $ids[] = (int) $r->id; $ids[] = (int) $r->reverses_journal_id;
        }
        // cadangan untuk jurnal balik lama yang belum ditautkan
        foreach ($DB::table('journals')->where('company_id', $companyId)->whereNull('reverses_journal_id')->where('ref_number', 'like', 'VOID-%')->get(['id', 'description']) as $r) {
            $desc = (string) $r->description;
            $orig = null;
            if (preg_match('/^Pembatalan faktur (.+)$/', $desc, $m)) {
                $orig = $DB::table('sales_invoices')->where('company_id', $companyId)->where('invoice_number', $m[1])->value('journal_id')
                    ?: $DB::table('purchase_invoices')->where('company_id', $companyId)->where('invoice_number', $m[1])->value('journal_id');
            } elseif (preg_match('/^Pembatalan perolehan aset (.+)$/', $desc, $m)) {
                $orig = $DB::table('fixed_assets')->where('company_id', $companyId)->where('code', $m[1])->value('acquisition_journal_id');
            } elseif (preg_match('/^Pembatalan (.+)$/', $desc, $m)) {
                $orig = $DB::table('journals')->where('company_id', $companyId)->where('ref_number', $m[1])->where('id', '<', $r->id)->orderByDesc('id')->value('id');
            }
            if ($orig) { $ids[] = (int) $r->id; $ids[] = (int) $orig; }
        }

        return array_values(array_unique($ids));
    }

    private static function base(Account $account)
    {
        return JournalDetail::where('journal_details.account_id', $account->id)
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->where('journals.status', 'posted')
            ->where('journals.company_id', $account->company_id);
    }

    /** Saldo awal periode (mengikuti saldo normal akun). */
    public static function openingBefore(Account $account, ?string $from, bool $includeVoided = true): float
    {
        $opening = (float) $account->opening_balance;
        if (!$from) return $opening;

        $q = self::base($account);
        if (! $includeVoided && ($hide = self::cancelledPairIds((int) $account->company_id))) {
            $q->whereNotIn('journals.id', $hide);
        }
        $row = $q->where('journals.transaction_date', '<', $from)
            ->selectRaw('COALESCE(SUM(journal_details.debit),0) as d, COALESCE(SUM(journal_details.credit),0) as c')
            ->first();
        $d = (float) ($row->d ?? 0);
        $c = (float) ($row->c ?? 0);

        return $opening + ($account->normal_balance === 'debit' ? $d - $c : $c - $d);
    }

    /** Mutasi dalam periode, urut tanggal lalu id (menaik). */
    public static function mutations(Account $account, ?string $from, ?string $to, bool $includeVoided = true)
    {
        $q = self::base($account)->with('journal');
        if (! $includeVoided && ($hide = self::cancelledPairIds((int) $account->company_id))) {
            $q->whereNotIn('journals.id', $hide);
        }
        if ($from) $q->where('journals.transaction_date', '>=', $from);
        if ($to) $q->where('journals.transaction_date', '<=', $to);

        return $q->orderBy('journals.transaction_date')->orderBy('journal_details.id')
            ->select('journal_details.*')->get();
    }

    /** Ada mutasi di luar periode yang sedang ditampilkan? (untuk petunjuk) */
    public static function totalCount(Account $account, bool $includeVoided = true): int
    {
        $q = self::base($account);
        if (! $includeVoided && ($hide = self::cancelledPairIds((int) $account->company_id))) {
            $q->whereNotIn('journals.id', $hide);
        }

        return (int) $q->count();
    }
}
