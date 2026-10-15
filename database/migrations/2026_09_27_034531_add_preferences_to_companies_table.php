<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('default_tax_rate', 5, 2)->default(11.00)->after('is_premium');
            $table->string('tax_rounding')->default('Normal (Matematis)')->after('default_tax_rate');
            $table->string('default_sales_term')->default('Net 30')->after('tax_rounding');
            $table->boolean('warn_below_cost')->default(true)->after('default_sales_term');
            $table->string('default_purchase_term')->default('Net 30')->after('warn_below_cost');
            $table->boolean('prevent_over_receive')->default(false)->after('default_purchase_term');
            $table->boolean('prevent_prior_transactions')->default(true)->after('prevent_over_receive');
            $table->date('closing_date')->nullable()->after('prevent_prior_transactions');
            $table->boolean('notify_approver')->default(true)->after('closing_date');
            $table->integer('max_attachment_size')->default(5)->after('notify_approver');
            $table->string('allowed_attachment_types')->default('PDF,JPG,PNG')->after('max_attachment_size');
            $table->string('custom_customer_attr_1')->nullable()->after('allowed_attachment_types');
            $table->string('custom_item_attr_1')->nullable()->after('custom_customer_attr_1');
            $table->string('price_decimal_format')->default('2 Angka (0.00)')->after('custom_item_attr_1');
            $table->string('default_language')->default('Bahasa Indonesia')->after('price_decimal_format');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'default_tax_rate', 'tax_rounding', 'default_sales_term', 'warn_below_cost',
                'default_purchase_term', 'prevent_over_receive', 'prevent_prior_transactions',
                'closing_date', 'notify_approver', 'max_attachment_size', 'allowed_attachment_types',
                'custom_customer_attr_1', 'custom_item_attr_1', 'price_decimal_format', 'default_language'
            ]);
        });
    }
};
