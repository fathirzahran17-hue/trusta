<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Produksi / perakitan: resep (BOM) dan perintah produksi beserta bahan & biaya tambahannya. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boms')) {
            Schema::create('boms', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->unsignedBigInteger('product_id')->index();
                $t->string('name');
                $t->decimal('output_qty', 15, 2)->default(1);
                $t->string('notes')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('bom_items')) {
            Schema::create('bom_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('bom_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->decimal('quantity', 15, 4);
            });
        }
        if (! Schema::hasTable('production_orders')) {
            Schema::create('production_orders', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->string('number', 50);
                $t->date('date');
                $t->unsignedBigInteger('product_id');
                $t->unsignedBigInteger('bom_id')->nullable();
                $t->decimal('quantity', 15, 2);
                $t->decimal('material_cost', 18, 2)->default(0);
                $t->decimal('extra_cost', 18, 2)->default(0);
                $t->decimal('total_cost', 18, 2)->default(0);
                $t->decimal('unit_cost', 18, 4)->default(0);
                $t->unsignedBigInteger('journal_id')->nullable();
                $t->string('notes')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'number']);
            });
        }
        if (! Schema::hasTable('production_order_items')) {
            Schema::create('production_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_order_id')->index();
                $t->unsignedBigInteger('product_id');
                $t->decimal('quantity', 15, 4);
                $t->decimal('unit_cost', 18, 4)->default(0);
                $t->decimal('total', 18, 2)->default(0);
            });
        }
        if (! Schema::hasTable('production_order_costs')) {
            Schema::create('production_order_costs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('production_order_id')->index();
                $t->unsignedBigInteger('account_id');
                $t->string('description')->nullable();
                $t->decimal('amount', 18, 2);
            });
        }
    }

    public function down(): void
    {
        foreach (['production_order_costs', 'production_order_items', 'production_orders', 'bom_items', 'boms'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
