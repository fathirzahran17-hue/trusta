<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InvoicePayment;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Pembayaran (termasuk cicilan) dan pembatalan faktur penjualan/pembelian.
 * Semua operasi memakai transaksi database dan membuat jurnal otomatis.
 */
class InvoicePaymentService
{
    private static function nextRef(int $companyId, string $prefix, string $date): string
    {
        $last = Journal::where('company_id', $companyId)
            ->where('ref_number', 'like', $prefix . '%')
            ->whereYear('transaction_date', date('Y', strtotime($date)))
            ->whereMonth('transaction_date', date('m', strtotime($date)))
            ->orderBy('id', 'desc')
            ->first();

        $next = $last ? (intval(substr($last->ref_number, -4)) + 1) : 1;

        return $prefix . date('Ym', strtotime($date)) . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /** Terapkan perubahan jumlah dibayar secara inkremental (aman terhadap retur yang sudah mengurangi sisa tagihan). */
    private static function applyPaid($invoice, float $delta): void
    {
        $paid = max(0, round((float) $invoice->amount_paid + $delta, 2));
        $due = max(0, round((float) $invoice->amount_due - $delta, 2));
        if ($due <= 0.004) {
            $due = 0;
        }
        $invoice->amount_paid = $paid;
        $invoice->amount_due = $due;
        $invoice->status = $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $invoice->save();
    }

    /** Catat pembayaran dari pelanggan untuk satu faktur. */
    public static function paySales(SalesInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId): InvoicePayment
    {
        return self::payMany('sales', (int) $inv->company_id, (int) $inv->customer_id, [$inv->id => $amount], $accountId, $date, $notes, $userId)[0];
    }

    /** Catat pembayaran ke pemasok untuk satu faktur. */
    public static function payPurchase(PurchaseInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId): InvoicePayment
    {
        return self::payMany('purchase', (int) $inv->company_id, (int) $inv->supplier_id, [$inv->id => $amount], $accountId, $date, $notes, $userId)[0];
    }

    /**
     * Satu penerimaan/pembayaran yang dialokasikan ke beberapa faktur milik SATU pelanggan/pemasok.
     * Menghasilkan satu jurnal (Kas vs Piutang/Hutang per faktur) dan satu baris pembayaran per faktur.
     *
     * @param array<int,float|string> $allocations [id faktur => jumlah]
     * @return InvoicePayment[]
     */
    public static function payMany(string $kind, int $companyId, int $partyId, array $allocations, int $accountId, string $date, ?string $notes, int $userId): array
    {
        $allocations = array_filter(array_map(fn ($v) => round((float) $v, 2), $allocations), fn ($v) => $v > 0);
        if (!$allocations) {
            throw new \DomainException('Pilih minimal satu faktur dan isi jumlah yang dibayar.');
        }

        $cash = Account::where('company_id', $companyId)->where('is_cash_bank', true)->find($accountId);
        if (!$cash) {
            throw new \DomainException('Akun kas/bank tidak valid.');
        }
        $counter = $kind === 'sales' ? AccountResolver::receivable($companyId) : AccountResolver::payable($companyId);
        if (!$counter) {
            throw new \DomainException($kind === 'sales' ? 'Akun Piutang belum disiapkan di COA.' : 'Akun Hutang belum disiapkan di COA.');
        }

        return DB::transaction(function () use ($kind, $companyId, $partyId, $allocations, $cash, $counter, $date, $notes, $userId) {
            $model = $kind === 'sales' ? SalesInvoice::class : PurchaseInvoice::class;
            $partyCol = $kind === 'sales' ? 'customer_id' : 'supplier_id';

            // kunci baris faktur agar dua pembayaran bersamaan tidak melebihi sisa tagihan
            $invoices = $model::where('company_id', $companyId)->whereIn('id', array_keys($allocations))->lockForUpdate()->get()->keyBy('id');

            foreach ($allocations as $id => $amount) {
                $inv = $invoices->get($id);
                if (!$inv) {
                    throw new \DomainException('Faktur tidak ditemukan.');
                }
                if ((int) $inv->{$partyCol} !== $partyId) {
                    throw new \DomainException('Faktur ' . $inv->invoice_number . ' bukan milik ' . ($kind === 'sales' ? 'pelanggan' : 'pemasok') . ' yang dipilih.');
                }
                if ($inv->status === 'void') {
                    throw new \DomainException('Faktur ' . $inv->invoice_number . ' sudah dibatalkan dan tidak dapat dibayar.');
                }
                if ($amount > round((float) $inv->amount_due, 2) + 0.001) {
                    throw new \DomainException('Jumlah untuk faktur ' . $inv->invoice_number . ' melebihi sisa tagihan (Rp ' . number_format($inv->amount_due, 0, ',', '.') . ').');
                }
            }

            $total = round(array_sum($allocations), 2);
            $ref = self::nextRef($companyId, $kind === 'sales' ? 'RC-' : 'SP-', $date);
            $first = $invoices->first();
            $party = $kind === 'sales' ? optional($first->customer)->name : optional($first->supplier)->name;
            $numbers = $invoices->pluck('invoice_number')->implode(', ');

            $journal = Journal::create([
                'company_id' => $companyId,
                'ref_number' => $ref,
                'type' => 'auto_cash',
                'transaction_date' => $date,
                'description' => ($kind === 'sales' ? 'Penerimaan pembayaran ' : 'Pembayaran hutang ') . $numbers . ($party ? ' - ' . $party : ''),
                'status' => 'posted',
                'created_by' => $userId,
            ]);

            // Penjualan: Dr Kas (total), Cr Piutang per faktur. Pembelian: Dr Hutang per faktur, Cr Kas (total).
            JournalDetail::create([
                'journal_id' => $journal->id, 'account_id' => $cash->id,
                'debit' => $kind === 'sales' ? $total : 0, 'credit' => $kind === 'sales' ? 0 : $total,
                'description' => ($kind === 'sales' ? 'Diterima dari ' : 'Dibayar kepada ') . ($party ?: '-'),
            ]);
            foreach ($allocations as $id => $amount) {
                JournalDetail::create([
                    'journal_id' => $journal->id, 'account_id' => $counter->id,
                    'debit' => $kind === 'sales' ? 0 : $amount, 'credit' => $kind === 'sales' ? $amount : 0,
                    'description' => 'Pelunasan ' . $invoices[$id]->invoice_number,
                ]);
            }
            JournalGuard::assertBalanced($journal);

            $payments = [];
            foreach ($allocations as $id => $amount) {
                $inv = $invoices[$id];
                $payments[] = InvoicePayment::create([
                    'company_id' => $companyId,
                    'sales_invoice_id' => $kind === 'sales' ? $id : null,
                    'purchase_invoice_id' => $kind === 'purchase' ? $id : null,
                    'payment_number' => $ref,
                    'payment_date' => $date,
                    'account_id' => $cash->id,
                    'amount' => $amount,
                    'notes' => $notes,
                    'journal_id' => $journal->id,
                    'created_by' => $userId,
                ]);
                self::applyPaid($inv, $amount);
            }

            return $payments;
        });
    }

    /**
     * Batalkan satu penerimaan/pembayaran (seluruh alokasi pada jurnal yang sama):
     * jurnal balik dibuat, sisa tagihan faktur dipulihkan, riwayat tetap tersimpan sebagai "dibatalkan".
     */
    public static function voidPayment(InvoicePayment $payment, int $userId): void
    {
        if ($payment->voided_at) {
            throw new \DomainException('Pembayaran ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($payment, $userId) {
            $group = InvoicePayment::where('company_id', $payment->company_id)
                ->whereNull('voided_at')
                ->when($payment->journal_id, fn ($q) => $q->where('journal_id', $payment->journal_id), fn ($q) => $q->where('id', $payment->id))
                ->lockForUpdate()->get();

            $orig = $payment->journal_id ? Journal::with('details')->find($payment->journal_id) : null;
            if ($orig) {
                $rev = Journal::create([
                    'company_id' => $payment->company_id,
                    'ref_number' => self::nextRef((int) $payment->company_id, 'VOID-', now()->format('Y-m-d')),
                    'type' => $orig->type,
                    'transaction_date' => now()->format('Y-m-d'),
                    'description' => 'Pembatalan ' . $orig->ref_number,
                    'status' => 'posted',
                    'created_by' => $userId,
                ]);
                foreach ($orig->details as $d) {
                    JournalDetail::create([
                        'journal_id' => $rev->id, 'account_id' => $d->account_id,
                        'debit' => $d->credit, 'credit' => $d->debit,
                        'description' => 'Balik: ' . $d->description,
                    ]);
                }
                JournalGuard::assertBalanced($rev);
            }

            foreach ($group as $p) {
                $inv = $p->sales_invoice_id ? SalesInvoice::find($p->sales_invoice_id) : PurchaseInvoice::find($p->purchase_invoice_id);
                if ($inv && $inv->status !== 'void') {
                    self::applyPaid($inv, -1 * (float) $p->amount);
                }
                $p->voided_at = now();
                $p->voided_by = $userId;
                $p->save();
            }
        });
    }

    /** Batalkan faktur: jurnal balik, status void. Hanya jika belum ada pembayaran/retur. */
    public static function void($inv, string $kind, int $userId): void
    {
        if ($inv->status === 'void') {
            throw new \DomainException('Faktur sudah dibatalkan.');
        }
        if ((float) $inv->amount_paid > 0) {
            throw new \DomainException('Faktur yang sudah dibayar tidak dapat dibatalkan. Batalkan pembayarannya lebih dulu atau buat retur.');
        }
        if ($inv->returns()->exists()) {
            throw new \DomainException('Faktur yang sudah memiliki retur tidak dapat dibatalkan.');
        }

        DB::transaction(function () use ($inv, $kind, $userId) {
            $orig = $inv->journal()->with('details')->first();
            if ($orig) {
                $rev = Journal::create([
                    'company_id' => $inv->company_id,
                    'ref_number' => self::nextRef((int) $inv->company_id, 'VOID-', now()->format('Y-m-d')),
                    'type' => $orig->type,
                    'transaction_date' => now()->format('Y-m-d'),
                    'description' => 'Pembatalan faktur ' . $inv->invoice_number,
                    'status' => 'posted',
                    'created_by' => $userId,
                ]);
                foreach ($orig->details as $d) {
                    JournalDetail::create([
                        'journal_id' => $rev->id,
                        'account_id' => $d->account_id,
                        'project_job_id' => $d->project_job_id,
                        'debit' => $d->credit,
                        'credit' => $d->debit,
                        'description' => 'Balik: ' . $d->description,
                    ]);
                }
            }
            $inv->status = 'void';
            $inv->amount_due = 0;
            $inv->save();

            // Jumlah yang sudah ditagih pada pesanan asal dikembalikan
            if ($kind === 'sales' && $inv->sales_order_id && ($so = \App\Models\SalesOrder::find($inv->sales_order_id))) {
                OrderProgress::syncSalesOrder($so);
            }
            if ($kind === 'purchase' && $inv->po_id && ($po = \App\Models\PurchaseOrder::find($inv->po_id))) {
                OrderProgress::syncPurchaseOrder($po);
            }
        });
    }
}
