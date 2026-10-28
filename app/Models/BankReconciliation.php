<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hasil pencocokan saldo kas/bank di buku dengan rekening koran pada tanggal tertentu. */
class BankReconciliation extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'account_id', 'statement_date', 'statement_balance', 'cleared_balance', 'cleared_count', 'notes', 'created_by', 'voided_at'];

    protected $casts = ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'cleared_balance' => 'decimal:2', 'voided_at' => 'datetime'];

    public function account() { return $this->belongsTo(Account::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
