<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class FixedAsset extends Model
{
    protected $fillable = [
        'company_id', 'fixed_asset_category_id', 'code', 'name', 'description',
        'acquisition_date', 'acquisition_cost', 'useful_life_months', 'salvage_value',
        'depreciation_method', 'location', 'condition', 'status',
        'serial_number', 'brand', 'supplier_name', 'invoice_number', 'notes',
    ];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    /**
     * Menghitung penyusutan bulanan (metode garis lurus)
     */
    public function getMonthlyDepreciationAttribute(): float
    {
        if ($this->useful_life_months <= 0) return 0;
        return ($this->acquisition_cost - $this->salvage_value) / $this->useful_life_months;
    }

    /**
     * Akumulasi penyusutan = yang benar-benar sudah dijurnal (kolom accumulated_depreciation),
     * bukan perkiraan berdasarkan lama waktu, supaya sama dengan buku besar.
     */
    public function getAccumulatedDepreciationAttribute(): float
    {
        return round((float) ($this->attributes['accumulated_depreciation'] ?? 0), 2);
    }

    /**
     * Nilai buku = Harga Perolehan - Akumulasi Penyusutan
     */
    public function getBookValueAttribute(): float
    {
        return max($this->acquisition_cost - $this->accumulated_depreciation, $this->salvage_value);
    }

    /**
     * Persentase penyusutan
     */
    public function getDepreciationPercentAttribute(): float
    {
        if ($this->acquisition_cost <= 0) return 0;
        return round(($this->accumulated_depreciation / $this->acquisition_cost) * 100, 1);
    }
}
