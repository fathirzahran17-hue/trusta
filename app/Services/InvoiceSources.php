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
            if ($o->status === 'cancelled') {
                throw new \DomainException("Pesanan {$label} sudah dibatalkan dan tidak bisa ditagih.");
            }
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

    /**
     * Pengiriman/Penerimaan asal per baris faktur (row_docs[]). Dokumen harus milik pihak yang sama dan sudah dikirim/diterima.
     * Untuk baris tanpa pesanan, jumlahnya dibatasi sisa yang belum ditagih dari dokumen itu (agar tidak tertagih dua kali).
     * @param array<int,int|null> $rowOrders
     * @return array<int,int|null>
     * @throws \DomainException
     */
    public static function resolveDocs(string $kind, Request $r, int $companyId, int $partyId, array $rowOrders): array
    {
        $sales = $kind === 'sales';
        $raw = (array) $r->input('row_docs', []);
        $perRow = [];
        foreach ((array) $r->input('products', []) as $i => $pid) {
            $perRow[$i] = ((int) ($raw[$i] ?? 0)) ?: null;
        }
        $ids = array_values(array_unique(array_filter($perRow)));
        if (!$ids) {
            return $perRow;
        }
        $model = $sales ? \App\Models\DeliveryOrder::class : \App\Models\ItemReceipt::class;
        $docs = $model::with('details')->where('company_id', $companyId)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $d = $docs[$id] ?? null;
            if (!$d) {
                throw new \DomainException($sales ? 'Pengiriman asal tidak ditemukan.' : 'Penerimaan asal tidak ditemukan.');
            }
            $no = $sales ? $d->delivery_number : $d->receipt_number;
            if (!in_array($d->status, $sales ? ['shipped', 'delivered'] : ['received'], true)) {
                throw new \DomainException("Dokumen {$no} belum " . ($sales ? 'dikirim' : 'diterima') . ' atau sudah dibatalkan.');
            }
            if ((int) ($sales ? $d->customer_id : $d->supplier_id) !== $partyId) {
                throw new \DomainException("Dokumen {$no} milik " . ($sales ? 'pelanggan' : 'pemasok') . ' lain.');
            }
            // baris tanpa pesanan: batasi ke sisa belum ditagih dari dokumen ini
            $asked = [];
            foreach ($perRow as $i => $did) {
                if ($did === $id && empty($rowOrders[$i])) {
                    $pid = (int) $r->products[$i];
                    $asked[$pid] = ($asked[$pid] ?? 0) + (float) ($r->quantities[$i] ?? 0);
                }
            }
            if (!$asked) continue;
            $billed = DocumentLinker::billedFromDocument($kind, $d->id);
            $shipped = $d->details->groupBy('product_id')->map(fn ($g) => (float) $g->sum('quantity'));
            foreach ($asked as $pid => $q) {
                $left = round(($shipped[$pid] ?? 0) - ($billed[$pid] ?? 0), 4);
                if ($q > $left + 0.0001) {
                    $name = optional(\App\Models\Product::find($pid))->name ?? "#{$pid}";
                    throw new \DomainException("Jumlah faktur untuk {$name} ({$q}) melebihi sisa yang belum ditagih dari {$no} (" . max(0, $left) . ').');
                }
            }
        }

        return $perRow;
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
