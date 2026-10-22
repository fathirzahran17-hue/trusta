<?php

namespace App\Support;

use Carbon\Carbon;

class ReportFormat
{
    private const MONTHS = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function money($v): string
    {
        if ($v === null || $v === '') return '';
        $v = (float) $v;
        if (abs($v) < 0.005) return '-';
        $s = number_format(abs($v), 2, ',', '.');
        return $v < 0 ? '(' . $s . ')' : $s;
    }

    public static function qty($v): string
    {
        if ($v === null || $v === '') return '';
        $s = number_format((float) $v, 2, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    }

    public static function date($v): string
    {
        if (! $v) return '';
        $c = Carbon::parse($v);
        return $c->format('d') . ' ' . self::MONTHS[(int) $c->format('n')] . ' ' . $c->format('Y');
    }

    public static function cell($value, string $format): string
    {
        return match ($format) {
            'money' => self::money($value),
            'qty'   => self::qty($value),
            'int'   => $value === null || $value === '' ? '' : number_format((float) $value, 0, ',', '.'),
            'date'  => self::date($value),
            default => (string) ($value ?? ''),
        };
    }
}
