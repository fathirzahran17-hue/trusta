<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('ref_number', 30); // JU-2026-001, JM-2026-001
            $table->enum('type', ['general', 'memorial', 'auto_sales', 'auto_purchase', 'auto_cash']);
            $table->date('transaction_date');
            $table->string('description');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['company_id', 'ref_number']);
            $table->index(['company_id', 'transaction_date']);
            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
