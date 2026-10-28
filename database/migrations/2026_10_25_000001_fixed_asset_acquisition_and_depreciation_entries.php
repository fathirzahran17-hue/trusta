<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_assets', 'asset_account_id')) {
                $table->unsignedBigInteger('asset_account_id')->nullable();
            }
            if (!Schema::hasColumn('fixed_assets', 'acquisition_journal_id')) {
                $table->unsignedBigInteger('acquisition_journal_id')->nullable();
            }
        });

        if (!Schema::hasTable('depreciation_entries')) {
            Schema::create('depreciation_entries', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->index();
                $table->unsignedBigInteger('fixed_asset_id')->index();
                $table->unsignedBigInteger('journal_id')->nullable()->index();
                $table->string('period', 7);
                $table->decimal('amount', 15, 2);
                $table->date('prev_last_date')->nullable();
                $table->timestamp('voided_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['asset_account_id', 'acquisition_journal_id']);
        });
    }
};
