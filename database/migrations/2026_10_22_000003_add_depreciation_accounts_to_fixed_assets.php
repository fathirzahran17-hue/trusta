<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun jurnal penyusutan bisa dipilih per aset (diingat dari penyusutan terakhir). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
            $table->unsignedBigInteger('accumulated_account_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', fn (Blueprint $t) => $t->dropColumn(['depreciation_expense_account_id', 'accumulated_account_id']));
    }
};
