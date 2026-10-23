<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Status PO tidak lagi dibatasi enum agar bisa bernilai "cancelled" (Dibatalkan). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }

    public function down(): void
    {
        // dibiarkan sebagai string; mengembalikan ke enum bisa gagal bila sudah ada status "cancelled"
    }
};
