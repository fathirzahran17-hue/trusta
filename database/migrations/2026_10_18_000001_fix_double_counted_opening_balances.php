<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Saldo awal dulu tersimpan dua kali: di kolom accounts.opening_balance DAN di jurnal "Saldo Awal Sistem",
 * sehingga saldo akun (opening_balance + mutasi) terhitung ganda. Jurnal dijadikan satu-satunya sumber.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('journals')->where('description', 'Saldo Awal Sistem')->get() as $j) {
            $ids = DB::table('journal_details')->where('journal_id', $j->id)
                ->where('description', 'not like', 'Penyeimbang%')->pluck('account_id');
            if ($ids->isNotEmpty()) {
                DB::table('accounts')->where('company_id', $j->company_id)->whereIn('id', $ids)->update(['opening_balance' => 0]);
            }
        }
    }

    public function down(): void {}
};
