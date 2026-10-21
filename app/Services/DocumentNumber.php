<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Penomoran dokumen otomatis: PREFIX-YYYYMM-0001.
 * Nomor berikutnya = nomor terbesar yang sudah ada untuk perusahaan dan bulan tersebut + 1 (bukan "dokumen terakhir
 * berdasarkan tanggal"), sehingga dokumen bertanggal mundur/maju atau yang dihapus tidak menghasilkan nomor kembar.
 */
class DocumentNumber
{
    /**
     * @param class-string $model    model Eloquent
     * @param string       $column   kolom nomor, mis. 'receipt_number'
     * @param string       $prefix   mis. 'IR-'
     * @param \Closure|null $scope   tambahan filter (mis. tipe uang muka), menerima Builder
     */
    public static function next(string $model, string $column, string $prefix, int $companyId, ?string $date = null, ?\Closure $scope = null): string
    {
        $ym = date('Ym', $date ? strtotime($date) : time());
        $stem = $prefix . $ym . '-';

        $q = $model::withoutGlobalScopes()->where('company_id', $companyId)->where($column, 'like', $stem . '%');
        if ($scope) {
            $scope($q);
        }
        $max = 0;
        foreach ($q->pluck($column) as $n) {
            if (preg_match('/-(\d+)$/', (string) $n, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $number = $stem . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        // pengaman terakhir bila ada nomor yang sama di luar pola
        while ($model::withoutGlobalScopes()->where('company_id', $companyId)->where($column, $number)->exists()) {
            $max++;
            $number = $stem . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
        }

        return $number;
    }

    /** Aturan validasi: nomor dokumen yang diketik manual tidak boleh sama dengan milik perusahaan ini. */
    public static function rule(string $table, string $column): \Illuminate\Validation\Rules\Unique
    {
        return \Illuminate\Validation\Rule::unique($table, $column)->where('company_id', auth()->user()->company_id);
    }
}
