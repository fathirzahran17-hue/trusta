<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan per baris ke dokumen asal:
 *  - baris Pengiriman/Penerimaan -> pesanan asalnya (satu pengiriman bisa dari beberapa SO/PO)
 *  - baris Faktur -> pengiriman/penerimaan asalnya (agar pengiriman tanpa SO tidak tertagih dua kali)
 */
return new class extends Migration
{
    private array $cols = [
        'delivery_order_details' => ['sales_order_id', 'product_id'],
        'item_receipt_details' => ['purchase_order_id', 'product_id'],
        'sales_invoice_items' => ['delivery_order_id', 'product_id'],
        'purchase_invoice_items' => ['item_receipt_id', 'product_id'],
    ];

    public function up(): void
    {
        foreach ($this->cols as $table => [$col, $after]) {
            if (! Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $t) use ($col, $after) {
                    $t->unsignedBigInteger($col)->nullable()->after($after)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->cols as $table => [$col]) {
            if (Schema::hasColumn($table, $col)) {
                Schema::table($table, function (Blueprint $t) use ($table, $col) {
                    $t->dropIndex([$col]);
                    $t->dropColumn($col);
                });
            }
        }
    }
};
