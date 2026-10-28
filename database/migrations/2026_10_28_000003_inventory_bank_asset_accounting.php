<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Penyesuaian/pemakaian/stok opname persediaan kini dijurnal: mutasi stok menyimpan jurnalnya.
 * - Rekonsiliasi bank: baris jurnal kas/bank bisa ditandai "cocok dengan rekening koran".
 * - Pelepasan (penjualan/penghapusan) aset tetap dengan jurnal laba/rugi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $t) {
            if (! Schema::hasColumn('inventory_movements', 'journal_id')) $t->unsignedBigInteger('journal_id')->nullable()->index();
            if (! Schema::hasColumn('inventory_movements', 'voided_at')) $t->timestamp('voided_at')->nullable();
        });

        if (! Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->unsignedBigInteger('account_id')->index();
                $t->date('statement_date');
                $t->decimal('statement_balance', 18, 2);
                $t->decimal('cleared_balance', 18, 2);
                $t->unsignedInteger('cleared_count')->default(0);
                $t->string('notes')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('voided_at')->nullable();
                $t->timestamps();
            });
        }
        Schema::table('journal_details', function (Blueprint $t) {
            if (! Schema::hasColumn('journal_details', 'bank_reconciliation_id')) $t->unsignedBigInteger('bank_reconciliation_id')->nullable()->index();
        });

        Schema::table('fixed_assets', function (Blueprint $t) {
            if (! Schema::hasColumn('fixed_assets', 'disposal_date')) $t->date('disposal_date')->nullable();
            if (! Schema::hasColumn('fixed_assets', 'disposal_proceeds')) $t->decimal('disposal_proceeds', 18, 2)->nullable();
            if (! Schema::hasColumn('fixed_assets', 'disposal_journal_id')) $t->unsignedBigInteger('disposal_journal_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', fn (Blueprint $t) => $t->dropColumn(['disposal_date', 'disposal_proceeds', 'disposal_journal_id']));
        Schema::table('journal_details', function (Blueprint $t) { $t->dropIndex(['bank_reconciliation_id']); $t->dropColumn('bank_reconciliation_id'); });
        Schema::dropIfExists('bank_reconciliations');
        Schema::table('inventory_movements', function (Blueprint $t) { $t->dropIndex(['journal_id']); $t->dropColumn(['journal_id', 'voided_at']); });
    }
};
