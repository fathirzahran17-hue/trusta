<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak penyesuaian harga pokok saat harga Faktur Pembelian berbeda dari harga Penerimaan Barang. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_cost_adjustments')) {
            Schema::create('purchase_cost_adjustments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->unsignedBigInteger('purchase_invoice_id')->index();
                $t->unsignedBigInteger('purchase_invoice_item_id')->index();
                $t->unsignedBigInteger('inventory_movement_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->decimal('quantity', 15, 4);
                $t->decimal('receipt_unit_cost', 18, 4);
                $t->decimal('invoice_unit_cost', 18, 4);
                $t->decimal('variance', 18, 2)->default(0);
                $t->decimal('absorbed', 18, 2)->default(0);
                $t->decimal('expensed', 18, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_cost_adjustments');
    }
};
