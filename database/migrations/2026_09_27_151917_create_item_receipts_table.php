<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('restrict');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->onDelete('set null');
            $table->string('receipt_number')->unique();
            $table->date('receipt_date');
            $table->string('delivery_note_number')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled'])->default('draft');
            $table->foreignId('journal_id')->nullable()->constrained('journals')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });

        Schema::create('item_receipt_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_receipt_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->string('description')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_receipt_details');
        Schema::dropIfExists('item_receipts');
    }
};
