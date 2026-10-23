<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'po_number', 'supplier_id', 'order_date', 'expected_date',
        'status', 'subtotal', 'discount_percent', 'discount_amount',
        'tax_percent', 'tax_amount', 'total', 'notes', 'created_by'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function downPayments(): HasMany
    {
        return $this->hasMany(DownPayment::class, 'purchase_order_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ItemReceipt::class, 'purchase_order_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'po_id');
    }

    /** Semua faktur yang menagih PO ini, termasuk faktur gabungan beberapa PO. */
    public function allInvoices()
    {
        $id = $this->id;
        return PurchaseInvoice::where('company_id', $this->company_id)->where(function ($q) use ($id) {
            $q->where('po_id', $id)->orWhereHas('items', fn ($i) => $i->where('po_id', $id));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }
}
