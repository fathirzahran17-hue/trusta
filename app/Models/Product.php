<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'category_id', 'sku', 'name', 'type', 'is_taxable', 'unit',
        'sell_price', 'buy_price', 'revenue_account_id', 'expense_account_id',
        'is_active', 'inventory_account_id',
    ];

    protected $casts = [
        'sell_price' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getStockAttribute()
    {
        if ($this->type !== 'goods') {
            return 0; // Services do not have stock
        }
        $in = $this->inventoryMovements()->where('type', 'in')->sum('quantity');
        $out = $this->inventoryMovements()->where('type', 'out')->sum('quantity');
        return $in - $out;
    }
}
