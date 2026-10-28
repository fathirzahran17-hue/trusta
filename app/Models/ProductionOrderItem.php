<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['production_order_id', 'product_id', 'quantity', 'unit_cost', 'total'];

    public function product() { return $this->belongsTo(Product::class); }
}
