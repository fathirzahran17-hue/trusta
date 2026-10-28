<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'max_accounts', 'max_transactions', 'max_users',
        'has_reports', 'has_cash_bank', 'has_export', 'has_auto_backup',
        'trial_days', 'is_active', 'price_yearly', 'description', 'features', 'sort_order', 'is_public',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_reports' => 'boolean',
        'has_cash_bank' => 'boolean',
        'has_export' => 'boolean',
        'has_auto_backup' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'price_yearly' => 'decimal:2',
    ];

    /** Harga tahunan; bila kosong = 12 x bulanan. */
    public function yearlyPrice(): float
    {
        return (float) ($this->price_yearly ?? 0) > 0 ? (float) $this->price_yearly : round((float) $this->price * 12, 2);
    }

    /** @return string[] daftar keunggulan (satu per baris) */
    public function featureList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $this->features))));
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
