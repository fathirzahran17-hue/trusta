<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DefaultAccount extends Model
{
    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'normal_balance', 'is_system', 'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DefaultAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DefaultAccount::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Mendapatkan label tipe akun dalam bahasa Indonesia
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'asset' => 'Aset',
            'liability' => 'Kewajiban',
            'equity' => 'Ekuitas',
            'revenue' => 'Pendapatan',
            'cost_of_sales' => 'HPP',
            'expense' => 'Beban',
            'other_income' => 'Pend. Lain',
            'other_expense' => 'Beban Lain',
            default => $this->type,
        };
    }
}
