<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan platform (diatur super admin): rekening transfer, WhatsApp admin, trial otomatis, dll. */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private static ?array $cache = null;

    public static function get(string $key, $default = null)
    {
        if (self::$cache === null) {
            try {
                self::$cache = self::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                self::$cache = [];
            }
        }

        return array_key_exists($key, self::$cache) && self::$cache[$key] !== null && self::$cache[$key] !== '' ? self::$cache[$key] : $default;
    }

    public static function put(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }
}
