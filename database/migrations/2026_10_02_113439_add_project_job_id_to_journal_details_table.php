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
        Schema::table('journal_details', function (Blueprint $table) {
            $table->foreignId('project_job_id')->nullable()->after('account_id')->constrained('project_jobs')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('journal_details', function (Blueprint $table) {
            $table->dropForeign(['project_job_id']);
            $table->dropColumn('project_job_id');
        });
    }
};
