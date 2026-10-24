<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\PurchaseInvoice;

/**
 * Membuat ulang jurnal Faktur Pembelian yang hilang (mis. terhapus manual dari Jurnal Umum)
 * dengan aturan yang sama seperti saat faktur dibuat. Bila jurnal bernomor sama masih ada, hanya disambungkan kembali.
 */
class InvoiceJournalRebuilder
{
    /** @return string pesan hasil */
    public static function purchase(PurchaseInvoice $inv, int $userId): string
    {
        if ($inv->status === 'void') {
            throw new \DomainException('Faktur yang sudah dibatalkan tidak perlu dijurnal ulang.');
        }
        if ($inv->journal_id && Journal::where('id', $inv->journal_id)->exists()) {
            throw new \DomainException('Faktur ini sudah punya jurnal.');
        }

        $cid = (int) $inv->company_id;

        // Jurnal bernomor sama masih ada (hanya putus sambungan)? Sambungkan saja.
        if ($existing = Journal::where('company_id', $cid)->where('ref_number', $inv->invoice_number)->first()) {
            $inv->journal_id = $existing->id;
            $inv->save();

            return "Jurnal {$existing->ref_number} (tanggal " . $existing->transaction_date->format('d/m/Y') . ') ditemukan dan disambungkan kembali ke faktur.';
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($inv, $cid, $userId) {
            $ap = AccountResolver::payable($cid);
            if (! $ap) {
                throw new \DomainException('Akun Hutang Usaha belum disiapkan di Chart of Accounts.');
            }

            $factor = 1 - ((float) $inv->discount_percent / 100);
            $groups = [];
            foreach ($inv->items as $item) {
                if (! $item->product_id) {
                    $target = $item->account_id;
                } else {
                    $prod = Product::find($item->product_id);
                    if ($prod && $prod->type === 'goods') {
                        $inventory = AccountResolver::inventory($cid);
                        if (! $inventory) {
                            throw new \DomainException('Akun Persediaan belum disiapkan di Chart of Accounts.');
                        }
                        $target = $inventory->id;
                    } else {
                        $target = $prod?->expense_account_id;
                    }
                }
                if (! $target) {
                    throw new \DomainException('Baris "' . ($item->description ?: 'tanpa nama') . '" belum punya akun biaya; jurnal tidak bisa dibuat otomatis. Buat lewat Jurnal Umum.');
                }
                $groups[$target] = ($groups[$target] ?? 0) + (float) $item->total * $factor;
            }

            $tax = (float) $inv->tax_amount;
            $total = (float) $inv->total;
            $debit = array_sum($groups) + $tax;
            $diff = round($total - $debit, 2);
            if (abs($diff) > 0.05) {
                throw new \DomainException('Isi faktur tidak cocok dengan total (selisih Rp ' . number_format($diff, 2, ',', '.') . '); buat jurnalnya manual lewat Jurnal Umum.');
            }
            if ($groups && abs($diff) > 0) {
                arsort($groups);
                $k = array_key_first($groups);
                $groups[$k] += $diff; // pembulatan
            }

            $journal = Journal::create([
                'company_id' => $cid,
                'ref_number' => $inv->invoice_number,
                'type' => 'auto_purchase',
                'transaction_date' => $inv->invoice_date,
                'description' => 'Pembelian dari ' . optional($inv->supplier)->name . ($inv->supplier_invoice_no ? ' (Ref: ' . $inv->supplier_invoice_no . ')' : '') . ' [dijurnal ulang]',
                'status' => 'posted',
                'created_by' => $userId,
            ]);
            foreach ($groups as $accountId => $amount) {
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $accountId, 'debit' => round($amount, 2), 'credit' => 0,
                    'description' => 'Biaya Pembelian ' . $inv->invoice_number]);
            }
            if ($tax > 0) {
                $taxAcc = AccountResolver::inputTax($cid, $inv->tax);
                if (! $taxAcc) {
                    throw new \DomainException('Akun Pajak Masukan belum diatur.');
                }
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $taxAcc->id, 'debit' => $tax, 'credit' => 0, 'description' => 'Pajak Masukan']);
            }
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $ap->id, 'debit' => 0, 'credit' => $total,
                'description' => 'Hutang Pembelian ' . $inv->invoice_number]);
            JournalGuard::assertBalanced($journal);

            $inv->journal_id = $journal->id;
            $inv->save();

            return 'Jurnal ' . $inv->invoice_number . ' dibuat ulang (tanggal ' . \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') . ', Hutang Rp ' . number_format($total, 2, ',', '.') . ').';
        });
    }
}
