<?php

namespace App\Services;

use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\ItemReceipt;
use App\Models\ItemReceiptDetail;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Product;
use App\Models\PurchaseCostAdjustment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;

/**
 * Harga Faktur Pembelian adalah harga final barang. Bila berbeda dari harga saat Penerimaan Barang:
 *  - bagian barang yang masih ada di stok: selisihnya masuk ke harga pokok,
 *  - bagian yang sudah terpakai/terjual: selisihnya dibebankan ke akun Selisih Harga Pembelian.
 * Dengan begitu saldo akun Persediaan tetap sama dengan nilai stok.
 */
class PurchaseCostAdjuster
{
    /** Cocokkan baris faktur dengan penerimaan yang sudah ada, lalu jurnal bagian yang sudah terpakai. */
    public static function applyInvoice(PurchaseInvoice $invoice, Journal $journal): void
    {
        $factor = 1 - ((float) $invoice->discount_percent / 100);
        $expensedByAccount = [];

        foreach ($invoice->items()->get() as $item) {
            if (! $item->product_id || (float) $item->quantity <= 0 || (! $item->item_receipt_id && ! $item->po_id)) {
                continue;
            }
            $product = Product::where('company_id', $invoice->company_id)->find($item->product_id);
            if (! $product || $product->type !== 'goods') {
                continue;
            }
            $unit = (float) $item->total * $factor / (float) $item->quantity;
            $left = (float) $item->quantity;
            foreach (self::openMovements($invoice->company_id, $item) as $movement) {
                if ($left <= 0.00005) {
                    break;
                }
                $qty = min($left, self::unmatched($movement));
                $adjustment = self::match($invoice, $item, $movement, $product, $qty, $unit, false);
                $left -= $qty;
                $account = AccountResolver::inventoryFor($product, (int) $invoice->company_id);
                if ($account) {
                    $expensedByAccount[$account->id] = ($expensedByAccount[$account->id] ?? 0) + (float) $adjustment->expensed;
                }
            }
        }

        self::journalExpensed($journal, $invoice, $expensedByAccount);
    }

    /** Penerimaan yang datang setelah fakturnya: barang masih utuh di stok, jadi seluruh selisih masuk ke harga pokok. */
    public static function applyReceipt(InventoryMovement $movement, int $purchaseOrderId): void
    {
        $items = PurchaseInvoiceItem::where('po_id', $purchaseOrderId)->where('product_id', $movement->product_id)
            ->whereNull('item_receipt_id')
            ->whereHas('invoice', fn ($q) => $q->where('company_id', $movement->company_id)->where('status', '!=', 'void'))
            ->with('invoice')->orderBy('id')->get();
        $product = Product::find($movement->product_id);
        $left = (float) $movement->quantity;
        foreach ($items as $item) {
            $qty = min($left, (float) $item->quantity - (float) PurchaseCostAdjustment::where('purchase_invoice_item_id', $item->id)->sum('quantity'));
            if ($qty <= 0.00005) {
                continue;
            }
            $unit = (float) $item->total * (1 - (float) $item->invoice->discount_percent / 100) / (float) $item->quantity;
            self::match($item->invoice, $item, $movement, $product, $qty, $unit, true);
            $left -= $qty;
        }
    }

    /** Faktur dibatalkan: harga pokok kembali ke nilai penerimaan. Jurnalnya dibalik oleh proses pembatalan faktur. */
    public static function revertInvoice(PurchaseInvoice $invoice): void
    {
        $method = optional(Company::find($invoice->company_id))->inventory_costing_method ?? 'average';
        foreach (PurchaseCostAdjustment::where('purchase_invoice_id', $invoice->id)->orderByDesc('id')->get() as $a) {
            $absorbed = (float) $a->absorbed;
            if (abs($absorbed) >= 0.005) {
                $product = Product::find($a->product_id);
                $stock = $product ? (float) $product->stock : 0;
                if ($stock > 0) {
                    $product->buy_price = round(max(0, ($stock * (float) $product->buy_price - $absorbed) / $stock), 2);
                    $product->save();
                }
                $movement = InventoryMovement::find($a->inventory_movement_id);
                if ($method === 'fifo' && $movement && (float) $movement->remaining_qty > 0) {
                    $movement->unit_cost = round(max(0, (float) $movement->unit_cost - $absorbed / (float) $movement->remaining_qty), 4);
                    $movement->save();
                }
            }
            $a->delete();
        }
    }

    /** Jumlah per akun persediaan dari selisih yang sudah dibebankan, untuk membuat ulang jurnal faktur. */
    public static function expensedByAccount(PurchaseInvoice $invoice): array
    {
        $out = [];
        foreach (PurchaseCostAdjustment::where('purchase_invoice_id', $invoice->id)->where('expensed', '!=', 0)->get() as $a) {
            $account = AccountResolver::inventoryFor(Product::find($a->product_id), (int) $invoice->company_id);
            if ($account) {
                $out[$account->id] = ($out[$account->id] ?? 0) + (float) $a->expensed;
            }
        }

        return $out;
    }

