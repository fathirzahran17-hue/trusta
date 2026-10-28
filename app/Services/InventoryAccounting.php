<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InventoryMovement;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Mutasi persediaan di luar jual-beli (penyesuaian, stok opname, pemakaian internal) SELALU dijurnal,
 * agar saldo akun Persediaan di neraca sama dengan nilai stok:
 *   stok bertambah : Debit Persediaan  / Kredit akun lawan (mis. Selisih Persediaan)
 *   stok berkurang : Debit akun lawan (Beban/Selisih) / Kredit Persediaan
 * Nilai memakai harga pokok rata-rata barang (atau FIFO bila perusahaan memakai FIFO).
 */
class InventoryAccounting
{
    public const KINDS = [
        'adjustment' => ['label' => 'Penyesuaian Persediaan', 'prefix' => 'ADJ-'],
        'opname' => ['label' => 'Stok Opname', 'prefix' => 'OPN-'],
        'request' => ['label' => 'Pemakaian Barang', 'prefix' => 'PMK-'],
    ];

    /** Akun lawan yang boleh dipilih: beban/HPP/beban lain, pendapatan lain, dan ekuitas (koreksi saldo awal). */
    public static function contraAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)->where('is_active', true)
            ->whereIn('type', ['expense', 'cost_of_sales', 'other_expense', 'other_income', 'equity'])
            ->orderBy('code')->get()->reject(fn ($a) => $a->is_header)
            ->sortBy(fn ($a) => (preg_match('/selisih persediaan|penyesuaian persediaan|kerugian persediaan/i', $a->name) ? '0' : ($a->type === 'equity' ? '2' : '1')) . $a->code)
            ->values();
    }

    public static function defaultContra(int $companyId, string $kind): ?Account
    {
        $list = self::contraAccounts($companyId);
        $pattern = $kind === 'request' ? '/beban perlengkapan|pemakaian|perlengkapan|beban lain/i' : '/selisih persediaan|penyesuaian persediaan|beban lain|hpp|harga pokok/i';

        return $list->first(fn ($a) => preg_match($pattern, $a->name)) ?? $list->first();
    }

    /**
     * @param float $qtyDelta positif = stok bertambah, negatif = berkurang
     * @throws \DomainException
     */
    public static function adjust(int $companyId, Product $product, float $qtyDelta, string $date, int $contraAccountId, string $kind, ?string $note, int $userId): InventoryMovement
    {
        if ($product->company_id != $companyId || $product->type !== 'goods') {
            throw new \DomainException('Barang tidak valid (hanya barang bersetok yang bisa disesuaikan).');
        }
        if (abs($qtyDelta) < 0.0001) {
            throw new \DomainException('Selisih jumlah tidak boleh 0.');
        }
        if (! self::contraAccounts($companyId)->contains('id', $contraAccountId)) {
            throw new \DomainException('Pilih akun lawan (beban/selisih persediaan) yang valid.');
        }
        $inv = AccountResolver::inventoryFor($product, $companyId);
        if (! $inv) {
            throw new \DomainException('Akun Persediaan belum ada di Bagan Akun.');
        }
        $out = $qtyDelta < 0;
        $qty = abs($qtyDelta);
        if ($out && (float) $product->stock + 0.0001 < $qty) {
            throw new \DomainException("Stok {$product->name} hanya " . (float) $product->stock . ', tidak bisa dikurangi ' . $qty . '.');
        }
        $meta = self::KINDS[$kind] ?? self::KINDS['adjustment'];

        return DB::transaction(function () use ($companyId, $product, $qty, $out, $date, $contraAccountId, $kind, $note, $userId, $inv, $meta) {
            $cost = $out ? self::consumeCost($companyId, $product, $qty) : round($qty * (float) $product->buy_price, 2);
            $label = $meta['label'] . ' ' . $product->name . ' (' . ($out ? '-' : '+') . (float) $qty . ' ' . ($product->unit ?: '') . ')';

            $journal = null;
            if ($cost > 0.004) {
                $journal = Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => DocumentNumber::next(Journal::class, 'ref_number', $meta['prefix'], $companyId, $date),
                    'type' => 'general', 'transaction_date' => $date,
                    'description' => trim($label . ($note ? ' — ' . $note : '')),
                    'status' => 'posted', 'created_by' => $userId,
                ]);
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $out ? $contraAccountId : $inv->id, 'debit' => $cost, 'credit' => 0, 'description' => $label]);
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $out ? $inv->id : $contraAccountId, 'debit' => 0, 'credit' => $cost, 'description' => $label]);
                JournalGuard::assertBalanced($journal);
            }

            return InventoryMovement::create([
                'company_id' => $companyId, 'product_id' => $product->id, 'date' => $date,
                'type' => $out ? 'out' : 'in', 'quantity' => $qty,
                'unit_cost' => $qty > 0 ? round($cost / $qty, 2) : 0, 'remaining_qty' => $out ? 0 : $qty,
                'reference_type' => $kind, 'journal_id' => $journal?->id,
                'notes' => $note ?: $meta['label'], 'created_by' => $userId,
            ]);
        });
    }

    /** Batalkan penyesuaian: mutasi kebalikan + jurnal balik bertanggal hari ini. */
    public static function cancel(InventoryMovement $m, int $userId): void
    {
        if ($m->voided_at) {
            throw new \DomainException('Mutasi ini sudah dibatalkan.');
        }
        if (! array_key_exists($m->reference_type, self::KINDS)) {
            throw new \DomainException('Hanya penyesuaian, stok opname, dan pemakaian barang yang bisa dibatalkan di sini.');
        }
        $product = Product::find($m->product_id);
        if ($m->type === 'in' && $product && (float) $product->stock + 0.0001 < (float) $m->quantity) {
            throw new \DomainException("Barang {$product->name} dari penyesuaian ini sudah terpakai, jadi tidak bisa dibatalkan. Buat penyesuaian pengurang.");
        }
        DB::transaction(function () use ($m, $userId) {
            $today = now()->toDateString();
            if ($m->journal_id && ($j = Journal::with('details')->find($m->journal_id))) {
                InvoicePaymentService::reverseJournal($j, 'Pembatalan ' . $j->ref_number, $userId);
            }
            InventoryMovement::create([
                'company_id' => $m->company_id, 'product_id' => $m->product_id, 'date' => $today,
                'type' => $m->type === 'in' ? 'out' : 'in', 'quantity' => $m->quantity,
                'unit_cost' => $m->unit_cost, 'remaining_qty' => $m->type === 'in' ? 0 : $m->quantity,
                'reference_type' => 'void_' . $m->reference_type, 'reference_id' => $m->id,
                'notes' => 'Pembatalan: ' . $m->notes, 'created_by' => $userId,
            ]);
            if ($m->type === 'in') {
                $m->remaining_qty = 0;
            }
            $m->voided_at = now();
            $m->save();
        });
    }

    /** Nilai pokok barang yang keluar (FIFO bila dipakai perusahaan, selain itu harga rata-rata). */
    private static function consumeCost(int $companyId, Product $product, float $qty): float
    {
        $method = optional(\App\Models\Company::find($companyId))->inventory_costing_method ?? 'average';
        if ($method !== 'fifo') {
            return round($qty * (float) $product->buy_price, 2);
        }
        $left = $qty;
        $cost = 0.0;
        $batches = InventoryMovement::where('product_id', $product->id)->where('type', 'in')->where('remaining_qty', '>', 0)
            ->orderBy('date')->orderBy('id')->get();
        foreach ($batches as $b) {
            if ($left <= 0) break;
            $take = min($left, (float) $b->remaining_qty);
            $cost += $take * (float) $b->unit_cost;
            $b->remaining_qty = (float) $b->remaining_qty - $take;
            $b->save();
            $left -= $take;
        }
        if ($left > 0) {
            $cost += $left * (float) $product->buy_price;
        }

        return round($cost, 2);
    }
}
