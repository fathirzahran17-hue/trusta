<?php

namespace App\Services;

use App\Models\DeliveryOrderDetail;
use App\Models\ItemReceiptDetail;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoiceItem;
use App\Models\SalesOrder;

/**
 * Pelacakan jumlah per baris pesanan: dipesan / dikirim(diterima) / ditagih,
 * sisa yang boleh diproses, dan status otomatis (SO: open -> closed; PO: sent -> received -> converted).
 */
class OrderProgress
{
    /** @return array<int, array{product_id:int,name:string,is_goods:bool,ordered:float,fulfilled:float,invoiced:float}> */
    public static function forSalesOrder(SalesOrder $so): array
    {
        $so->loadMissing('details.product');
        $rows = [];
        foreach ($so->details as $d) {
            $pid = (int) $d->product_id;
            $rows[$pid] ??= ['product_id' => $pid, 'name' => optional($d->product)->name ?? '-', 'is_goods' => optional($d->product)->type === 'goods', 'ordered' => 0.0, 'fulfilled' => 0.0, 'invoiced' => 0.0];
            $rows[$pid]['ordered'] += (float) $d->quantity;
        }

        $delivered = DeliveryOrderDetail::query()
            ->join('delivery_orders', 'delivery_orders.id', '=', 'delivery_order_details.delivery_order_id')
            ->where('delivery_orders.sales_order_id', $so->id)
            ->whereIn('delivery_orders.status', ['shipped', 'delivered'])
            ->selectRaw('delivery_order_details.product_id as pid, SUM(delivery_order_details.quantity) as qty')
            ->groupBy('delivery_order_details.product_id')->pluck('qty', 'pid');

        $invoiced = SalesInvoiceItem::query()
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_items.sales_invoice_id')
            ->where('sales_invoices.sales_order_id', $so->id)
            ->where('sales_invoices.status', '!=', 'void')
            ->selectRaw('sales_invoice_items.product_id as pid, SUM(sales_invoice_items.quantity) as qty')
            ->groupBy('sales_invoice_items.product_id')->pluck('qty', 'pid');

        foreach ($rows as $pid => &$r) {
            $r['fulfilled'] = (float) ($delivered[$pid] ?? 0);
            $r['invoiced'] = (float) ($invoiced[$pid] ?? 0);
        }

        return array_values($rows);
    }

    /** @return array same shape; 'fulfilled' = diterima */
    public static function forPurchaseOrder(PurchaseOrder $po): array
    {
        $po->loadMissing('items.product');
        $rows = [];
        foreach ($po->items as $i) {
            $pid = (int) $i->product_id;
            $rows[$pid] ??= ['product_id' => $pid, 'name' => optional($i->product)->name ?? '-', 'is_goods' => optional($i->product)->type === 'goods', 'ordered' => 0.0, 'fulfilled' => 0.0, 'invoiced' => 0.0];
            $rows[$pid]['ordered'] += (float) $i->quantity;
        }

        $received = ItemReceiptDetail::query()
            ->join('item_receipts', 'item_receipts.id', '=', 'item_receipt_details.item_receipt_id')
            ->where('item_receipts.purchase_order_id', $po->id)
            ->where('item_receipts.status', 'received')
            ->selectRaw('item_receipt_details.product_id as pid, SUM(item_receipt_details.quantity) as qty')
            ->groupBy('item_receipt_details.product_id')->pluck('qty', 'pid');

        $invoiced = PurchaseInvoiceItem::query()
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_items.purchase_invoice_id')
            ->where('purchase_invoices.po_id', $po->id)
            ->where('purchase_invoices.status', '!=', 'void')
            ->selectRaw('purchase_invoice_items.product_id as pid, SUM(purchase_invoice_items.quantity) as qty')
            ->groupBy('purchase_invoice_items.product_id')->pluck('qty', 'pid');

        foreach ($rows as $pid => &$r) {
            $r['fulfilled'] = (float) ($received[$pid] ?? 0);
            $r['invoiced'] = (float) ($invoiced[$pid] ?? 0);
        }

        return array_values($rows);
    }

    /** Gabungkan baris form menjadi product_id => total qty. */
    public static function requested(array $products, array $quantities): array
    {
        $out = [];
        foreach ($products as $i => $pid) {
            $out[(int) $pid] = ($out[(int) $pid] ?? 0) + (float) ($quantities[$i] ?? 0);
        }

        return $out;
    }

    /** Sisa yang masih boleh diproses per product_id. $what: 'fulfilled' (kirim/terima) atau 'invoiced'. */
    public static function remaining(array $progress, string $what): array
    {
        $out = [];
        foreach ($progress as $r) {
            if ($what === 'fulfilled' && !$r['is_goods']) {
                continue; // jasa tidak dikirim/diterima
            }
            $left = round($r['ordered'] - $r[$what], 4);
            if ($left > 0) {
                $out[$r['product_id']] = $left;
            }
        }

        return $out;
    }

    /**
     * Pastikan jumlah yang diminta tidak melebihi sisa pesanan.
     * @param array<int,float> $requested product_id => qty
     * @throws \DomainException
     */
    public static function assertWithinRemaining(array $progress, string $what, array $requested, string $label): void
    {
        $byId = [];
        foreach ($progress as $r) {
            $byId[$r['product_id']] = $r;
        }
        foreach ($requested as $pid => $qty) {
            if (!isset($byId[$pid])) {
                $name = optional(Product::find($pid))->name ?? "#{$pid}";
                throw new \DomainException("Produk {$name} tidak ada di pesanan asal.");
            }
            $left = round($byId[$pid]['ordered'] - $byId[$pid][$what], 4);
            if ($qty > $left + 0.0001) {
                throw new \DomainException("Jumlah {$label} untuk {$byId[$pid]['name']} (" . (float) $qty . ") melebihi sisa pesanan (" . max(0, (float) $left) . ').');
            }
        }
    }

    /** Hitung ulang status Pesanan Penjualan: open <-> closed. */
    public static function syncSalesOrder(SalesOrder $so): void
    {
        if (!in_array($so->status, ['open', 'closed'], true)) {
            return;
        }
        $done = true;
        foreach (self::forSalesOrder($so) as $r) {
            if ($r['invoiced'] + 0.0001 < $r['ordered']) {
                $done = false;
            }
            if ($r['is_goods'] && $r['fulfilled'] + 0.0001 < $r['ordered']) {
                $done = false;
            }
        }
        $new = $done ? 'closed' : 'open';
        if ($so->status !== $new) {
            $so->update(['status' => $new]);
        }
    }

    /** Hitung ulang status Pesanan Pembelian: sent -> received (semua barang diterima) -> converted (semua ditagih). */
    public static function syncPurchaseOrder(PurchaseOrder $po): void
    {
        if (!in_array($po->status, ['sent', 'received', 'converted'], true)) {
            return;
        }
        $allReceived = true;
        $allInvoiced = true;
        foreach (self::forPurchaseOrder($po) as $r) {
            if ($r['is_goods'] && $r['fulfilled'] + 0.0001 < $r['ordered']) {
                $allReceived = false;
            }
            if ($r['invoiced'] + 0.0001 < $r['ordered']) {
                $allInvoiced = false;
            }
        }
        $new = ($allReceived && $allInvoiced) ? 'converted' : ($allReceived ? 'received' : 'sent');
        if ($po->status !== $new) {
            $po->update(['status' => $new]);
        }
    }
}
