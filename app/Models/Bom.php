<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Resep produksi (Bill of Materials): bahan untuk menghasilkan sejumlah barang jadi. */
class Bom extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $fillable = ['company_id', 'product_id', 'name', 'output_qty', 'notes', 'is_active'];
    protected $casts = ['output_qty' => 'decimal:2', 'is_active' => 'boolean'];

    public function product() { return $this->belongsTo(Product::class); }
    public function items() { return $this->hasMany(BomItem::class); }
}
