<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('down_payment_applications')) {
            return;
        }
        Schema::create('down_payment_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('down_payment_id')->index();
            $table->unsignedBigInteger('sales_invoice_id')->nullable()->index();
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->date('applied_date');
            $table->unsignedBigInteger('journal_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('down_payment_applications');
    }
};
