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

    private static function base(Account $account)
    {
        return JournalDetail::where('journal_details.account_id', $account->id)
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->where('journals.status', 'posted')
            ->where('journals.company_id', $account->company_id);
    }

    /** Saldo awal periode (mengikuti saldo normal akun). */
    public static function openingBefore(Account $account, ?string $from): float
    {
        $opening = (float) $account->opening_balance;
        if (!$from) return $opening;

        $row = self::base($account)->where('journals.transaction_date', '<', $from)
            ->selectRaw('COALESCE(SUM(journal_details.debit),0) as d, COALESCE(SUM(journal_details.credit),0) as c')
            ->first();
        $d = (float) ($row->d ?? 0);
        $c = (float) ($row->c ?? 0);

        return $opening + ($account->normal_balance === 'debit' ? $d - $c : $c - $d);
    }

    /** Mutasi dalam periode, urut tanggal lalu id (menaik). */
    public static function mutations(Account $account, ?string $from, ?string $to)
    {
        $q = self::base($account)->with('journal');
        if ($from) $q->where('journals.transaction_date', '>=', $from);
        if ($to) $q->where('journals.transaction_date', '<=', $to);

        return $q->orderBy('journals.transaction_date')->orderBy('journal_details.id')
            ->select('journal_details.*')->get();
    }

    /** Ada mutasi di luar periode yang sedang ditampilkan? (untuk petunjuk) */
    public static function totalCount(Account $account): int
    {
        return (int) self::base($account)->count();
    }
}
