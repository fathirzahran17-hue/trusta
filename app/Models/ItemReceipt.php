<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;

class ItemReceipt extends Model
{
    use HasFactory, EnforcesClosingDate;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'receipt_number',
        'receipt_date',
        'delivery_note_number',
        'status',
        'journal_id',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function details()
    {
        return $this->hasMany(ItemReceiptDetail::class);
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
