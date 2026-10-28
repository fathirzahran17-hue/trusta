<?php

namespace App\Services;

use App\Models\Account;
use App\Models\InventoryMovement;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * Produksi / perakitan sederhana:
 *   bahan keluar dari stok (nilai = harga pokok bahan), barang jadi masuk (nilai = bahan + biaya tambahan).
 * Jurnal:
 *   Debit  Persediaan Barang Jadi          (total biaya produksi)
 *   Kredit Persediaan Bahan (per akun)     (nilai bahan terpakai)
 *   Kredit akun biaya dibebankan / kas     (biaya tenaga kerja & overhead yang dimasukkan ke produk)
 */
class ProductionService
{
    /** Akun untuk biaya tambahan: biaya tenaga kerja/overhead dibebankan (HPP), beban, hutang, atau kas/bank. */
    public static function costAccounts(int $cid)
    {
        return Account::where('company_id', $cid)->where('is_active', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)
            ->filter(fn ($a) => in_array($a->type, ['cost_of_sales', 'expense', 'liability'], true) || $a->is_cash_bank)
            ->sortBy(fn ($a) => (preg_match('/dibebankan/i', $a->name) ? '0' : (in_array($a->type, ['cost_of_sales']) ? '1' : '2')) . $a->code)->values();
    }

