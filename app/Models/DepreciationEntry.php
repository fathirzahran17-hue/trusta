<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan penyusutan per aset per jurnal, agar penyusutan bisa dibatalkan. */
class DepreciationEntry extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'fixed_asset_id', 'journal_id', 'period', 'amount', 'prev_last_date', 'voided_at'];

    protected $casts = ['amount' => 'decimal:2', 'voided_at' => 'datetime'];

    public function asset() { return $this->belongsTo(FixedAsset::class, 'fixed_asset_id'); }
    public function journal() { return $this->belongsTo(Journal::class); }
}
