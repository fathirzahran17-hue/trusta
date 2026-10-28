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
        $ref = DocumentNumber::next(Journal::class, 'ref_number', $prefix, $companyId, $date);
        // Nomor pembayaran (termasuk yang dibatalkan) tidak boleh dipakai ulang.
        if (in_array($prefix, ['RC-', 'SP-'], true)) {
            $pay = DocumentNumber::next(InvoicePayment::class, 'payment_number', $prefix, $companyId, $date);
            if (strcmp($pay, $ref) > 0) {
                $ref = $pay;
            }
        }

        return $ref;
    }

    /**
     * Jurnal balik (standar akuntansi): jurnal asli tetap ada, lalu dibuat jurnal pembalik bertanggal hari pembatalan
     * dengan debit/kredit ditukar, sehingga saldo akun kembali seperti sebelum transaksi.
     */
    public static function reverseJournal(Journal $orig, string $description, int $userId): Journal
    {
        $orig->loadMissing('details');
        $rev = Journal::create([
            'company_id' => $orig->company_id,
            'ref_number' => self::nextRef((int) $orig->company_id, 'VOID-', now()->format('Y-m-d')),
            'type' => $orig->type,
            'transaction_date' => now()->format('Y-m-d'),
            'description' => $description,
            'status' => 'posted',
            'created_by' => $userId,
        ]);
        foreach ($orig->details as $d) {
            JournalDetail::create([
                'journal_id' => $rev->id, 'account_id' => $d->account_id, 'project_job_id' => $d->project_job_id,
                'debit' => $d->credit, 'credit' => $d->debit,
                'description' => 'Balik: ' . $d->description,
            ]);
        }
        JournalGuard::assertBalanced($rev);

        return $rev;
    }

    /** Terapkan perubahan jumlah dibayar secara inkremental (aman terhadap retur yang sudah mengurangi sisa tagihan). */
    public static function applyPaid($invoice, float $delta): void
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
    public static function paySales(SalesInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId, array $deductions = []): InvoicePayment
    {
        return self::payMany('sales', (int) $inv->company_id, (int) $inv->customer_id, [$inv->id => $amount], $accountId, $date, $notes, $userId, $deductions)[0];
    }

    /** Catat pembayaran ke pemasok untuk satu faktur. */
    public static function payPurchase(PurchaseInvoice $inv, int $accountId, float $amount, string $date, ?string $notes, int $userId, array $deductions = []): InvoicePayment
    {
        return self::payMany('purchase', (int) $inv->company_id, (int) $inv->supplier_id, [$inv->id => $amount], $accountId, $date, $notes, $userId, $deductions)[0];
    }

    /**
     * Satu penerimaan/pembayaran yang dialokasikan ke beberapa faktur milik SATU pelanggan/pemasok.
     * Menghasilkan satu jurnal (Kas vs Piutang/Hutang per faktur) dan satu baris pembayaran per faktur.
     *
     * Pelunasan boleh di-split: sebagian jumlah tidak lewat kas/bank melainkan ke akun potongan
     * (mis. PPh yang dipotong pelanggan → Pajak Dibayar Dimuka, atau PPh yang ditahan dari pemasok → Hutang PPh).
     * Penjualan: Dr Kas (sisa), Dr akun potongan, Cr Piutang. Pembelian: Dr Hutang, Cr Kas (sisa), Cr akun potongan.
     *
     * @param array<int,float|string> $allocations [id faktur => jumlah yang dilunasi]
     * @param array<int,array{account_id:int|string,amount:float|string}> $deductions
     * @return InvoicePayment[]
     */
    public static function payMany(string $kind, int $companyId, int $partyId, array $allocations, int $accountId, string $date, ?string $notes, int $userId, array $deductions = []): array
    {
        $allocations = array_filter(array_map(fn ($v) => round((float) $v, 2), $allocations), fn ($v) => $v > 0);
        if (!$allocations) {
            throw new \DomainException('Pilih minimal satu faktur dan isi jumlah yang dibayar.');
        }

        $cash = Account::where('company_id', $companyId)->where('is_cash_bank', true)->find($accountId);
        if (!$cash) {
            throw new \DomainException('Akun kas/bank tidak valid.');
        }

        // potongan / split: gabungkan per akun, buang baris kosong
        $ded = [];
        foreach ($deductions as $row) {
            $amt = round((float) ($row['amount'] ?? 0), 2);
            $accId = (int) ($row['account_id'] ?? 0);
            if ($amt <= 0 || !$accId) {
                continue;
            }
            $ded[$accId] = round(($ded[$accId] ?? 0) + $amt, 2);
        }
        $dedAccounts = [];
        if ($ded) {
            $dedAccounts = Account::where('company_id', $companyId)->where('is_settlement_deduction', true)->where('is_active', true)
                ->where('normal_balance', $kind === 'sales' ? 'debit' : 'credit')->whereIn('id', array_keys($ded))->get()->reject(fn ($a) => $a->is_header)->keyBy('id');
            foreach (array_keys($ded) as $accId) {
                if (!$dedAccounts->has($accId)) {
                    throw new \DomainException('Akun potongan tidak valid (' . ($kind === 'sales' ? 'penerimaan butuh akun bersaldo normal debit, mis. Pajak Dibayar Dimuka' : 'pembayaran butuh akun bersaldo normal kredit, mis. Hutang PPh') . '). Centang "Bisa dipakai untuk potongan / split pelunasan" di Akun Perkiraan.');
                }
            }
        }
        $counter = $kind === 'sales' ? AccountResolver::receivable($companyId) : AccountResolver::payable($companyId);
        if (!$counter) {
            throw new \DomainException($kind === 'sales' ? 'Akun Piutang belum disiapkan di COA.' : 'Akun Hutang belum disiapkan di COA.');
        }

        return DB::transaction(function () use ($kind, $companyId, $partyId, $allocations, $cash, $counter, $date, $notes, $userId, $ded, $dedAccounts) {
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
                    throw new \DomainException('Jumlah untuk faktur ' . $inv->invoice_number . ' melebihi sisa tagihan (Rp ' . number_format($inv->amount_due, 2, ',', '.') . ').');
                }
            }

            $total = round(array_sum($allocations), 2);
            $dedTotal = round(array_sum($ded), 2);
            if ($dedTotal > $total + 0.001) {
                throw new \DomainException('Total potongan (Rp ' . number_format($dedTotal, 2, ',', '.') . ') melebihi jumlah yang dilunasi (Rp ' . number_format($total, 2, ',', '.') . ').');
            }
            $cashAmount = round($total - $dedTotal, 2);
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

            // Penjualan: Dr Kas (sisa) + Dr akun potongan, Cr Piutang per faktur.
            // Pembelian: Dr Hutang per faktur, Cr Kas (sisa) + Cr akun potongan.
            if ($cashAmount > 0.004) {
                JournalDetail::create([
                    'journal_id' => $journal->id, 'account_id' => $cash->id,
                    'debit' => $kind === 'sales' ? $cashAmount : 0, 'credit' => $kind === 'sales' ? 0 : $cashAmount,
                    'description' => ($kind === 'sales' ? 'Diterima dari ' : 'Dibayar kepada ') . ($party ?: '-'),
                ]);
            }
            foreach ($ded as $accId => $amt) {
                JournalDetail::create([
                    'journal_id' => $journal->id, 'account_id' => $accId,
                    'debit' => $kind === 'sales' ? $amt : 0, 'credit' => $kind === 'sales' ? 0 : $amt,
                    'description' => 'Potongan pelunasan: ' . $dedAccounts[$accId]->name . ($party ? ' - ' . $party : ''),
                ]);
            }
            foreach ($allocations as $id => $amount) {
                JournalDetail::create([
                    'journal_id' => $journal->id, 'account_id' => $counter->id,
                    'debit' => $kind === 'sales' ? 0 : $amount, 'credit' => $kind === 'sales' ? $amount : 0,
                    'description' => 'Pelunasan ' . $invoices[$id]->invoice_number,
                ]);
            }
            JournalGuard::assertBalanced($journal);

            $payments = [];
            $dedLeft = $dedTotal;
            $lastId = array_key_last($allocations);
            foreach ($allocations as $id => $amount) {
                $inv = $invoices[$id];
                // potongan dibagi proporsional ke tiap faktur; faktur terakhir menampung sisa pembulatan
                $share = $id === $lastId ? $dedLeft : round($dedTotal * $amount / $total, 2);
                $dedLeft = round($dedLeft - $share, 2);
                $payments[] = InvoicePayment::create([
                    'company_id' => $companyId,
                    'sales_invoice_id' => $kind === 'sales' ? $id : null,
                    'purchase_invoice_id' => $kind === 'purchase' ? $id : null,
                    'payment_number' => $ref,
                    'payment_date' => $date,
                    'account_id' => $cash->id,
                    'amount' => $amount,
                    'deduction_amount' => $share,
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
                self::reverseJournal($orig, 'Pembatalan ' . $orig->ref_number, $userId);
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
                self::reverseJournal($orig, 'Pembatalan faktur ' . $inv->invoice_number, $userId);
            }
            $inv->status = 'void';
            $inv->amount_due = 0;
            $inv->save();

            // Jumlah yang sudah ditagih pada pesanan asal dikembalikan
            InvoiceSources::syncFor($inv, $kind);
        });
    }
}
