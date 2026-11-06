<?php

namespace App\Traits;

use App\Support\PeriodLock;

trait EnforcesClosingDate
{
    public static function bootEnforcesClosingDate()
    {
        static::saving(function ($model) {
            $model->checkClosingDate();
        });

        static::deleting(function ($model) {
            $model->checkClosingDate(true);
        });
    }

    /**
     * Menolak transaksi di periode Tutup Buku:
     *  - dokumen baru / dihapus: tanggalnya tidak boleh di periode terkunci;
     *  - dokumen lama diubah tanggalnya: tanggal lama maupun baru harus terbuka;
     *  - dokumen lama diubah non-tanggal (status, jumlah terbayar): diizinkan, kecuali model "ketat" (Jurnal) yang tidak boleh diubah sama sekali.
     * Pembatalan memakai jurnal balik bertanggal hari ini, jadi pembayaran/pembatalan faktur lama tetap bisa di periode terbuka.
     */
    public function checkClosingDate(bool $deleting = false): void
    {
        $field = $this->getDateField();
        $label = \App\Support\PeriodLock::label(class_basename($this));
        // Tahun yang salah ketik (mis. 0026) akan menaruh jurnal di luar semua laporan
        if (!$deleting && $this->{$field}) {
            $year = (int) \Carbon\Carbon::parse($this->{$field})->format('Y');
            if ($year < 1900 || $year > 2099) {
                throw new \App\Exceptions\PeriodClosedException($label . ' bertanggal ' . \Carbon\Carbon::parse($this->{$field})->format('d/m/Y')
                    . ' ditolak karena tahunnya tidak wajar. Periksa tahunnya, ketik 4 digit seperti 2026.');
            }
        }

        if (!PeriodLock::closingDate() && !PeriodLock::startDate()) {
            return;
        }

        if ($deleting || !$this->exists) {
            PeriodLock::assertOpen($this->{$field} ?? null, $label);
            return;
        }

        if ($this->isDirty($field)) {
            PeriodLock::assertOpen($this->getOriginal($field), $label);
            PeriodLock::assertOpen($this->{$field}, $label);
            return;
        }

        if (property_exists($this, 'closingStrict') ? $this->closingStrict : false) {
            PeriodLock::assertOpen($this->{$field} ?? null, $label);
        }
    }

    protected function getDateField(): string
    {
        if (isset($this->closingDateField)) {
            return $this->closingDateField;
        }

        $guesses = ['transaction_date', 'date', 'invoice_date', 'receipt_date', 'return_date'];
        foreach ($guesses as $guess) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), $guess)) {
                return $guess;
            }
        }

        return 'created_at';
    }
}
