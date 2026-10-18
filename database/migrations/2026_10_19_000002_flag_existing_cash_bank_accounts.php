<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Akun seperti "Kas Tunai" dan "Bank BCA" belum bertanda kas/bank sehingga tidak muncul di
 * Daftar Kas & Bank dan tidak bisa dipilih saat menerima/membayar faktur. Tandai yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parents = DB::table('accounts')->whereNotNull('parent_id')->pluck('parent_id')->unique()->flip();

        DB::table('accounts')->where('type', 'asset')->where('is_cash_bank', false)->get(['id', 'name', 'type', 'code'])
            ->each(function ($a) use ($parents) {
                if (isset($parents[$a->id]) || str_ends_with((string) $a->code, '-0000')) {
                    return; // akun induk
                }
                if (Account::nameLooksLikeCashBank($a->name, $a->type)) {
                    DB::table('accounts')->where('id', $a->id)->update(['is_cash_bank' => true]);
                }
            });
    }

    public function down(): void
    {
        // tidak ada yang perlu dikembalikan
    }
};
