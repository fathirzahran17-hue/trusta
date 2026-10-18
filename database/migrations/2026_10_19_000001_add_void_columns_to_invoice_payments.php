<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pembayaran yang salah dibatalkan (bukan dihapus) agar jejak audit tetap ada. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('journal_id');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'voided_by']);
        });
    }
};
