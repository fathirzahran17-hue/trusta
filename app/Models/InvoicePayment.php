<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = [
        'company_id', 'sales_invoice_id', 'purchase_invoice_id', 'payment_number',
        'payment_date', 'account_id', 'amount', 'notes', 'journal_id', 'created_by', 'voided_at', 'voided_by',
    ];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2', 'voided_at' => 'datetime'];

    public function salesInvoice() { return $this->belongsTo(SalesInvoice::class); }
    public function purchaseInvoice() { return $this->belongsTo(PurchaseInvoice::class); }
    public function scopeActive($q) { return $q->whereNull('voided_at'); }

    public function account() { return $this->belongsTo(Account::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
}
