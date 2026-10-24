<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran/faktur yang sudah dibatalkan sebelumnya meninggalkan jurnal asli + jurnal balik (VOID-) di Buku Besar.
 * Sekarang yang dibatalkan tidak tercatat di GL, jadi pasangan jurnal lama itu dibersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $drop = function (int $companyId, ?int $originalId, string $reverseDescription) {
            $ids = [];
            if ($originalId) {
                $ids[] = $originalId;
            }
            $rev = DB::table('journals')->where('company_id', $companyId)->where('ref_number', 'like', 'VOID-%')
                ->where('description', $reverseDescription)->pluck('id')->all();
            $ids = array_values(array_unique(array_merge($ids, $rev)));
            if ($ids) {
                DB::table('journal_details')->whereIn('journal_id', $ids)->delete();
                DB::table('journals')->whereIn('id', $ids)->delete();
            }
        };

        // pembayaran yang dibatalkan
        $voided = DB::table('invoice_payments')->whereNotNull('voided_at')->get();
        $byJournal = $voided->whereNotNull('journal_id')->groupBy('journal_id');
        foreach ($byJournal as $journalId => $rows) {
            $first = $rows->first();
            $orig = DB::table('journals')->where('id', $journalId)->first();
            if ($orig) {
                $drop((int) $first->company_id, (int) $journalId, 'Pembatalan ' . $orig->ref_number);
            }
            DB::table('invoice_payments')->where('journal_id', $journalId)->update(['journal_id' => null]);
        }

        // faktur yang dibatalkan
        foreach (['sales_invoices', 'purchase_invoices'] as $table) {
            foreach (DB::table($table)->where('status', 'void')->get() as $inv) {
                $drop((int) $inv->company_id, $inv->journal_id ? (int) $inv->journal_id : null, 'Pembatalan faktur ' . $inv->invoice_number);
                if ($inv->journal_id) {
                    DB::table($table)->where('id', $inv->id)->update(['journal_id' => null]);
                }
            }
        }
    }

    public function down(): void
    {
        // data jurnal yang dihapus tidak dapat dipulihkan
    }
};
