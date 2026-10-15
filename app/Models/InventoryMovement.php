<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\EnforcesClosingDate;

class InventoryMovement extends Model
{
    protected $guarded = ['id'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
