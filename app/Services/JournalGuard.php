<?php

namespace App\Services;

use App\Models\Journal;

/** Pengaman: jurnal otomatis harus seimbang (debit = kredit), kalau tidak transaksi dibatalkan. */
class JournalGuard
{
    public static function assertBalanced(Journal $journal): void
    {
        $d = (float) $journal->details()->sum('debit');
        $c = (float) $journal->details()->sum('credit');
        if (abs($d - $c) > 0.005) {
            throw new \Exception('Jurnal tidak seimbang (debit ' . number_format($d, 0, ',', '.') . ' ≠ kredit ' . number_format($c, 0, ',', '.')
                . '). Periksa pengaturan pajak dan akun, lalu coba lagi.');
        }
    }
}
