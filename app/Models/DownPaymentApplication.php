<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pemakaian uang muka untuk melunasi (sebagian) faktur. */
class DownPaymentApplication extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'down_payment_id', 'sales_invoice_id', 'purchase_invoice_id', 'amount', 'applied_date', 'journal_id', 'created_by', 'voided_at'];

    protected $casts = ['amount' => 'decimal:2', 'applied_date' => 'date', 'voided_at' => 'datetime'];

    public function downPayment() { return $this->belongsTo(DownPayment::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function scopeActive($q) { return $q->whereNull('voided_at'); }
}
