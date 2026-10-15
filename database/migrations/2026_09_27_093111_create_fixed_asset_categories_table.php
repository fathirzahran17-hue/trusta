<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');                    // Kendaraan, Gedung, Peralatan Kantor, dll
            $table->string('depreciation_method')->default('straight_line'); // straight_line, declining_balance
            $table->integer('useful_life_years')->default(4);    // Umur manfaat dalam tahun
            $table->string('account_asset_code')->nullable();    // Kode akun aset (misal: 1-2001)
            $table->string('account_depreciation_code')->nullable(); // Kode akun akumulasi penyusutan
            $table->string('account_expense_code')->nullable();  // Kode akun beban penyusutan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_categories');
    }
};
