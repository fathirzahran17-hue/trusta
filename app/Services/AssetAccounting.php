<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\JournalDetail;

/** Jurnal perolehan aset tetap: Debit akun aset, Kredit akun kas/bank atau hutang. */
class AssetAccounting
{
    /** Akun yang boleh dipilih sebagai akun aset (bukan kas/bank, bukan induk). */
    public static function assetAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)->where('is_active', true)->where('type', 'asset')->where('is_cash_bank', false)
            ->orderBy('code')->get()->reject(fn ($a) => $a->is_header)
            ->sortBy(fn ($a) => (preg_match('/aset tetap|peralatan|kendaraan|bangunan|tanah|inventaris|mesin|perlengkapan/i', $a->name) && stripos($a->name, 'akumulasi') === false ? '0' : '1') . $a->code)->values();
    }

    /** Sumber dana: akun kas/bank atau hutang. */
    public static function fundingAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header)
            ->filter(fn ($a) => $a->is_cash_bank || $a->type === 'liability')->values();
    }

    private static function assertAccounts(int $companyId, int $assetAccountId, int $fundingAccountId): void
    {
        if (! self::assetAccounts($companyId)->contains('id', $assetAccountId)) {
            throw new \DomainException('Akun aset tidak valid.');
        }
        if (! self::fundingAccounts($companyId)->contains('id', $fundingAccountId)) {
            throw new \DomainException('Akun sumber dana (kas/bank/hutang) tidak valid.');
        }
    }

    public static function post(FixedAsset $asset, int $assetAccountId, int $fundingAccountId, int $userId): Journal
    {
        $cid = (int) $asset->company_id;
        self::assertAccounts($cid, $assetAccountId, $fundingAccountId);
        $cost = round((float) $asset->acquisition_cost, 2);
        if ($cost <= 0) {
            throw new \DomainException('Harga perolehan harus lebih dari 0 untuk dijurnal.');
        }
        $date = $asset->acquisition_date->format('Y-m-d');

        $journal = Journal::create([
            'company_id' => $cid,
            'ref_number' => DocumentNumber::next(Journal::class, 'ref_number', 'FA-', $cid, $date),
            'type' => 'general',
            'transaction_date' => $date,
            'description' => 'Perolehan aset tetap ' . $asset->code . ' - ' . $asset->name,
            'status' => 'posted',
            'created_by' => $userId,
        ]);
        JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $assetAccountId, 'debit' => $cost, 'credit' => 0, 'description' => 'Perolehan ' . $asset->name]);
        JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $fundingAccountId, 'debit' => 0, 'credit' => $cost, 'description' => 'Pembayaran/hutang aset ' . $asset->name]);
        JournalGuard::assertBalanced($journal);

        $asset->forceFill(['asset_account_id' => $assetAccountId, 'acquisition_journal_id' => $journal->id])->save();

        return $journal;
    }

    /** Sesuaikan jurnal perolehan bila harga/tanggal aset diubah. */
    public static function sync(FixedAsset $asset): void
    {
        $journal = $asset->acquisition_journal_id ? Journal::with('details')->find($asset->acquisition_journal_id) : null;
        if (! $journal) {
            return;
        }
        $cost = round((float) $asset->acquisition_cost, 2);
        $date = $asset->acquisition_date->format('Y-m-d');
        $changed = abs($journal->details->sum('debit') - $cost) > 0.004 || $journal->transaction_date->format('Y-m-d') !== $date;
        if (! $changed) {
            return;
        }
        $journal->update(['transaction_date' => $date]);
        foreach ($journal->details as $d) {
            if ((float) $d->debit > 0) {
                $d->update(['debit' => $cost]);
            } else {
                $d->update(['credit' => $cost]);
            }
        }
        JournalGuard::assertBalanced($journal->fresh());
    }
}
