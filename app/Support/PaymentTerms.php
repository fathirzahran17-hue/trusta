<?php

namespace App\Support;

use App\Models\PaymentTerm;

/** Syarat pembayaran: pilihan standar ditambah yang dibuat sendiri di menu Syarat Pembayaran. */
class PaymentTerms
{
    private const STANDARD = ['Cash On Delivery' => 0, 'Net 7' => 7, 'Net 14' => 14, 'Net 30' => 30, 'Net 45' => 45, 'Net 60' => 60, 'Net 90' => 90];

    /** @return array<string,int> nama => jumlah hari */
    public static function options(int $companyId): array
    {
        $own = PaymentTerm::where('company_id', $companyId)->orderBy('days')->get()->mapWithKeys(fn ($t) => [$t->name => (int) $t->days])->all();

        return self::STANDARD + $own;
    }

    public static function days(?string $term, int $companyId): int
    {
        return self::options($companyId)[$term] ?? 30;
    }
}