    /**
     * @param array{date:string,product_id:int,quantity:float,bom_id:?int,notes:?string,components:array<int,array{product_id:int,quantity:float}>,costs:array<int,array{account_id:int,amount:float,description:?string}>} $d
     * @throws \DomainException
     */
    public static function run(int $cid, array $d, int $userId): ProductionOrder
    {
        $product = Product::where('company_id', $cid)->find($d['product_id'] ?? 0);
        if (! $product || $product->type !== 'goods') {
            throw new \DomainException('Pilih barang jadi (barang bersetok) yang akan diproduksi.');
        }
        $qty = round((float) ($d['quantity'] ?? 0), 2);
        if ($qty <= 0) {
            throw new \DomainException('Jumlah produksi harus lebih dari 0.');
        }

        // gabungkan bahan yang sama
        $need = [];
        foreach ($d['components'] ?? [] as $c) {
            $pid = (int) ($c['product_id'] ?? 0);
            $q = round((float) ($c['quantity'] ?? 0), 4);
            if (! $pid || $q <= 0) continue;
            $need[$pid] = ($need[$pid] ?? 0) + $q;
        }
        if (! $need) {
            throw new \DomainException('Isi minimal satu bahan yang dipakai.');
        }
        if (isset($need[$product->id])) {
            throw new \DomainException('Barang jadi tidak boleh menjadi bahannya sendiri.');
        }
        $components = Product::where('company_id', $cid)->whereIn('id', array_keys($need))->get()->keyBy('id');
        foreach ($need as $pid => $q) {
            $p = $components[$pid] ?? null;
            if (! $p || $p->type !== 'goods') {
                throw new \DomainException('Bahan harus barang bersetok.');
            }
            if ((float) $p->stock + 0.0001 < $q) {
                throw new \DomainException("Stok {$p->name} tidak cukup (tersedia " . (float) $p->stock . ", dibutuhkan {$q}).");
            }
        }
        $allowedCost = self::costAccounts($cid)->pluck('id')->all();
        $costs = [];
        foreach ($d['costs'] ?? [] as $c) {
            $amt = round((float) ($c['amount'] ?? 0), 2);
            if ($amt <= 0) continue;
            if (! in_array((int) ($c['account_id'] ?? 0), $allowedCost, true)) {
                throw new \DomainException('Akun biaya tambahan tidak valid.');
            }
            $costs[] = ['account_id' => (int) $c['account_id'], 'amount' => $amt, 'description' => $c['description'] ?? null];
        }
        $finishedAcc = AccountResolver::inventoryFor($product, $cid);
        if (! $finishedAcc) {
            throw new \DomainException('Akun Persediaan belum ada di Bagan Akun.');
        }

        return DB::transaction(function () use ($cid, $d, $userId, $product, $qty, $need, $components, $costs, $finishedAcc) {
            $date = $d['date'];
            $order = ProductionOrder::create([
                'company_id' => $cid, 'number' => DocumentNumber::next(ProductionOrder::class, 'number', 'PRD-', $cid, $date),
                'date' => $date, 'product_id' => $product->id, 'bom_id' => $d['bom_id'] ?? null, 'quantity' => $qty,
                'notes' => $d['notes'] ?? null, 'created_by' => $userId,
            ]);

            $material = 0.0;
            $byInvAcc = [];
            foreach ($need as $pid => $q) {
                $p = $components[$pid];
                $cost = InventoryAccounting::consumeCost($cid, $p, $q);
                $material += $cost;
                $acc = AccountResolver::inventoryFor($p, $cid);
                if (! $acc) throw new \DomainException('Akun Persediaan bahan belum ada.');
                $byInvAcc[$acc->id] = ($byInvAcc[$acc->id] ?? 0) + $cost;
                $order->items()->create(['product_id' => $pid, 'quantity' => $q, 'unit_cost' => $q > 0 ? $cost / $q : 0, 'total' => $cost]);
                InventoryMovement::create([
                    'company_id' => $cid, 'product_id' => $pid, 'date' => $date, 'type' => 'out', 'quantity' => $q,
                    'unit_cost' => $q > 0 ? round($cost / $q, 2) : 0, 'remaining_qty' => 0,
                    'reference_type' => 'production', 'reference_id' => $order->id,
                    'notes' => 'Bahan produksi ' . $order->number, 'created_by' => $userId,
                ]);
            }
            $extra = 0.0;
            foreach ($costs as $c) {
                $order->costs()->create($c);
                $extra += $c['amount'];
            }
            $material = round($material, 2);
            $total = round($material + $extra, 2);
            $unit = $total / $qty;

            // barang jadi masuk + harga pokok rata-rata diperbarui
            $oldStock = (float) $product->stock;
            $newStock = $oldStock + $qty;
            if ($newStock > 0) {
                $product->buy_price = round((($oldStock > 0 ? $oldStock : 0) * (float) $product->buy_price + $total) / ($oldStock > 0 ? $newStock : $qty), 2);
                $product->save();
            }
            InventoryMovement::create([
                'company_id' => $cid, 'product_id' => $product->id, 'date' => $date, 'type' => 'in', 'quantity' => $qty,
                'unit_cost' => round($unit, 2), 'remaining_qty' => $qty,
                'reference_type' => 'production', 'reference_id' => $order->id,
                'notes' => 'Hasil produksi ' . $order->number, 'created_by' => $userId,
            ]);

            $journal = null;
            if ($total > 0.004) {
                $journal = Journal::create([
                    'company_id' => $cid, 'ref_number' => $order->number, 'type' => 'general', 'transaction_date' => $date,
                    'description' => 'Produksi ' . $product->name . ' (' . (float) $qty . ' ' . ($product->unit ?: '') . ') ' . $order->number,
                    'status' => 'posted', 'created_by' => $userId,
                ]);
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $finishedAcc->id, 'debit' => $total, 'credit' => 0, 'description' => 'Barang jadi ' . $product->name]);
                foreach ($byInvAcc as $accId => $amt) {
                    if (round($amt, 2) > 0) {
                        JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $accId, 'debit' => 0, 'credit' => round($amt, 2), 'description' => 'Bahan terpakai ' . $order->number]);
                    }
                }
                foreach ($costs as $c) {
                    JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $c['account_id'], 'debit' => 0, 'credit' => $c['amount'], 'description' => $c['description'] ?: 'Biaya produksi ' . $order->number]);
                }
                // pembulatan per akun bahan
                $diff = round($total - (float) JournalDetail::where('journal_id', $journal->id)->sum('credit'), 2);
                if (abs($diff) >= 0.01) {
                    $last = JournalDetail::where('journal_id', $journal->id)->where('credit', '>', 0)->orderBy('id')->first();
                    $last->credit = round((float) $last->credit + $diff, 2);
                    $last->save();
                }
                JournalGuard::assertBalanced($journal);
            }

            $order->update(['material_cost' => $material, 'extra_cost' => round($extra, 2), 'total_cost' => $total, 'unit_cost' => $unit, 'journal_id' => $journal?->id]);

            return $order;
        });
    }

    /** Batalkan produksi: barang jadi ditarik, bahan dikembalikan, jurnal balik bertanggal hari ini. */
    public static function cancel(ProductionOrder $order, int $userId): void
    {
        if ($order->voided_at) {
            throw new \DomainException('Produksi ini sudah dibatalkan.');
        }
        $product = Product::find($order->product_id);
        if ($product && (float) $product->stock + 0.0001 < (float) $order->quantity) {
            throw new \DomainException("Barang jadi {$product->name} dari produksi ini sudah terjual/terpakai, jadi produksi tidak bisa dibatalkan.");
        }
        DB::transaction(function () use ($order, $userId, $product) {
            $today = now()->toDateString();
            if ($order->journal_id && ($j = Journal::with('details')->find($order->journal_id))) {
                InvoicePaymentService::reverseJournal($j, 'Pembatalan ' . $j->ref_number, $userId);
            }
            if ($product) {
                $stockAfter = (float) $product->stock - (float) $order->quantity;
                if ($stockAfter > 0) {
                    $product->buy_price = round(max(0, ((float) $product->stock * (float) $product->buy_price - (float) $order->total_cost) / $stockAfter), 2);
                    $product->save();
                }
                InventoryMovement::create(['company_id' => $order->company_id, 'product_id' => $product->id, 'date' => $today, 'type' => 'out',
                    'quantity' => $order->quantity, 'unit_cost' => round((float) $order->unit_cost, 2), 'remaining_qty' => 0,
                    'reference_type' => 'void_production', 'reference_id' => $order->id, 'notes' => 'Pembatalan hasil produksi ' . $order->number, 'created_by' => $userId]);
                InventoryMovement::where('reference_type', 'production')->where('reference_id', $order->id)->where('type', 'in')->update(['remaining_qty' => 0]);
            }
            foreach ($order->items as $it) {
                InventoryMovement::create(['company_id' => $order->company_id, 'product_id' => $it->product_id, 'date' => $today, 'type' => 'in',
                    'quantity' => $it->quantity, 'unit_cost' => round((float) $it->unit_cost, 2), 'remaining_qty' => $it->quantity,
                    'reference_type' => 'void_production', 'reference_id' => $order->id, 'notes' => 'Bahan kembali dari pembatalan ' . $order->number, 'created_by' => $userId]);
            }
            $order->voided_at = now();
            $order->save();
        });
    }
}
