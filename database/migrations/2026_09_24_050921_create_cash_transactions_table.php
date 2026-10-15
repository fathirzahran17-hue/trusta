<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('transaction_number', 30);
            $table->enum('type', ['cash_in', 'cash_out', 'transfer']);
            $table->foreignId('account_id')->constrained()->onDelete('restrict'); // akun kas/bank sumber
            $table->unsignedBigInteger('target_account_id')->nullable(); // untuk transfer
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->date('transaction_date');
            $table->foreignId('journal_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('sales_invoice_id')->nullable();
            $table->unsignedBigInteger('purchase_invoice_id')->nullable();
            $table->unsignedBigInteger('contra_account_id')->nullable(); // akun lawan untuk kas masuk/keluar manual
            $table->timestamps();

            $table->unique(['company_id', 'transaction_number']);
            $table->index(['company_id', 'transaction_date']);

            $table->foreign('target_account_id')->references('id')->on('accounts')->onDelete('restrict');
            $table->foreign('contra_account_id')->references('id')->on('accounts')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
