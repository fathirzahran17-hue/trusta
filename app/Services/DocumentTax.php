<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\Rule;

/**
 * Satu aturan hitung PPN untuk semua dokumen: hanya baris kena PPN yang masuk DPP, diskon keseluruhan
 * mengurangi DPP secara proporsional, dan pembulatannya mengikuti Preferensi perusahaan.
 * Tampilan di form memakai aturan yang sama lewat window.LineTax (resources/js/forms.js).
 */
class DocumentTax
{
    public const ROUND_NORMAL = 'Normal (Matematis)';
    public const ROUND_UP = 'Pembulatan Ke Atas';
    public const ROUND_DOWN = 'Pembulatan Ke Bawah';

    public const NUMBER_MESSAGES = [
        'tax_invoice_number.regex' => 'No. Faktur Pajak hanya boleh berisi angka, titik, dan tanda hubung.',
        'tax_invoice_number.max' => 'No. Faktur Pajak maksimal 32 karakter.',
        'tax_invoice_number.unique' => 'No. Faktur Pajak ini sudah dipakai faktur lain.',
    ];

    /** Pembulatan Normal menjaga dua desimal seperti perhitungan lama; Ke Atas dan Ke Bawah membulatkan ke rupiah penuh. */
    public static function amount(float $base, float $percent, ?string $rounding): float
    {
        // Membuang galat float dulu, supaya 110.000,00 tidak terbaca 110.000,0000001 lalu naik sendiri
        $raw = round($base * $percent / 100, 4);

        return match ($rounding) {
            self::ROUND_UP => ceil($raw),
            self::ROUND_DOWN => floor($raw),
            default => round($raw, 2),
        };
    }

    /**
     * @param  array<int,array<string,mixed>>  $lines  baris dokumen, tiap baris punya nilai setelah diskon baris dan is_taxable
     * @return array{base: float, percent: float, amount: float}
     */
    public static function compute(array $lines, float $overallDiscPct, float $percent, ?string $rounding, string $totalKey = 'total'): array
    {
        if ($percent <= 0) {
            return ['base' => 0.0, 'percent' => 0.0, 'amount' => 0.0];
        }
        $taxable = 0.0;
        foreach ($lines as $line) {
            if ($line['is_taxable'] ?? true) {
                $taxable += (float) $line[$totalKey];
            }
        }
        $base = round($taxable * (1 - $overallDiscPct / 100), 2);

        return ['base' => $base, 'percent' => $percent, 'amount' => self::amount($base, $percent, $rounding)];
    }

    /**
     * Uang muka dicatat sebesar uang yang diterima atau dibayar, jadi DPP dan PPN dipisahkan dari jumlah itu.
     *
     * @return array{base: float, percent: float, amount: float}
     */
    public static function fromGross(float $gross, float $percent, ?string $rounding): array
    {
        if ($percent <= 0) {
            return ['base' => 0.0, 'percent' => 0.0, 'amount' => 0.0];
        }
        $amount = self::amount($gross / (1 + $percent / 100), $percent, $rounding);

        return ['base' => round($gross - $amount, 2), 'percent' => $percent, 'amount' => $amount];
    }

    /** Pilihan di form yang menentukan; bila tidak ada, mengikuti data barang. Baris akun langsung (tanpa barang) dianggap kena PPN. */
    public static function lineFlag(mixed $requested, ?Product $product): bool
    {
        if ($requested !== null && $requested !== '') {
            return filter_var($requested, FILTER_VALIDATE_BOOLEAN);
        }

        return $product ? (bool) $product->is_taxable : true;
    }

    /** Retur mengikuti status PPN baris faktur asalnya, bukan data barang saat ini yang mungkin sudah berubah. */
    public static function invoiceLineFlag(iterable $invoiceItems, mixed $productId, mixed $accountId = null): bool
    {
        foreach ($invoiceItems as $item) {
            $same = $productId ? (int) $item->product_id === (int) $productId : (int) ($item->account_id ?? 0) === (int) $accountId;
            if ($same) {
                return (bool) ($item->is_taxable ?? true);
            }
        }

        return true;
    }

    /** Nomor Faktur Pajak boleh menyusul. Formatnya longgar karena format lama dan format Coretax berbeda. */
    public static function numberRules(?string $uniqueIn = null, ?int $ignoreId = null): array
    {
        $rules = ['nullable', 'string', 'max:32', 'regex:/^[0-9][0-9.\-\s]*$/'];
        if ($uniqueIn) {
            $rules[] = Rule::unique($uniqueIn, 'tax_invoice_number')
                ->where('company_id', auth()->user()->company_id)
                ->where(fn ($q) => $q->where('status', '!=', 'void'))
                ->ignore($ignoreId);
        }

        return $rules;
    }
}
