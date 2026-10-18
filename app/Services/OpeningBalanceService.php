<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalDetail;

/**
 * Saldo awal disimpan HANYA sebagai satu jurnal "Saldo Awal Sistem" (seimbang, dengan akun penyeimbang).
 * Kolom accounts.opening_balance dibiarkan 0 supaya saldo tidak terhitung dua kali
 * (Account::balance = opening_balance + mutasi jurnal).
 */
class OpeningBalanceService
{
    public const DESCRIPTION = 'Saldo Awal Sistem';
    public const OFFSET_LINE = 'Penyeimbang Saldo Awal';

    public static function journal(int $companyId): ?Journal
    {
        return Journal::where('company_id', $companyId)->where('description', self::DESCRIPTION)->first();
    }

    /** @return array<int,float> accountId => saldo awal (sesuai saldo normal akun), tanpa baris penyeimbang */
    public static function current(int $companyId): array
    {
        $journal = self::journal($companyId);
        if (! $journal) return [];

        $out = [];
        foreach ($journal->details()->with('account')->get() as $d) {
            if ($d->description === self::OFFSET_LINE || ! $d->account) continue;
            $signed = $d->account->normal_balance === 'debit' ? $d->debit - $d->credit : $d->credit - $d->debit;
            $out[$d->account_id] = ($out[$d->account_id] ?? 0) + (float) $signed;
        }
        return $out;
    }

    /**
     * @param array<int,float|string> $overrides accountId => saldo awal baru (0 = hapus)
     */
    public static function set(int $companyId, string $date, array $overrides, ?int $userId = null): void
    {
        $map = self::current($companyId);
        foreach ($overrides as $id => $amount) {
            $map[(int) $id] = (float) $amount;
        }

        $journal = self::journal($companyId);
        if ($journal) {
            $journal->details()->delete();
            $journal->update(['transaction_date' => $date]);
        } else {
            $journal = Journal::create([
                'company_id' => $companyId, 'ref_number' => 'JV-OB-' . time(), 'type' => 'general',
                'transaction_date' => $date, 'description' => self::DESCRIPTION, 'status' => 'posted', 'created_by' => $userId,
            ]);
        }

        $totalDebit = 0; $totalCredit = 0;
        foreach ($map as $accountId => $balance) {
            if (abs($balance) < 0.005) continue;
            $account = Account::where('company_id', $companyId)->find($accountId);
            if (! $account) continue;

            $debit = 0; $credit = 0;
            if ($account->normal_balance === 'debit') { $balance > 0 ? $debit = $balance : $credit = abs($balance); }
            else { $balance > 0 ? $credit = $balance : $debit = abs($balance); }
            $totalDebit += $debit; $totalCredit += $credit;

            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $account->id,
                'description' => 'Saldo Awal ' . $account->name, 'debit' => $debit, 'credit' => $credit]);
            if ((float) $account->opening_balance != 0.0) $account->update(['opening_balance' => 0]);
        }

        $diff = $totalDebit - $totalCredit;
        if (abs($diff) >= 0.005) {
            $equity = Account::where('company_id', $companyId)->where('name', 'like', '%Ekuitas Saldo Awal%')->first()
                ?? Account::create(['company_id' => $companyId, 'code' => '3-9999', 'name' => 'Ekuitas Saldo Awal',
                    'type' => 'equity', 'normal_balance' => 'credit', 'is_system' => true]);
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $equity->id, 'description' => self::OFFSET_LINE,
                'debit' => $diff < 0 ? abs($diff) : 0, 'credit' => $diff > 0 ? $diff : 0]);
        }
    }
}
