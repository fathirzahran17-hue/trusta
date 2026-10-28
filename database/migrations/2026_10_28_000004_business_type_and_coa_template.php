<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Bidang usaha perusahaan (jasa/dagang/dagang_jasa/manufaktur).
 * - Template CoA per bidang (kolom industries; kosong = semua bidang). Template lama diganti CoA standar baru
 *   (hanya untuk perusahaan yang mendaftar setelah ini; akun perusahaan yang sudah ada tidak diubah).
 * - Akun persediaan per barang (mis. Bahan Baku vs Barang Jadi untuk manufaktur).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'business_type')) {
            Schema::table('companies', fn (Blueprint $t) => $t->string('business_type', 20)->nullable());
        }
        if (! Schema::hasColumn('default_accounts', 'industries')) {
            Schema::table('default_accounts', fn (Blueprint $t) => $t->string('industries', 60)->nullable());
        }
        if (! Schema::hasColumn('products', 'inventory_account_id')) {
            Schema::table('products', fn (Blueprint $t) => $t->unsignedBigInteger('inventory_account_id')->nullable());
        }

        // Template lama (belum dibagi per bidang) diganti CoA standar baru.
        if (! DB::table('default_accounts')->whereNotNull('industries')->exists()) {
            \App\Services\CoaTemplate::install();
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('inventory_account_id'));
        Schema::table('default_accounts', fn (Blueprint $t) => $t->dropColumn('industries'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('business_type'));
    }
};
