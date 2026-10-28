<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemReceiptDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_receipt_id',
        'purchase_order_id',
        'product_id',
        'description',
        'quantity'
    ];

    public function itemReceipt()
    {
        return $this->belongsTo(ItemReceipt::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
