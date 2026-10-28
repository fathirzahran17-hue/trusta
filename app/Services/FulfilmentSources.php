<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

/**
 * Pengiriman / Penerimaan Barang boleh memproses beberapa pesanan (SO/PO) sekaligus dari pihak yang sama.
 * Tiap baris membawa pesanan asalnya (row_orders[]); form lama yang hanya mengirim satu sales_order_id / purchase_order_id
 * tetap didukung (berlaku untuk semua baris). Baris tanpa pesanan = pengiriman/penerimaan bebas.
 */
class FulfilmentSources
{
    /**
     * @param 'sales'|'purchase' $kind
     * @return array{0: array<int,int|null>, 1: \Illuminate\Support\Collection} [pesanan per baris, pesanan terkait (keyed id)]
     * @throws \DomainException
     */
    public static function resolve(string $kind, Request $r, int $companyId, int $partyId, bool $checkRemaining = true): array
    {
        $sales = $kind === 'sales';
        $legacy = (int) $r->input($sales ? 'sales_order_id' : 'purchase_order_id') ?: null;
        $rowOrders = (array) $r->input('row_orders', []);

        $perRow = [];
        foreach ((array) $r->input('products', []) as $i => $pid) {
            $perRow[$i] = ((int) ($rowOrders[$i] ?? 0)) ?: $legacy;
        }

        $ids = array_values(array_unique(array_filter($perRow)));
        if (!$ids) {
            return [$perRow, collect()];
        }

        $model = $sales ? SalesOrder::class : PurchaseOrder::class;
        $orders = $model::where('company_id', $companyId)->whereIn('id', $ids)->get()->keyBy('id');
        if ($orders->count() !== count($ids)) {
            throw new \DomainException($sales ? 'Pesanan Penjualan tidak ditemukan.' : 'Pesanan Pembelian tidak ditemukan.');
        }

        foreach ($ids as $id) {
            $o = $orders[$id];
            $label = $sales ? $o->order_number : $o->po_number;
            if ($o->status === 'cancelled') {
                throw new \DomainException("Pesanan {$label} sudah dibatalkan.");
            }
            if ((int) ($sales ? $o->customer_id : $o->supplier_id) !== $partyId) {
                throw new \DomainException("Pesanan {$label} milik " . ($sales ? 'pelanggan' : 'pemasok') . ' lain — semua pesanan dalam satu ' . ($sales ? 'pengiriman' : 'penerimaan') . ' harus dari ' . ($sales ? 'pelanggan' : 'pemasok') . ' yang sama.');
            }
            if (!$checkRemaining) {
                continue;
            }
            $requested = [];
            foreach ($perRow as $i => $oid) {
                if ($oid === $id) {
                    $pid = (int) $r->products[$i];
                    $requested[$pid] = ($requested[$pid] ?? 0) + (float) ($r->quantities[$i] ?? 0);
                }
            }
            $progress = $sales ? OrderProgress::forSalesOrder($o) : OrderProgress::forPurchaseOrder($o);
            OrderProgress::assertWithinRemaining($progress, 'fulfilled', $requested, ($sales ? 'pengiriman' : 'penerimaan') . " ({$label})");
        }

        return [$perRow, $orders];
    }

    /** Pesanan di header dokumen: diisi bila semua baris dari satu pesanan yang sama (kompatibel dengan tampilan lama). */
    public static function headerOrder(array $perRow): ?int
    {
        $ids = array_values(array_unique(array_filter($perRow)));

        return count($ids) === 1 && count(array_filter($perRow)) === count($perRow) ? (int) $ids[0] : null;
    }
}
