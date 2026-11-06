<?php

namespace App\Support;

/**
 * Nilai baris dokumen. Form bisa mengisi subtotal lalu harga satuannya dihitung, atau memberi diskon dalam rupiah.
 * Harga satuan hanya dua desimal, jadi form ikut mengirim nilai baris yang tampil. Nilai itu dipakai bila masih sesuai
 * dengan jumlah x harga x diskon, supaya Rp100.000 untuk 3 barang tidak tersimpan Rp99.999,99.
 */
class LineAmount
{
    public static function net(float $qty, float $price, float $discountPercent, mixed $shown): float
    {
        $gross = $qty * $price;
        $computed = $gross - $gross * ($discountPercent / 100);
        if ($shown === null || $shown === '' || ! is_numeric($shown) || (float) $shown < 0) {
            return $computed;
        }
        // Selisih paling banyak setengah sen per barang karena harga satuan dibulatkan, ditambah sedikit kelonggaran
        $tolerance = 0.005 * abs($qty) + 0.02;

        return abs((float) $shown - $computed) <= $tolerance ? round((float) $shown, 2) : $computed;
    }

    /** @return array<string,string> aturan validasi tambahan untuk form baris */
    public static function rules(): array
    {
        return ['line_totals' => 'nullable|array', 'line_totals.*' => 'nullable|numeric|min:0'];
    }
}
