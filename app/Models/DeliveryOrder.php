<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;

class DeliveryOrder extends Model
{
    use HasFactory, EnforcesClosingDate, \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id',
        'customer_id',
        'sales_order_id',
        'delivery_number',
        'delivery_date',
        'driver_name',
        'license_plate',
        'shipping_address',
        'status',
        'journal_id',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function details()
    {
        return $this->hasMany(DeliveryOrderDetail::class);
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
