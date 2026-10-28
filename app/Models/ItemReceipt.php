<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;

class ItemReceipt extends Model
{
    use HasFactory, EnforcesClosingDate, \App\Models\Concerns\BelongsToCompany;

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

    /** Id pesanan asal (header + per baris; satu dokumen bisa dari beberapa pesanan). */
    public function sourceOrderIds()
    {
        return $this->details()->whereNotNull('purchase_order_id')->pluck('purchase_order_id')->push($this->purchase_order_id)->filter()->map(fn ($v) => (int) $v)->unique()->values();
    }

    public function sourceOrders()
    {
        $ids = $this->sourceOrderIds();

        return $ids->isEmpty() ? collect() : PurchaseOrder::where('company_id', $this->company_id)->whereIn('id', $ids)->get();
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
