<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'name', 'address', 'phone', 'email', 'npwp',
        'logo_path', 'fiscal_year_start', 'is_active', 'is_premium',
        'default_tax_rate', 'tax_rounding', 'default_sales_term', 'warn_below_cost', 'default_purchase_term',
        'prevent_over_receive', 'prevent_prior_transactions', 'closing_date', 'notify_approver', 'business_type',
        'max_attachment_size', 'allowed_attachment_types', 'custom_customer_attr_1', 'custom_item_attr_1',
        'price_decimal_format', 'default_language', 'inventory_costing_method',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['trial', 'active'])
            ->latest('end_date');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function salesInvoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    /**
     * Cek apakah perusahaan memiliki langganan premium aktif
     */
    public function isPremium(): bool
    {
        $sub = $this->activeSubscription;
        if (!$sub) return false;

        return in_array($sub->status, ['active', 'trial'])
            && $sub->end_date >= now()->toDateString()
            && $sub->plan && $sub->plan->price > 0;
    }

    /**
     * Cek apakah masih dalam masa trial
     */
    public function isTrial(): bool
    {
        $sub = $this->activeSubscription;
        return $sub && $sub->status === 'trial' && $sub->end_date >= now()->toDateString();
    }

    /**
     * Ambil paket langganan aktif
     */
    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    /** URL logo perusahaan (disimpan di public/uploads/logos agar jalan tanpa storage:link di hosting). */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path && file_exists(public_path($this->logo_path)) ? asset($this->logo_path) : null;
    }
}
