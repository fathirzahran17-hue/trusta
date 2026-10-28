<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionOrderCost extends Model
{
    public $timestamps = false;
    protected $fillable = ['production_order_id', 'account_id', 'description', 'amount'];

    public function account() { return $this->belongsTo(Account::class); }
}
