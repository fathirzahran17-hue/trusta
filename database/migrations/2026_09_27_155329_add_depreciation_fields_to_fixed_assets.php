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
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->decimal('accumulated_depreciation', 15, 2)->default(0)->after('salvage_value');
            $table->date('last_depreciation_date')->nullable()->after('accumulated_depreciation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['accumulated_depreciation', 'last_depreciation_date']);
        });
    }
};
