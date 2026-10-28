<?php

namespace App\Support;

/** Bidang usaha perusahaan: menentukan akun default (CoA) dan menu yang tampil. */
class BusinessType
{
    public const TYPES = [
        'jasa' => ['label' => 'Jasa', 'desc' => 'Konsultan, kontraktor, agensi, salon, bengkel jasa, dll. Tanpa stok barang.', 'industries' => ['jasa']],
        'dagang' => ['label' => 'Dagang', 'desc' => 'Toko, distributor, grosir: membeli barang lalu menjualnya kembali.', 'industries' => ['dagang']],
        'dagang_jasa' => ['label' => 'Dagang & Jasa', 'desc' => 'Menjual barang sekaligus jasa (mis. toko + instalasi/servis).', 'industries' => ['dagang', 'jasa']],
        'manufaktur' => ['label' => 'Manufaktur', 'desc' => 'Mengolah bahan baku menjadi barang jadi (produksi/perakitan).', 'industries' => ['manufaktur']],
    ];

    public const DEFAULT = 'dagang_jasa';

    public static function normalize(?string $type): string
    {
        return isset(self::TYPES[$type]) ? $type : self::DEFAULT;
    }

    /** @return string[] industri template CoA yang dipakai bidang ini */
    public static function industries(?string $type): array
    {
        return self::TYPES[self::normalize($type)]['industries'];
    }

    public static function label(?string $type): string
    {
        return self::TYPES[self::normalize($type)]['label'];
    }

    public static function hasStock(?string $type): bool
    {
        return self::normalize($type) !== 'jasa';
    }

    public static function hasProduction(?string $type): bool
    {
        return self::normalize($type) === 'manufaktur';
    }

    public static function current(): string
    {
        return self::normalize(optional(optional(auth()->user())->company)->business_type);
    }
}
