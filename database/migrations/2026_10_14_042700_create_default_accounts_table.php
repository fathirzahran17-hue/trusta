<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type', 30); // asset, liability, equity, revenue, cost_of_sales, expense, other_income, other_expense
            $table->string('normal_balance', 10); // debit, credit
            $table->boolean('is_system')->default(false); // Header akun (tidak bisa dihapus user)
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('default_accounts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_accounts');
    }
};
