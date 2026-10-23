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
}
