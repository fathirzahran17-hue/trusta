<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'price', 'max_accounts', 'max_transactions', 'max_users',
        'has_reports', 'has_cash_bank', 'has_export', 'has_auto_backup',
        'trial_days', 'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'has_reports' => 'boolean',
        'has_cash_bank' => 'boolean',
        'has_export' => 'boolean',
        'has_auto_backup' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
