<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Perintah produksi: bahan keluar dari stok, barang jadi masuk, HPP produksi dijurnal. */
class ProductionOrder extends Model
{
    use \App\Traits\EnforcesClosingDate, \App\Models\Concerns\BelongsToCompany;

    protected string $closingDateField = 'date';
    protected $fillable = ['company_id', 'number', 'date', 'product_id', 'bom_id', 'quantity', 'material_cost', 'extra_cost', 'total_cost', 'unit_cost', 'journal_id', 'notes', 'created_by', 'voided_at'];
    protected $casts = ['date' => 'date', 'quantity' => 'decimal:2', 'material_cost' => 'decimal:2', 'extra_cost' => 'decimal:2', 'total_cost' => 'decimal:2', 'unit_cost' => 'decimal:4', 'voided_at' => 'datetime'];

    public function product() { return $this->belongsTo(Product::class); }
    public function bom() { return $this->belongsTo(Bom::class); }
    public function items() { return $this->hasMany(ProductionOrderItem::class); }
    public function costs() { return $this->hasMany(ProductionOrderCost::class); }
    public function journal() { return $this->belongsTo(Journal::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
