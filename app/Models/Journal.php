<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    protected $fillable = [
        'company_id', 'ref_number', 'type', 'transaction_date',
        'description', 'status', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(JournalDetail::class);
    }

    public function getTotalDebitAttribute(): float
    {
        return $this->details->sum('debit');
    }

    public function getTotalCreditAttribute(): float
    {
        return $this->details->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.01;
    }
}
