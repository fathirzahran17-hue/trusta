<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pelunasan bisa di-split ke akun lain (mis. PPh yang dipotong pelanggan / ditahan ke pemasok).
 * Akun yang boleh dipakai ditandai lewat centang di Akun Perkiraan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('is_settlement_deduction')->default(false)->after('is_cash_bank');
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('deduction_amount', 18, 2)->default(0)->after('amount');
        });

        $parents = DB::table('accounts')->whereNotNull('parent_id')->pluck('parent_id')->unique()->flip();
        DB::table('accounts')->whereIn('type', ['asset', 'liability'])->get(['id', 'name', 'type', 'code'])
            ->each(function ($a) use ($parents) {
                if (isset($parents[$a->id]) || str_ends_with((string) $a->code, '-0000')) {
                    return;
                }
                if (Account::nameLooksLikeSettlementDeduction($a->name, $a->type)) {
                    DB::table('accounts')->where('id', $a->id)->update(['is_settlement_deduction' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', fn (Blueprint $t) => $t->dropColumn('deduction_amount'));
        Schema::table('accounts', fn (Blueprint $t) => $t->dropColumn('is_settlement_deduction'));
    }
};
