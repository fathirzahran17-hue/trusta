<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use \App\Traits\EnforcesClosingDate, \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'transaction_number', 'type', 'account_id', 'target_account_id',
        'amount', 'description', 'transaction_date', 'journal_id',
        'sales_invoice_id', 'purchase_invoice_id', 'contra_account_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'target_account_id');
    }
    
    public function contraAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'contra_account_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
