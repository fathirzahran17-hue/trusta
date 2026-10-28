<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BomItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['bom_id', 'product_id', 'quantity'];
    protected $casts = ['quantity' => 'decimal:4'];

    public function product() { return $this->belongsTo(Product::class); }
}
