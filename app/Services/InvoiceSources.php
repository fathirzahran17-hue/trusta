<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

/**
 * Faktur boleh menagih beberapa pesanan (SO/PO) sekaligus. Tiap baris faktur membawa pesanan asalnya (row_orders[]);
 * form lama yang hanya mengirim satu sales_order_id / po_id tetap didukung (berlaku untuk semua baris).
 */
class InvoiceSources
{
    /**
     * @return array{0: array<int,int|null>, 1: \Illuminate\Support\Collection} [pesanan per baris, pesanan terkait (keyed id)]
     * @throws \DomainException
     */
    public static function resolve(string $kind, Request $r, int $companyId, int $partyId): array
    {
        $sales = $kind === 'sales';
        $legacy = (int) $r->input($sales ? 'sales_order_id' : 'po_id') ?: null;
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
            if ((int) ($sales ? $o->customer_id : $o->supplier_id) !== $partyId) {
                throw new \DomainException("Pesanan {$label} milik " . ($sales ? 'pelanggan' : 'pemasok') . ' lain — pilih ' . ($sales ? 'pelanggan' : 'pemasok') . ' yang sama untuk semua pesanan dalam satu faktur.');
            }

            $requested = [];
            foreach ($perRow as $i => $oid) {
                if ($oid === $id) {
                    $pid = (int) $r->products[$i];
                    $requested[$pid] = ($requested[$pid] ?? 0) + (float) ($r->quantities[$i] ?? 0);
                }
            }
            $progress = $sales ? OrderProgress::forSalesOrder($o) : OrderProgress::forPurchaseOrder($o);
            OrderProgress::assertWithinRemaining($progress, 'invoiced', $requested, "faktur ({$label})");
        }

        return [$perRow, $orders];
    }

    /** Hitung ulang status semua pesanan terkait sebuah faktur (dipakai setelah dibuat / dibatalkan). */
    public static function syncFor($invoice, string $kind): void
    {
        $inv = $invoice->fresh();
        $col = $kind === 'sales' ? 'sales_order_id' : 'po_id';
        $ids = $inv->items()->whereNotNull($col)->pluck($col)->push($inv->{$col})->filter()->unique();
        foreach ($ids as $id) {
            if ($kind === 'sales' && ($so = SalesOrder::find($id))) {
                OrderProgress::syncSalesOrder($so);
            }
            if ($kind === 'purchase' && ($po = PurchaseOrder::find($id))) {
                OrderProgress::syncPurchaseOrder($po);
            }
        }
    }
}
