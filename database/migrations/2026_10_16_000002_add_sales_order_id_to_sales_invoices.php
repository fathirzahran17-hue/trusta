<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan Faktur Penjualan ke Pesanan Penjualan asalnya
 * (sebelumnya hanya faktur pembelian yang punya tautan ke PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('sales_invoices', 'sales_order_id')) {
                $table->unsignedBigInteger('sales_order_id')->nullable()->after('quote_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn('sales_order_id');
        });
    }
};
