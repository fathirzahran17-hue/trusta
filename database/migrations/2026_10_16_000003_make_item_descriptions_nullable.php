<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom "Keterangan" pada baris dokumen bersifat opsional di form, tetapi
 * kolom databasenya NOT NULL sehingga baris tanpa keterangan membuat simpan gagal.
 */
return new class extends Migration
{
    private array $tables = [
        'purchase_order_items',
        'sales_invoice_items',
        'purchase_invoice_items',
        'sales_return_items',
        'purchase_return_items',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'description')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->string('description')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Dibiarkan nullable agar data yang sudah tersimpan tidak bermasalah.
    }
};
