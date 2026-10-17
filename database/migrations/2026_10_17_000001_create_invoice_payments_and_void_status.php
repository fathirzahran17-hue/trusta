<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Tabel pembayaran faktur (mendukung cicilan).
 * - Kolom status faktur diubah dari ENUM ke string agar bisa bernilai 'void'
 *   (kode dashboard & laporan sudah memakai status tersebut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index();
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        foreach (['sales_invoices', 'purchase_invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('status', 20)->default('unpaid')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
