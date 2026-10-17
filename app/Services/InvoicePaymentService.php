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

    private static function refreshStatus($invoice): void
    {
        $paid = round((float) $invoice->amount_paid, 2);
        $total = round((float) $invoice->total, 2);
        $invoice->amount_due = max(0, round($total - $paid, 2));
        $invoice->status = $invoice->amount_due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        $invoice->save();
    }

    /** Catat pembayaran dari pelanggan. */
    public static function paySales(SalesInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId): InvoicePayment
    {
        return self::pay($inv, 'sales', $accountId, $amount, $date, $notes, $userId);
    }

    /** Catat pembayaran ke pemasok. */
    public static function payPurchase(PurchaseInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId): InvoicePayment
    {
        return self::pay($inv, 'purchase', $accountId, $amount, $date, $notes, $userId);
    }

    private static function pay($inv, string $kind, int $accountId, float $amount, string $date, ?string $notes, int $userId): InvoicePayment
    {
        $companyId = (int) $inv->company_id;

        if ($inv->status === 'void') {
            throw new \DomainException('Faktur yang sudah dibatalkan tidak dapat dibayar.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \DomainException('Jumlah pembayaran harus lebih dari nol.');
        }
        if ($amount > round((float) $inv->amount_due, 2) + 0.001) {
            throw new \DomainException('Jumlah pembayaran melebihi sisa tagihan (Rp ' . number_format($inv->amount_due, 0, ',', '.') . ').');
        }

        $cash = Account::where('company_id', $companyId)->where('is_cash_bank', true)->find($accountId);
        if (!$cash) {
            throw new \DomainException('Akun kas/bank tidak valid.');
        }

        $counter = $kind === 'sales' ? AccountResolver::receivable($companyId) : AccountResolver::payable($companyId);
        if (!$counter) {
            throw new \DomainException($kind === 'sales' ? 'Akun Piutang belum disiapkan di COA.' : 'Akun Hutang belum disiapkan di COA.');
        }

        return DB::transaction(function () use ($inv, $kind, $cash, $counter, $amount, $date, $notes, $userId, $companyId) {
            $prefix = $kind === 'sales' ? 'RC-' : 'SP-';
            $ref = self::nextRef($companyId, $prefix, $date);
            $party = $kind === 'sales' ? optional($inv->customer)->name : optional($inv->supplier)->name;

            $journal = Journal::create([
                'company_id' => $companyId,
                'ref_number' => $ref,
                'type' => 'auto_cash',
                'transaction_date' => $date,
                'description' => ($kind === 'sales' ? 'Pembayaran faktur ' : 'Pembayaran hutang faktur ') . $inv->invoice_number . ($party ? ' - ' . $party : ''),
                'status' => 'posted',
                'created_by' => $userId,
            ]);

            // Penjualan: Dr Kas, Cr Piutang. Pembelian: Dr Hutang, Cr Kas.
            $lines = $kind === 'sales'
                ? [[$cash->id, $amount, 0], [$counter->id, 0, $amount]]
                : [[$counter->id, $amount, 0], [$cash->id, 0, $amount]];
            foreach ($lines as [$accId, $debit, $credit]) {
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accId,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => 'Pembayaran ' . $inv->invoice_number,
                ]);
            }

            $payment = InvoicePayment::create([
                'company_id' => $companyId,
                'sales_invoice_id' => $kind === 'sales' ? $inv->id : null,
                'purchase_invoice_id' => $kind === 'purchase' ? $inv->id : null,
                'payment_number' => $ref,
                'payment_date' => $date,
                'account_id' => $cash->id,
                'amount' => $amount,
                'notes' => $notes,
                'journal_id' => $journal->id,
                'created_by' => $userId,
            ]);

            $inv->amount_paid = round((float) $inv->amount_paid + $amount, 2);
            self::refreshStatus($inv);

            return $payment;
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
