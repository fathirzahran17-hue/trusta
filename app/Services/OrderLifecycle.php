<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DownPayment;
use App\Models\InventoryMovement;
use App\Models\ItemReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

/**
 * Aturan hapus / ubah / batalkan dokumen:
 *  - Pesanan (SO/PO) boleh dihapus atau diubah selama belum punya turunan (pengiriman/penerimaan, faktur, uang muka).
 *  - Pesanan yang sudah punya turunan hanya bisa DIBATALKAN (sisanya tidak bisa diproses lagi, dokumen yang sudah jadi tetap utuh).
 *  - Pengiriman / Penerimaan Barang dibatalkan dengan stok dikembalikan otomatis.
 */
class OrderLifecycle
{
    /** @return string[] penghalang, mis. ["2 faktur"]; kosong = bebas hapus/ubah */
    public static function salesOrderBlockers(SalesOrder $so): array
    {
        $b = [];
        if ($n = $so->allDeliveries()->where('status', '!=', 'cancelled')->count()) {
            $b[] = "$n pengiriman";
        }
        if ($n = $so->allInvoices()->where('status', '!=', 'void')->count()) {
            $b[] = "$n faktur";
        }
        if ($n = DownPayment::where('sales_order_id', $so->id)->count()) {
            $b[] = "$n uang muka";
        }

        return $b;
    }

    /** @return string[] */
    public static function purchaseOrderBlockers(PurchaseOrder $po): array
    {
        $b = [];
        if ($n = $po->allReceipts()->where('status', '!=', 'cancelled')->count()) {
            $b[] = "$n penerimaan barang";
        }
        if ($n = $po->allInvoices()->where('status', '!=', 'void')->count()) {
            $b[] = "$n faktur";
        }
        if ($n = DownPayment::where('purchase_order_id', $po->id)->count()) {
            $b[] = "$n uang muka";
        }

        return $b;
    }

    public static function canCancelSales(SalesOrder $so): bool
    {
        return $so->status === 'open';
    }

    public static function canCancelPurchase(PurchaseOrder $po): bool
    {
        return in_array($po->status, ['draft', 'sent', 'received'], true);
    }

    /** Batalkan Pengiriman: stok keluar dikembalikan. @throws \DomainException */
    public static function cancelDelivery(DeliveryOrder $do): void
    {
        if ($do->status === 'cancelled') {
            throw new \DomainException('Pengiriman ini sudah dibatalkan.');
        }
        DB::transaction(function () use ($do) {
            InventoryMovement::where('company_id', $do->company_id)->where('reference_type', 'delivery_order')->where('reference_id', $do->id)->delete();
            $do->update(['status' => 'cancelled']);
        });
        foreach ($do->sourceOrders() as $so) {
            OrderProgress::syncSalesOrder($so);
        }
    }

    /** Batalkan Penerimaan Barang: stok masuk ditarik kembali bila belum terpakai. @throws \DomainException */
    public static function cancelReceipt(ItemReceipt $r): void
    {
        if ($r->status === 'cancelled') {
            throw new \DomainException('Penerimaan ini sudah dibatalkan.');
        }
        DB::transaction(function () use ($r) {
            $moves = InventoryMovement::where('company_id', $r->company_id)->where('reference_type', 'item_receipt')->where('reference_id', $r->id)->get();
            foreach ($moves as $m) {
                $p = \App\Models\Product::find($m->product_id);
                if (!$p) {
                    continue;
                }
                if ($p->stock + 0.0001 < (float) $m->quantity || (float) $m->remaining_qty + 0.0001 < (float) $m->quantity) {
                    throw new \DomainException("Barang {$p->name} dari penerimaan ini sudah terpakai (terjual/dikirim), sehingga penerimaan tidak bisa dibatalkan. Buat retur pembelian atau penyesuaian persediaan.");
                }
                // kembalikan harga rata-rata sebelum penerimaan ini
                $stockAfterRemoval = (float) $p->stock - (float) $m->quantity;
                if ($stockAfterRemoval > 0 && (float) $m->unit_cost > 0) {
                    $p->buy_price = (($p->stock * $p->buy_price) - ($m->quantity * $m->unit_cost)) / $stockAfterRemoval;
                    $p->save();
                }
                $m->delete();
            }
            $r->update(['status' => 'cancelled']);
        });
        foreach ($r->sourceOrders() as $po) {
            OrderProgress::syncPurchaseOrder($po);
        }
    }
}
