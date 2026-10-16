<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom products.type semula ENUM('product','service') padahal seluruh kode
 * memakai 'goods' / 'service'. Diubah menjadi string dan data lama dinormalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('goods')->change();
        });

        DB::table('products')->whereIn('type', ['product', ''])->orWhereNull('type')->update(['type' => 'goods']);
    }

    public function down(): void
    {
        // Tidak dikembalikan ke enum agar data 'goods' tidak hilang.
    }
};
