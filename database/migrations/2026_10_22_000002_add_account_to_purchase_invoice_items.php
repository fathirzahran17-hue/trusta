<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Baris faktur pembelian boleh langsung memakai akun (mis. Biaya Listrik) tanpa produk/jasa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', fn (Blueprint $t) => $t->dropColumn('account_id'));
    }
};
