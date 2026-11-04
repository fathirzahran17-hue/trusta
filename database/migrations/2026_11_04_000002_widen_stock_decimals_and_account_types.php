<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Mutasi stok memakai 4 desimal, sama dengan resep (BOM), agar bahan 0,125 tidak tercatat 0,13.
 * - Pilihan accounts.type disamakan dengan database yang berjalan; tanpa ini instalasi baru lewat migrate gagal saat membuat Bagan Akun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $t) {
            $t->decimal('quantity', 15, 4)->change();
            $t->decimal('remaining_qty', 15, 4)->default(0)->change();
            $t->decimal('unit_cost', 18, 4)->default(0)->change();
        });
        Schema::table('accounts', function (Blueprint $t) {
            $t->enum('type', ['asset', 'liability', 'equity', 'revenue', 'cost_of_sales', 'expense', 'other_income', 'other_expense'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $t) {
            $t->decimal('quantity', 10, 2)->change();
            $t->decimal('remaining_qty', 15, 2)->default(0)->change();
            $t->decimal('unit_cost', 15, 2)->default(0)->change();
        });
    }
};