    /** Pindahkan selisih barang yang sudah terpakai dari Persediaan ke Selisih Harga Pembelian (jurnal tetap seimbang). */
    public static function journalExpensed(Journal $journal, PurchaseInvoice $invoice, array $expensedByAccount): void
    {
        foreach ($expensedByAccount as $inventoryAccountId => $amount) {
            $amount = round($amount, 2);
            if (abs($amount) < 0.01) {
                continue;
            }
            $variance = AccountResolver::purchaseVariance((int) $invoice->company_id);
            if (! $variance) {
                throw new \DomainException('Akun Harga Pokok belum ada di Bagan Akun.');
            }
            $label = 'Selisih harga ' . $invoice->invoice_number . ' (barang sudah terpakai)';
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $variance->id, 'debit' => max($amount, 0), 'credit' => max(-$amount, 0), 'description' => $label]);
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $inventoryAccountId, 'debit' => max(-$amount, 0), 'credit' => max($amount, 0), 'description' => $label]);
        }
    }

    /** Penerimaan aktif yang terkait baris faktur dan belum habis dicocokkan, urut dari yang paling lama. */
    private static function openMovements(int $companyId, PurchaseInvoiceItem $item)
    {
        if ($item->item_receipt_id) {
            $receiptIds = collect([$item->item_receipt_id]);
        } else {
            $receiptIds = ItemReceiptDetail::where('purchase_order_id', $item->po_id)->where('product_id', $item->product_id)->pluck('item_receipt_id')
                ->merge(ItemReceipt::where('company_id', $companyId)->where('purchase_order_id', $item->po_id)->pluck('id'))->unique();
        }
        $active = ItemReceipt::where('company_id', $companyId)->whereIn('id', $receiptIds)->where('status', 'received')->pluck('id');

        return InventoryMovement::where('company_id', $companyId)->where('reference_type', 'item_receipt')->whereIn('reference_id', $active)
            ->where('product_id', $item->product_id)->where('type', 'in')->orderBy('id')->get()
            ->filter(fn ($m) => self::unmatched($m) > 0.00005)->values();
    }

    private static function unmatched(InventoryMovement $movement): float
    {
        return (float) $movement->quantity - (float) PurchaseCostAdjustment::where('inventory_movement_id', $movement->id)->sum('quantity');
    }

    private static function match(PurchaseInvoice $invoice, PurchaseInvoiceItem $item, InventoryMovement $movement, Product $product, float $qty, float $unit, bool $allOnHand): PurchaseCostAdjustment
    {
        // Harga penerimaan asli: penyesuaian sebelumnya pada mutasi yang sama (FIFO) tidak boleh menggeser patokannya.
        $first = PurchaseCostAdjustment::where('inventory_movement_id', $movement->id)->orderBy('id')->first();
        $receiptCost = $first ? (float) $first->receipt_unit_cost : (float) $movement->unit_cost;
        $variance = round($qty * ($unit - $receiptCost), 2);
        $method = optional(Company::find($invoice->company_id))->inventory_costing_method ?? 'average';
        $stock = (float) $product->stock;

        $absorbed = 0.0;
        if (abs($variance) >= 0.01) {
            $onHand = $method === 'fifo' ? (float) $movement->remaining_qty : $stock;
            $share = $allOnHand ? 1.0 : min(1.0, max(0.0, $onHand) / $qty);
            $absorbed = round($variance * $share, 2);
            if ($stock > 0 && abs($absorbed) >= 0.005) {
                $old = (float) $product->buy_price;
                $product->buy_price = round(max(0, ($stock * $old + $absorbed) / $stock), 2);
                $product->save();
                if ($method !== 'fifo' && ! $allOnHand) {
                    // Harga pokok hanya 2 desimal: sisa pembulatannya ikut dibebankan agar nilai stok = saldo Persediaan.
                    $absorbed = round(((float) $product->buy_price - $old) * $stock, 2);
                }
            } elseif ($stock <= 0) {
                $absorbed = 0.0;
            }
            if ($method === 'fifo' && (float) $movement->remaining_qty > 0) {
                $movement->unit_cost = round((float) $movement->unit_cost + $absorbed / (float) $movement->remaining_qty, 4);
                $movement->save();
            }
        }

        return PurchaseCostAdjustment::create([
            'company_id' => $invoice->company_id, 'purchase_invoice_id' => $invoice->id, 'purchase_invoice_item_id' => $item->id,
            'inventory_movement_id' => $movement->id, 'product_id' => $product->id, 'quantity' => $qty,
            'receipt_unit_cost' => $receiptCost, 'invoice_unit_cost' => $unit,
            'variance' => $variance, 'absorbed' => $absorbed, 'expensed' => round($variance - $absorbed, 2),
        ]);
    }
}
