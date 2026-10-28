<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\InvoicePayment;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan konsistensi data akuntansi: mencari faktur/pembayaran yang tidak sinkron dengan Buku Besar.
 * Hanya membaca data (tidak mengubah apa pun).
 */
class ConsistencyController extends Controller
{
    public function index()
    {
        $cid = (int) auth()->user()->company_id;
        $issues = [];
        $add = function (string $group, string $text, ?string $url = null, ?string $hint = null, ?array $fix = null) use (&$issues) {
            $issues[$group][] = ['text' => $text, 'url' => $url, 'hint' => $hint, 'fix' => $fix];
        };

        // 1. Jurnal tidak seimbang
        $unbalanced = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $cid)->where('journals.status', 'posted')
            ->groupBy('journals.id', 'journals.ref_number')
            ->havingRaw('ABS(SUM(journal_details.debit) - SUM(journal_details.credit)) > 0.01')
            ->select('journals.id', 'journals.ref_number', DB::raw('SUM(journal_details.debit) - SUM(journal_details.credit) as diff'))->get();
        foreach ($unbalanced as $j) {
            $add('Jurnal tidak seimbang', "Jurnal {$j->ref_number}: selisih debit - kredit Rp " . number_format($j->diff, 2, ',', '.'), route('client.journals.show', $j->id), 'Perbaiki lewat Jurnal Umum.');
        }

        foreach (['sales' => [SalesInvoice::class, 'sales_invoice_id', 'client.sales-invoices.show', 'Faktur Penjualan'],
                  'purchase' => [PurchaseInvoice::class, 'purchase_invoice_id', 'client.purchase-invoices.show', 'Faktur Pembelian']] as $kind => [$model, $fk, $route, $label]) {
            $invoices = $model::where('company_id', $cid)->get();
            $journalIds = Journal::where('company_id', $cid)->pluck('id')->flip();

            foreach ($invoices as $inv) {
                $url = route($route, $inv->id);
                $active = $inv->status !== 'void';

                // 2. Faktur aktif tanpa jurnal
                if ($active && (! $inv->journal_id || ! $journalIds->has($inv->journal_id))) {
                    $add("$label tanpa jurnal", "{$inv->invoice_number}: jurnal faktur tidak ditemukan di Buku Besar (hutang/piutang & pendapatan/biaya tidak tercatat).", $url,
                        'Jurnal akan dibuat ulang dari isi faktur (tanggal, akun biaya/persediaan, pajak, hutang) bila tombol di kanan ditekan.',
                        $kind === 'purchase' ? ['url' => route('client.consistency.rebuild-purchase', $inv->id), 'label' => 'Buat ulang jurnal'] : null);
                }

                // 3. Dibayar vs total pembayaran aktif
                $paid = (float) InvoicePayment::where('company_id', $cid)->where($fk, $inv->id)->whereNull('voided_at')->sum('amount')
                    + (float) \App\Models\DownPaymentApplication::where('company_id', $cid)->where($fk, $inv->id)->whereNull('voided_at')->sum('amount');
                if ($active && abs($paid - (float) $inv->amount_paid) > 0.01) {
                    $add("$label: jumlah dibayar tidak cocok", "{$inv->invoice_number}: tercatat dibayar Rp " . number_format($inv->amount_paid, 2, ',', '.') . ', total pembayaran aktif Rp ' . number_format($paid, 2, ',', '.'), $url);
                }

                // 4. Faktur dibatalkan tanpa jurnal balik
                if (! $active && $inv->journal_id && $journalIds->has($inv->journal_id)) {
                    $has = Journal::where('company_id', $cid)->where('description', 'Pembatalan faktur ' . $inv->invoice_number)->exists();
                    if (! $has) {
                        $add("$label dibatalkan tanpa jurnal balik", "{$inv->invoice_number}: status dibatalkan tetapi jurnalnya masih berlaku di Buku Besar.", $url);
                    }
                }
            }

            // 5. Pembayaran aktif pada faktur yang dibatalkan / hilang, atau tanpa jurnal
            $payments = InvoicePayment::where('company_id', $cid)->whereNotNull($fk)->whereNull('voided_at')->get();
            foreach ($payments as $p) {
                $inv = $invoices->firstWhere('id', $p->{$fk});
                $payUrl = route($kind === 'sales' ? 'client.sales.receipts' : 'client.purchases.payments');
                if (! $inv || $inv->status === 'void') {
                    $add('Pembayaran pada faktur yang dibatalkan', "{$p->payment_number}: Rp " . number_format($p->amount, 2, ',', '.') . ' masih aktif padahal faktur ' . ($inv->invoice_number ?? '(terhapus)') . ' dibatalkan.', $payUrl,
                        'Klik "Batalkan" pada pembayaran ini agar Buku Besar kembali seimbang.');
                }
                if (! $p->journal_id || ! $journalIds->has($p->journal_id)) {
                    $add('Pembayaran tanpa jurnal', "{$p->payment_number}: jurnal pembayaran tidak ditemukan.", $payUrl);
                }
            }
        }

