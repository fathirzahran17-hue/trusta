<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pengaturan Preferensi yang kini dijalankan, lampiran dokumen, pemetaan akun Coretax, dan pilihan bahasa. */
return new class extends Migration
{
    public function up(): void
    {
        // Master yang selama ini berdiri sendiri: kategori pelanggan, kategori pemasok, dan merek barang
        Schema::table('customers', function (Blueprint $t) {
            $t->string('company_name')->nullable()->after('name');
            $t->foreignId('customer_category_id')->nullable()->constrained('customer_categories')->nullOnDelete();
        });
        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('company_name')->nullable()->after('name');
            $t->foreignId('supplier_category_id')->nullable()->constrained('supplier_categories')->nullOnDelete();
        });
        Schema::table('products', fn (Blueprint $t) => $t->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete());

        Schema::table('companies', function (Blueprint $t) {
            $t->date('data_start_date')->nullable()->after('closing_date');
            $t->boolean('prevent_over_receive')->default(true)->change();
        });
        // Selama ini penerimaan melebihi pesanan selalu ditolak, apa pun isi pengaturannya
        DB::table('companies')->update(['prevent_over_receive' => true]);

        Schema::table('customers', fn (Blueprint $t) => $t->string('custom_attr_1')->nullable());
        Schema::table('products', fn (Blueprint $t) => $t->string('custom_attr_1')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->string('locale', 5)->nullable());
        Schema::table('accounts', fn (Blueprint $t) => $t->string('coretax_code', 8)->nullable());

        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('attachable_type', 40);
            $t->unsignedBigInteger('attachable_id');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime', 100)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('coretax_fiscal_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('tax_year');
            $t->string('code', 8);
            $t->decimal('non_object', 18, 2)->default(0);
            $t->decimal('final_income', 18, 2)->default(0);
            $t->decimal('positive', 18, 2)->default(0);
            $t->decimal('negative', 18, 2)->default(0);
            $t->string('adjustment_codes', 120)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'tax_year', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('customer_category_id');
            $t->dropColumn('company_name');
        });
        Schema::table('suppliers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('supplier_category_id');
            $t->dropColumn('company_name');
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('brand_id'));

        Schema::dropIfExists('coretax_fiscal_rows');
        Schema::dropIfExists('attachments');
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('coretax_code'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('locale'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('custom_attr_1'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('custom_attr_1'));
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('data_start_date');
            $t->boolean('prevent_over_receive')->default(false)->change();
        });
    }
};
