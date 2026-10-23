<?php

namespace App\Services;

use App\Models\Product;

/** Aturan khusus jasa: nilainya tidak tetap, jadi wajib diisi pada setiap transaksi (tidak boleh 0). */
class ServiceLines
{
    /** @throws \DomainException */
    public static function assertPriced(array $products, array $prices): void
    {
        $ids = array_unique(array_map('intval', $products));
        $services = Product::whereIn('id', $ids)->where('type', 'service')->pluck('name', 'id');
        if ($services->isEmpty()) {
            return;
        }
        foreach ($products as $i => $pid) {
            if (isset($services[(int) $pid]) && (float) ($prices[$i] ?? 0) <= 0) {
                throw new \DomainException('Isi nilai jasa untuk "' . $services[(int) $pid] . '" — nilai jasa tidak tetap sehingga harus diisi pada setiap transaksi.');
            }
        }
    }
}
