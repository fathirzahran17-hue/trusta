<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnal balik ditautkan ke jurnal aslinya (reverses_journal_id) dan diberi tanggal yang sama dengan jurnal asli,
 * sehingga transaksi yang dibatalkan beserta pembaliknya bisa disembunyikan dari riwayat mutasi tanpa mengubah saldo.
 * Jurnal balik lama (bertanggal hari pembatalan) ikut dipindah ke tanggal aslinya, kecuali periode itu sudah Tutup Buku.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journals', 'reverses_journal_id')) {
            Schema::table('journals', fn (Blueprint $t) => $t->unsignedBigInteger('reverses_journal_id')->nullable()->index());
        }

        $closing = DB::table('companies')->pluck('closing_date', 'id');
        $revs = DB::table('journals')->where('ref_number', 'like', 'VOID-%')->whereNull('reverses_journal_id')->get();
        foreach ($revs as $r) {
            $desc = (string) $r->description;
            $orig = null;
            if (preg_match('/^Pembatalan faktur (.+)$/', $desc, $m)) {
                $jid = DB::table('sales_invoices')->where('company_id', $r->company_id)->where('invoice_number', $m[1])->value('journal_id')
                    ?: DB::table('purchase_invoices')->where('company_id', $r->company_id)->where('invoice_number', $m[1])->value('journal_id');
                $orig = $jid ? DB::table('journals')->find($jid) : null;
            } elseif (preg_match('/^Pembatalan perolehan aset (.+)$/', $desc, $m)) {
                $jid = DB::table('fixed_assets')->where('company_id', $r->company_id)->where('code', $m[1])->value('acquisition_journal_id');
                $orig = $jid ? DB::table('journals')->find($jid) : null;
            } elseif (preg_match('/^Pembatalan (.+)$/', $desc, $m)) {
                $orig = DB::table('journals')->where('company_id', $r->company_id)->where('ref_number', $m[1])->where('id', '<', $r->id)->orderByDesc('id')->first();
            }
            if (! $orig) {
                continue;
            }
            $upd = ['reverses_journal_id' => $orig->id];
            $lock = $closing[$r->company_id] ?? null;
            if (! $lock || substr((string) $orig->transaction_date, 0, 10) > substr((string) $lock, 0, 10)) {
                $upd['transaction_date'] = $orig->transaction_date;
            }
            DB::table('journals')->where('id', $r->id)->update($upd);
        }
    }

    public function down(): void
    {
        Schema::table('journals', fn (Blueprint $t) => $t->dropColumn('reverses_journal_id'));
    }
};
