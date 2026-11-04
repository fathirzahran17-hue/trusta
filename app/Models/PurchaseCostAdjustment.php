<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseCostAdjustment extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $guarded = ['id'];

    public function movement()
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }
}
