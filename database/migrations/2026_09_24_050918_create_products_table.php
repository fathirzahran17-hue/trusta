<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->onDelete('set null');
            $table->string('sku')->nullable();
            $table->string('name');
            $table->enum('type', ['product', 'service'])->default('product');
            $table->string('unit', 20)->default('pcs'); // pcs, unit, jam, kg, dll
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->decimal('buy_price', 15, 2)->default(0);
            $table->unsignedBigInteger('revenue_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
