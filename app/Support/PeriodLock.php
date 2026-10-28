<?php

namespace App\Support;

use App\Exceptions\PeriodClosedException;
use Carbon\Carbon;

/**
 * Kunci periode (Tutup Buku). Transaksi bertanggal <= closing_date perusahaan tidak boleh dibuat, diubah tanggalnya, atau dihapus.
 * Tidak berlaku bila tidak ada pengguna yang login (seeder, perintah artisan, scheduler).
 */
class PeriodLock
{
    public static function closingDate(): ?Carbon
    {
        if (!auth()->check()) return null;
        $company = auth()->user()->company ?? null;
        if (!$company || !$company->closing_date) return null;

        return Carbon::parse($company->closing_date)->startOfDay();
    }

    public static function isClosed($date): bool
    {
        $closing = self::closingDate();
        if (!$closing || !$date) return false;

        return Carbon::parse($date)->startOfDay()->lte($closing);
    }

    public static function assertOpen($date, string $what = 'Transaksi'): void
    {
        if (self::isClosed($date)) {
            throw new PeriodClosedException(
                $what . ' bertanggal ' . Carbon::parse($date)->format('d M Y') . ' tidak dapat disimpan/diubah/dihapus karena periodenya sudah Tutup Buku (terkunci sampai '
                . self::closingDate()->format('d M Y') . '). Gunakan tanggal setelah itu, atau minta admin membuka kunci di Pengaturan > Proses Akhir Bulan.'
            );
        }
    }
}