        // 6. Pembayaran dibatalkan tanpa jurnal balik
        foreach (InvoicePayment::where('company_id', $cid)->whereNotNull('voided_at')->whereNotNull('journal_id')->get()->unique('journal_id') as $p) {
            $orig = Journal::find($p->journal_id);
            if ($orig && ! Journal::where('company_id', $cid)->where('description', 'Pembatalan ' . $orig->ref_number)->exists()) {
                $add('Pembayaran dibatalkan tanpa jurnal balik', "{$p->payment_number}: dibatalkan, tetapi jurnal {$orig->ref_number} tidak punya jurnal balik sehingga saldo akun masih terpengaruh.", route('client.journals.show', $orig->id));
            }
        }

        // 7. Aset tetap tanpa jurnal perolehan (info: abaikan bila asetnya sudah masuk Saldo Awal)
        foreach (\App\Models\FixedAsset::where('company_id', $cid)->whereNull('acquisition_journal_id')->where('acquisition_cost', '>', 0)->get() as $a) {
            $add('Aset tetap tanpa jurnal perolehan', "{$a->code} - {$a->name}: Rp " . number_format($a->acquisition_cost, 2, ',', '.') . ' belum punya jurnal perolehan.',
                route('client.fixed_assets.edit', $a->id), 'Abaikan bila asetnya sudah termasuk Saldo Awal. Bila belum, buka Edit lalu centang "Catat jurnal perolehan".');
        }

        // 8. Akun Laba Ditahan ganda yang ikut terpakai (bawaan 3-0001 Retained Earnings & 3-0004 Laba Ditahan): laporan hanya memakai satu akun
        $primary = \App\Services\AccountResolver::retainedEarnings($cid);
        $re = \App\Models\Account::where('company_id', $cid)->where('type', 'equity')->where(function ($q) {
            $q->where('name', 'like', '%laba ditahan%')->orWhere('name', 'like', '%retained earning%');
        })->get();
        $extras = $re->filter(fn ($a) => $primary && $a->id !== $primary->id
            && (abs((float) $a->opening_balance) > 0.004 || JournalDetail::where('account_id', $a->id)->exists()));
        foreach ($extras as $a) {
            $add('Akun Laba Ditahan ganda', "{$a->code} {$a->name} berisi saldo/mutasi, padahal laporan memakai {$primary->code} {$primary->name} sebagai Laba Ditahan.",
                route('client.accounts.show', $a->id), 'Pindahkan saldo/jurnalnya ke akun yang dipakai laporan (jurnal pemindahan), lalu akun ini boleh dihapus/dinonaktifkan.');
        }

        // 9. Nilai Persediaan di Buku Besar vs nilai stok (stok x harga pokok rata-rata)
        $invAccs = \App\Services\AccountResolver::inventoryAccounts($cid);
        if ($invAcc = $invAccs->first()) {
            $row = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
                ->where('journals.company_id', $cid)->where('journals.status', 'posted')->whereIn('journal_details.account_id', $invAccs->pluck('id'))
                ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')->first();
            $book = round((float) $invAccs->sum('opening_balance') + (float) $row->d - (float) $row->c, 2);
            if ($invAccs->count() > 1) { $invAcc = (object) ['code' => '', 'name' => 'Persediaan (' . $invAccs->count() . ' akun)']; }
            $stock = round(\App\Models\Product::where('company_id', $cid)->where('type', 'goods')->get()->sum(fn ($p) => (float) $p->stock * (float) $p->buy_price), 2);
            if (abs($book - $stock) > 1) {
                $add('Nilai persediaan tidak sama dengan stok', "Saldo akun {$invAcc->code} {$invAcc->name} Rp " . number_format($book, 2, ',', '.') . ', sedangkan nilai stok barang (stok × harga pokok) Rp ' . number_format($stock, 2, ',', '.') . ' (selisih Rp ' . number_format($book - $stock, 2, ',', '.') . ').',
                    route('client.inventory.stock_opname'), 'Biasanya karena penyesuaian/permintaan barang lama yang dulu belum dijurnal, pembelian barang tanpa Penerimaan, atau saldo awal persediaan yang belum diisi. Koreksi lewat Jurnal Umum (lawan: Selisih Persediaan/HPP) setelah dicek.');
            }
        }

        return view('client.consistency.index', compact('issues'));
    }

    public function rebuildPurchase(PurchaseInvoice $purchase_invoice)
    {
        abort_if((int) $purchase_invoice->company_id !== (int) auth()->user()->company_id, 403);
        try {
            $msg = \App\Services\InvoiceJournalRebuilder::purchase($purchase_invoice, auth()->id());
        } catch (\DomainException $e) {
            return redirect()->route('client.consistency')->with('error', $e->getMessage());
        }

        return redirect()->route('client.consistency')->with('success', $msg);
    }
}
