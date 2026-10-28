<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pembayaran langganan oleh klien (transfer + bukti), menunggu konfirmasi super admin. */
class SubscriptionPayment extends Model
{
    protected $fillable = ['company_id', 'plan_id', 'billing_cycle', 'months', 'amount', 'payer_name', 'payer_bank', 'paid_at', 'proof_path', 'notes',
        'status', 'admin_note', 'reviewed_by', 'reviewed_at', 'subscription_id', 'created_by'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'date', 'reviewed_at' => 'datetime'];

    public function company() { return $this->belongsTo(Company::class); }
    public function plan() { return $this->belongsTo(Plan::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function cycleLabel(): string
    {
        return $this->billing_cycle === 'yearly' ? 'Tahunan' : ($this->months > 1 ? $this->months . ' bulan' : 'Bulanan');
    }
}
