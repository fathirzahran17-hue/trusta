<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->comment('Kode aset unik, misal: FA-001');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('acquisition_date');         // Tanggal perolehan
            $table->decimal('acquisition_cost', 15, 2)->default(0); // Harga perolehan
            $table->integer('useful_life_months')->default(48);     // Umur manfaat (bulan)
            $table->decimal('salvage_value', 15, 2)->default(0);    // Nilai sisa / residu
            $table->string('depreciation_method')->default('straight_line');
            $table->string('location')->nullable();      // Lokasi fisik aset
            $table->string('condition')->default('baik'); // baik, rusak_ringan, rusak_berat
            $table->string('status')->default('aktif');   // aktif, dijual, dihapus, arsip
            $table->string('serial_number')->nullable();  // Nomor seri
            $table->string('brand')->nullable();          // Merek
            $table->string('supplier_name')->nullable();  // Nama pemasok
            $table->string('invoice_number')->nullable(); // Nomor faktur pembelian
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
