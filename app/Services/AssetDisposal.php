<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\JournalDetail;
use Illuminate\Support\Facades\DB;

/**
 * Pelepasan aset tetap (dijual atau dihapus/dibuang):
 *   Debit  Akumulasi Penyusutan   (yang sudah dijurnal)
 *   Debit  Kas/Bank/Piutang        (harga jual, bila dijual)
 *   Kredit Akun Aset               (harga perolehan)
 *   Selisih -> Kredit Laba Pelepasan Aset (pendapatan lain) atau Debit Rugi Pelepasan Aset (beban lain)
 */
class AssetDisposal
{
    public static function proceedsAccounts(int $cid)
    {
        return Account::where('company_id', $cid)->where('is_active', true)->where('type', 'asset')->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header)->filter(fn ($a) => $a->is_cash_bank || preg_match('/^piutang/i', trim($a->name)))->values();
    }

    public static function gainAccounts(int $cid)
    {
        return Account::where('company_id', $cid)->where('is_active', true)->whereIn('type', ['other_income', 'revenue'])->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header)->sortBy(fn ($a) => ($a->type === 'other_income' ? '0' : '1') . $a->code)->values();
    }

    public static function lossAccounts(int $cid)
    {
        return Account::where('company_id', $cid)->where('is_active', true)->whereIn('type', ['other_expense', 'expense'])->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header)->sortBy(fn ($a) => ($a->type === 'other_expense' ? '0' : '1') . $a->code)->values();
    }

    public static function defaultOf($list, string $pattern): ?Account
    {
        return $list->first(fn ($a) => preg_match($pattern, $a->name)) ?? $list->first();
    }

    /** @throws \DomainException */
    public static function dispose(FixedAsset $asset, array $d, int $userId): Journal
    {
        $cid = (int) $asset->company_id;
        if ($asset->status !== 'aktif' || $asset->disposal_journal_id) {
            throw new \DomainException('Aset ini sudah tidak aktif / sudah dilepas.');
        }
        $date = $d['date'];
        if ($asset->acquisition_date && $date < $asset->acquisition_date->format('Y-m-d')) {
            throw new \DomainException('Tanggal pelepasan tidak boleh sebelum tanggal perolehan.');
        }
        $cost = round((float) $asset->acquisition_cost, 2);
        $accum = round((float) $asset->accumulated_depreciation, 2);
        $proceeds = round((float) ($d['proceeds'] ?? 0), 2);

        $assetAcc = (int) ($d['asset_account_id'] ?? 0) ?: (int) $asset->asset_account_id;
        if (! AssetAccounting::assetAccounts($cid)->contains('id', $assetAcc)) {
            throw new \DomainException('Pilih akun aset tetap yang valid.');
        }
        $accumAcc = (int) ($d['accumulated_account_id'] ?? 0) ?: (int) $asset->accumulated_account_id;
        if ($accum > 0 && ! Account::where('company_id', $cid)->whereKey($accumAcc)->exists()) {
            throw new \DomainException('Pilih akun Akumulasi Penyusutan.');
        }
        $proceedsAcc = (int) ($d['proceeds_account_id'] ?? 0);
        if ($proceeds > 0 && ! self::proceedsAccounts($cid)->contains('id', $proceedsAcc)) {
            throw new \DomainException('Pilih akun penerimaan hasil penjualan (kas/bank/piutang).');
        }
        $gainLoss = round($proceeds + $accum - $cost, 2);
        $gainAcc = (int) ($d['gain_account_id'] ?? 0);
        $lossAcc = (int) ($d['loss_account_id'] ?? 0);
        if ($gainLoss > 0 && ! self::gainAccounts($cid)->contains('id', $gainAcc)) {
            throw new \DomainException('Pilih akun Laba Pelepasan Aset.');
        }
        if ($gainLoss < 0 && ! self::lossAccounts($cid)->contains('id', $lossAcc)) {
            throw new \DomainException('Pilih akun Rugi Pelepasan Aset.');
        }

        return DB::transaction(function () use ($asset, $cid, $date, $cost, $accum, $proceeds, $assetAcc, $accumAcc, $proceedsAcc, $gainLoss, $gainAcc, $lossAcc, $userId, $d) {
            $label = ($proceeds > 0 ? 'Penjualan' : 'Penghapusan') . ' aset ' . $asset->code . ' - ' . $asset->name;
            $journal = Journal::create([
                'company_id' => $cid,
                'ref_number' => DocumentNumber::next(Journal::class, 'ref_number', 'FAD-', $cid, $date),
                'type' => 'general', 'transaction_date' => $date,
                'description' => $label . (! empty($d['notes']) ? ' — ' . $d['notes'] : ''),
                'status' => 'posted', 'created_by' => $userId,
            ]);
            $line = fn ($acc, $dr, $cr, $desc) => JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $acc, 'debit' => $dr, 'credit' => $cr, 'description' => $desc]);
            if ($accum > 0) $line($accumAcc, $accum, 0, 'Hapus akumulasi penyusutan ' . $asset->code);
            if ($proceeds > 0) $line($proceedsAcc, $proceeds, 0, 'Hasil penjualan ' . $asset->code);
            if ($gainLoss < 0) $line($lossAcc, -$gainLoss, 0, 'Rugi pelepasan ' . $asset->code);
            $line($assetAcc, 0, $cost, 'Keluarkan harga perolehan ' . $asset->code);
            if ($gainLoss > 0) $line($gainAcc, 0, $gainLoss, 'Laba pelepasan ' . $asset->code);
            JournalGuard::assertBalanced($journal);

            $asset->forceFill([
                'status' => $proceeds > 0 ? 'dijual' : 'dihapus',
                'disposal_date' => $date, 'disposal_proceeds' => $proceeds, 'disposal_journal_id' => $journal->id,
                'asset_account_id' => $assetAcc,
            ])->save();

            return $journal;
        });
    }

    /** Batalkan pelepasan: jurnal balik bertanggal hari ini, aset kembali aktif. */
    public static function cancel(FixedAsset $asset, int $userId): void
    {
        if (! $asset->disposal_journal_id) {
            throw new \DomainException('Aset ini belum dilepas.');
        }
        DB::transaction(function () use ($asset, $userId) {
            if ($j = Journal::with('details')->find($asset->disposal_journal_id)) {
                InvoicePaymentService::reverseJournal($j, 'Pembatalan ' . $j->ref_number, $userId);
            }
            $asset->forceFill(['status' => 'aktif', 'disposal_date' => null, 'disposal_proceeds' => null, 'disposal_journal_id' => null])->save();
        });
    }
}
