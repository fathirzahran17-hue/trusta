<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor dokumen harus unik per perusahaan, bukan sedunia. Sebelumnya perusahaan B tidak bisa membuat
 * IR-202610-0001 kalau perusahaan A sudah memakainya (error "Duplicate entry ... _unique").
 */
return new class extends Migration
{
    private array $map = [
        'sales_orders' => 'order_number',
        'delivery_orders' => 'delivery_number',
        'item_receipts' => 'receipt_number',
        'down_payments' => 'payment_number',
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $col) {
            Schema::table($table, function (Blueprint $t) use ($col) {
                $t->dropUnique([$col]);
            });
            Schema::table($table, function (Blueprint $t) use ($col) {
                $t->unique(['company_id', $col]);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => $col) {
            Schema::table($table, function (Blueprint $t) use ($col) {
                $t->dropUnique(['company_id', $col]);
            });
            Schema::table($table, function (Blueprint $t) use ($col) {
                $t->unique([$col]);
            });
        }
    }
};
