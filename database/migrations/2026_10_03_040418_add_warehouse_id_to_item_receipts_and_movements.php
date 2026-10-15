<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item_receipts', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('set null')->after('purchase_order_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->constrained()->onDelete('cascade')->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item_receipts', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
