<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownPayment extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id',
        'type', // 'sales' or 'purchase'
        'customer_id',
        'supplier_id',
        'sales_order_id',
        'purchase_order_id',
        'payment_method',
        'payment_number',
        'payment_date',
        'amount',
        'notes',
        'journal_id',
        'created_by'
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications()
    {
        return $this->hasMany(DownPaymentApplication::class)->whereNull('voided_at');
    }

    /** Uang muka yang sudah dipakai melunasi faktur. */
    public function getAppliedAmountAttribute(): float
    {
        return round((float) $this->applications()->sum('amount'), 2);
    }

    /** Sisa uang muka yang belum dipakai. */
    public function getRemainingAttribute(): float
    {
        return max(0, round((float) $this->amount - $this->applied_amount, 2));
    }
}
