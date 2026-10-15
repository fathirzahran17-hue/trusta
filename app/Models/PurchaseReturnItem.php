<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $guarded = ['id'];
    public $timestamps = false;
    public function purchaseReturn() { return $this->belongsTo(PurchaseReturn::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
