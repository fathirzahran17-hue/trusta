<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Free, Professional, Enterprise
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('max_accounts')->default(15);
            $table->integer('max_transactions')->default(20);
            $table->integer('max_users')->default(1);
            $table->boolean('has_reports')->default(false);
            $table->boolean('has_cash_bank')->default(false);
            $table->boolean('has_export')->default(false);
            $table->boolean('has_auto_backup')->default(false);
            $table->integer('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
