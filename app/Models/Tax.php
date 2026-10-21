<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tax extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'rate', 'sales_tax_account_id', 'purchase_tax_account_id', 'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesTaxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_tax_account_id');
    }

    public function purchaseTaxAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_tax_account_id');
    }
}
