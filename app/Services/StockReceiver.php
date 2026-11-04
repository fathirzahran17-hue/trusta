<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ItemReceipt;
use App\Models\ItemReceiptDetail;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Warehouse;

/** Barang masuk stok: memperbarui harga rata-rata dan mencatat mutasinya. */
class StockReceiver
{
    public static function receive(ItemReceipt $receipt, Product $product, float $qty, float $price, ?int $warehouseId, int $userId): InventoryMovement
    {
        $oldStock = $product->stock;
        $newTotalStock = $oldStock + $qty;
        if ($newTotalStock > 0) {
            $product->buy_price = (($oldStock * $product->buy_price) + ($qty * $price)) / $newTotalStock;
            $product->save();
        }

        return InventoryMovement::create([
            'company_id' => $receipt->company_id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'date' => $receipt->receipt_date,
            'type' => 'in',
            'quantity' => $qty,
            'unit_cost' => $price,
            'remaining_qty' => $qty,
            'reference_type' => 'item_receipt',
            'reference_id' => $receipt->id,
            'notes' => 'Penerimaan Barang ' . $receipt->receipt_number,
            'created_by' => $userId,
        ]);
    }

    /**
     * Faktur Pembelian dengan pilihan "Barang sudah diterima": barang yang tidak berasal dari PO/penerimaan langsung masuk stok
     * lewat dokumen Penerimaan Barang yang dibuat otomatis, dengan harga bersih faktur.
     * @param array<int,array<string,mixed>> $items baris faktur
     * @return array<int,array<string,mixed>> baris faktur yang sudah tertaut ke penerimaannya
     */
    public static function receiveFromInvoice(PurchaseInvoice $invoice, array $items, float $discountPercent, ?int $warehouseId, int $userId): array
    {
        $products = [];
        foreach ($items as $i => $item) {
            $product = $item['product_id'] ? Product::where('company_id', $invoice->company_id)->find($item['product_id']) : null;
            if ($product && $product->type === 'goods' && empty($item['po_id']) && empty($item['item_receipt_id'])) {
                $products[$i] = $product;
            }
        }
        if (! $products) {
            return $items;
        }

        $warehouses = Warehouse::where('company_id', $invoice->company_id);
        $warehouse = ($warehouseId ? (clone $warehouses)->find($warehouseId) : null) ?? $warehouses->orderBy('id')->first();
        $date = $invoice->invoice_date;
        $receipt = ItemReceipt::create([
            'company_id' => $invoice->company_id,
            'supplier_id' => $invoice->supplier_id,
            'warehouse_id' => $warehouse?->id,
            'receipt_number' => DocumentNumber::next(ItemReceipt::class, 'receipt_number', 'IR-', (int) $invoice->company_id, is_string($date) ? $date : $date->toDateString()),
            'receipt_date' => $date,
            'status' => 'received',
            'notes' => 'Dibuat otomatis dari Faktur Pembelian ' . $invoice->invoice_number,
            'created_by' => $userId,
            'source_invoice_id' => $invoice->id,
        ]);
        $factor = 1 - $discountPercent / 100;
        foreach ($products as $i => $product) {
            $qty = (float) $items[$i]['quantity'];
            ItemReceiptDetail::create(['item_receipt_id' => $receipt->id, 'product_id' => $product->id, 'description' => $items[$i]['description'] ?? null, 'quantity' => $qty]);
            self::receive($receipt, $product, $qty, (float) $items[$i]['total'] * $factor / $qty, $warehouse?->id, $userId);
            $items[$i]['item_receipt_id'] = $receipt->id;
        }

        return $items;
    }
}
