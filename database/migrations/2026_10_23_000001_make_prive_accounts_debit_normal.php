<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prive adalah akun lawan modal (penarikan pemilik): saldo normalnya debit, bukan kredit.
 * Saldo awal ikut dibalik tandanya supaya nilai akhir akun tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['accounts', 'default_accounts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = Schema::hasColumn($table, 'opening_balance') ? ['id', 'opening_balance'] : ['id'];
            DB::table($table)->where('type', 'equity')->where('normal_balance', 'credit')->where('name', 'like', 'Prive%')
                ->get($cols)->each(function ($a) use ($table) {
                    $set = ['normal_balance' => 'debit'];
                    if (isset($a->opening_balance)) {
                        $set['opening_balance'] = -1 * (float) $a->opening_balance;
                    }
                    DB::table($table)->where('id', $a->id)->update($set);
                });
        }
    }

    public function down(): void
    {
        // tidak dikembalikan
    }
};
