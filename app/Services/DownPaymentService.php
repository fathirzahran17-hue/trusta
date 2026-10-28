<?php

namespace App\Services;

use App\Models\DownPayment;
use App\Models\DownPaymentApplication;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

/**
 * Memakai uang muka untuk melunasi faktur.
 * Penjualan : Debit Uang Muka Penjualan (kewajiban), Kredit Piutang.
 * Pembelian : Debit Hutang Usaha, Kredit Uang Muka Pembelian (aset).
 */
class DownPaymentService
{
    public static function apply(DownPayment $dp, $invoice, string $kind, float $amount, string $date, int $userId): DownPaymentApplication
    {
        $cid = (int) $invoice->company_id;
        if ((int) $dp->company_id !== $cid || $dp->type !== $kind) {
            throw new \DomainException('Uang muka ini tidak cocok untuk jenis faktur tersebut.');
        }
        if ($kind === 'sales' ? (int) $dp->customer_id !== (int) $invoice->customer_id : (int) $dp->supplier_id !== (int) $invoice->supplier_id) {
            throw new \DomainException('Uang muka ini milik ' . ($kind === 'sales' ? 'pelanggan' : 'pemasok') . ' yang berbeda dari faktur.');
        }
        if ($invoice->status === 'void') {
            throw new \DomainException('Faktur sudah dibatalkan.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \DomainException('Jumlah uang muka yang dipakai harus lebih dari 0.');
        }
        if ($amount > (float) $invoice->amount_due + 0.004) {
            throw new \DomainException('Jumlah melebihi sisa tagihan faktur (Rp ' . number_format($invoice->amount_due, 2, ',', '.') . ').');
        }

        return DB::transaction(function () use ($dp, $invoice, $kind, $amount, $date, $userId, $cid) {
            $dp = DownPayment::whereKey($dp->id)->lockForUpdate()->first();
            if ($amount > $dp->remaining + 0.004) {
                throw new \DomainException('Sisa uang muka hanya Rp ' . number_format($dp->remaining, 2, ',', '.') . '.');
            }

            if ($kind === 'sales') {
                $advance = AccountResolver::salesAdvance($cid);
                $other = AccountResolver::receivable($cid);
                $debit = $advance; $credit = $other;
                $label = 'Pemakaian uang muka penjualan ' . $dp->payment_number . ' untuk ' . $invoice->invoice_number;
            } else {
                $advance = AccountResolver::purchaseAdvance($cid);
                $other = AccountResolver::payable($cid);
                $debit = $other; $credit = $advance;
                $label = 'Pemakaian uang muka pembelian ' . $dp->payment_number . ' untuk ' . $invoice->invoice_number;
            }
            if (! $advance || ! $other) {
                throw new \DomainException('Akun Uang Muka / Piutang / Hutang belum lengkap di Chart of Accounts.');
            }

            $journal = Journal::create([
                'company_id' => $cid,
                'ref_number' => DocumentNumber::next(Journal::class, 'ref_number', 'DPA-', $cid, $date),
                'type' => 'general',
                'transaction_date' => $date,
                'description' => $label,
                'status' => 'posted',
                'created_by' => $userId,
            ]);
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $debit->id, 'debit' => $amount, 'credit' => 0, 'description' => $label]);
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $credit->id, 'debit' => 0, 'credit' => $amount, 'description' => $label]);
            JournalGuard::assertBalanced($journal);

            InvoicePaymentService::applyPaid($invoice, $amount);

            return DownPaymentApplication::create([
                'company_id' => $cid, 'down_payment_id' => $dp->id,
                'sales_invoice_id' => $kind === 'sales' ? $invoice->id : null,
                'purchase_invoice_id' => $kind === 'purchase' ? $invoice->id : null,
                'amount' => $amount, 'applied_date' => $date, 'journal_id' => $journal->id, 'created_by' => $userId,
            ]);
        });
    }

    public static function void(DownPaymentApplication $app, int $userId): void
    {
        if ($app->voided_at) {
            throw new \DomainException('Pemakaian uang muka ini sudah dibatalkan.');
        }
        DB::transaction(function () use ($app, $userId) {
            if ($app->journal_id && ($j = Journal::with('details')->find($app->journal_id))) {
                InvoicePaymentService::reverseJournal($j, 'Pembatalan ' . $j->ref_number, $userId);
            }
            $inv = $app->sales_invoice_id ? SalesInvoice::find($app->sales_invoice_id) : PurchaseInvoice::find($app->purchase_invoice_id);
            if ($inv && $inv->status !== 'void') {
                InvoicePaymentService::applyPaid($inv, -1 * (float) $app->amount);
            }
            $app->update(['voided_at' => now()]);
        });
    }
}
