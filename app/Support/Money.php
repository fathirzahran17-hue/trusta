<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Angka dari form berformat Indonesia ("1.234.567,89") diubah ke angka biasa ("1234567.89").
 * Nilai tanpa koma (mis. dari API/tes: "1234567.89" atau 20000) dipakai apa adanya.
 */
class Money
{
    public static function parse($v)
    {
        if (!is_string($v)) {
            return $v;
        }
        $s = trim($v);
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') || preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return $s;
    }

    /** Normalkan field uang (mendukung array bersarang lewat notasi "a.*.b") sebelum validasi. */
    public static function normalize(Request $r, array $keys): void
    {
        $data = $r->all();
        foreach ($keys as $key) {
            self::walk($data, explode('.', $key));
        }
        $r->merge($data);
    }

    private static function walk(array &$node, array $path): void
    {
        $head = array_shift($path);
        if ($head === '*') {
            foreach ($node as &$child) {
                if ($path === []) {
                    $child = self::parse($child);
                } elseif (is_array($child)) {
                    self::walk($child, $path);
                }
            }
            return;
        }
        if (!array_key_exists($head, $node)) {
            return;
        }
        if ($path === []) {
            $node[$head] = self::parse($node[$head]);
        } elseif (is_array($node[$head])) {
            self::walk($node[$head], $path);
        }
    }

    /** Jumlah desimal tampilan menurut Preferensi. Hanya tampilan: nilai tersimpan tetap dua desimal. */
    public static function decimals(): int
    {
        return auth()->check() && auth()->user()->company?->price_decimal_format === 'Tanpa Desimal' ? 0 : 2;
    }

    /** Satuan singkat mulai dari yang terbesar: [nilai, Indonesia, Inggris]. */
    private const UNITS = [[1e12, 'T', 'T'], [1e9, 'M', 'B'], [1e6, 'Jt', 'M'], [1e3, 'Rb', 'K']];

    /** @return array<int,array{0:float,1:string}> nilai dan satuan sesuai bahasa, dipakai juga oleh grafik */
    public static function compactUnits(): array
    {
        $column = app()->getLocale() === 'en' ? 2 : 1;

        return array_map(fn ($unit) => [$unit[0], $unit[$column]], self::UNITS);
    }

    /** 1500000 menjadi "1,5 Jt", 1250000000 menjadi "1,25 M". Nilai yang terbulatkan ke 1000 naik satuan: 999999000 menjadi "1 M". */
    public static function compact($value): string
    {
        $number = (float) $value;
        $sign = $number < 0 ? '-' : '';
        $number = abs($number);
        $units = self::compactUnits();
        foreach ($units as $i => [$size, $label]) {
            if ($number < $size) {
                continue;
            }
            $short = round($number / $size, 2);
            if ($short >= 1000 && $i > 0) {
                [$short, $label] = [round($short / 1000, 2), $units[$i - 1][1]];
            }

            return $sign . rtrim(rtrim(number_format($short, 2, ',', '.'), '0'), ',') . ' ' . $label;
        }

        return $sign . rtrim(rtrim(number_format($number, 2, ',', '.'), '0'), ',');
    }

    /** Untuk kolom sempit: angka lengkap selama masih muat, ringkas mulai 1 miliar. */
    public static function fit($value): string
    {
        return abs((float) $value) >= 1e9 ? self::compact($value) : self::fmt($value);
    }

    public static function fmt($value): string
    {
        return number_format((float) $value, self::decimals(), ',', '.');
    }
}
