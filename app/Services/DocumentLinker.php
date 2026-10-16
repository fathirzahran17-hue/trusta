<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\ItemReceipt;
use App\Models\Journal;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;

/**
 * Pusat logika penautan antar dokumen (SO -> Pengiriman -> Faktur, PO -> Penerimaan -> Faktur, Jurnal -> dokumen asal).
 * Semua method menyaring berdasarkan company_id agar tidak bocor antar perusahaan.
 */
class DocumentLinker
{
    /** Data awal form dari Pesanan Penjualan: pelanggan + baris barang. */
    public static function prefillFromSalesOrder($id, int $companyId): array
    {
        $order = $id ? SalesOrder::with('details')->where('company_id', $companyId)->find($id) : null;
        if (!$order) {
            return ['order' => null, 'rows' => []];
        }

        $rows = $order->details->map(fn ($d) => [
            'product_id' => $d->product_id,
            'description' => $d->description,
            'quantity' => (float) $d->quantity,
            'price' => (float) $d->unit_price,
            'discount' => (float) ($d->discount_percent ?? 0),
        ])->values()->all();

        return ['order' => $order, 'rows' => $rows];
    }

    /** Data awal form dari Pesanan Pembelian: pemasok + baris barang. */
    public static function prefillFromPurchaseOrder($id, int $companyId): array
    {
        $order = $id ? PurchaseOrder::with('items')->where('company_id', $companyId)->find($id) : null;
        if (!$order) {
            return ['order' => null, 'rows' => []];
        }

        $rows = $order->items->map(fn ($i) => [
            'product_id' => $i->product_id,
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'price' => (float) $i->unit_price,
            'discount' => (float) ($i->discount_percent ?? 0),
        ])->values()->all();

        return ['order' => $order, 'rows' => $rows];
    }

    /**
     * Dokumen sumber sebuah jurnal (faktur, pengiriman, penerimaan, dst.).
     * Mengembalikan ['label' => ..., 'number' => ..., 'url' => ...] atau null.
     */
    public static function sourceOfJournal(Journal $journal): ?array
    {
        $id = $journal->id;
        $cid = $journal->company_id;

        if ($m = SalesInvoice::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Faktur Penjualan', 'number' => $m->invoice_number, 'url' => route('client.sales-invoices.show', $m)];
        }
        if ($m = PurchaseInvoice::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Faktur Pembelian', 'number' => $m->invoice_number, 'url' => route('client.purchase-invoices.show', $m)];
        }
        if ($m = DeliveryOrder::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Pengiriman Barang', 'number' => $m->delivery_number, 'url' => route('client.sales.deliveries.show', $m)];
        }
        if ($m = ItemReceipt::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Penerimaan Barang', 'number' => $m->receipt_number, 'url' => route('client.purchases.receipts.show', $m)];
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Panel "Dokumen Terkait": setiap method mengembalikan daftar grup
    // [['label' => ..., 'items' => [['label','url','meta'], ...]], ...]
    // ------------------------------------------------------------------

    private static function item(string $label, ?string $url, ?string $meta = null): array
    {
        return ['label' => $label, 'url' => $url, 'meta' => $meta];
    }

    private static function rp($n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    private static function journalItems(?Journal $j): array
    {
        return $j ? [self::item($j->ref_number, route('client.journals.show', $j), optional($j->transaction_date)->format('d M Y'))] : [];
    }

    public static function relatedForSalesInvoice(SalesInvoice $inv): array
    {
        $groups = [];
        $so = $inv->salesOrder;
        if ($so) {
            $groups[] = ['label' => 'Pesanan Penjualan', 'items' => [self::item($so->order_number, route('client.sales.orders.show', $so), self::rp($so->total))]];
            $dos = $so->deliveryOrders()->orderBy('delivery_date')->get();
            $groups[] = ['label' => 'Pengiriman Barang', 'items' => $dos->map(fn ($d) => self::item($d->delivery_number, route('client.sales.deliveries.show', $d), optional($d->delivery_date)->format('d M Y')))->all()];
            $dps = $so->downPayments()->get();
            $groups[] = ['label' => 'Uang Muka', 'items' => $dps->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()];
        }
        $returns = $inv->returns()->get();
        $groups[] = ['label' => 'Retur Penjualan', 'items' => $returns->map(fn ($r) => self::item($r->return_number, null, optional($r->return_date ? \Carbon\Carbon::parse($r->return_date) : null)->format('d M Y')))->all()];
        $groups[] = ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($inv->journal)];
        $groups[] = ['label' => 'Pelanggan', 'items' => $inv->customer ? [self::item($inv->customer->name, route('client.customers.show', $inv->customer))] : []];

        return $groups;
    }

    public static function relatedForPurchaseInvoice(PurchaseInvoice $inv): array
    {
        $groups = [];
        $po = $inv->purchaseOrder;
        if ($po) {
            $groups[] = ['label' => 'Pesanan Pembelian', 'items' => [self::item($po->po_number, route('client.purchases.orders.show', $po), self::rp($po->total))]];
            $groups[] = ['label' => 'Penerimaan Barang', 'items' => $po->receipts()->orderBy('receipt_date')->get()->map(fn ($r) => self::item($r->receipt_number, route('client.purchases.receipts.show', $r), optional($r->receipt_date)->format('d M Y')))->all()];
            $groups[] = ['label' => 'Uang Muka', 'items' => $po->downPayments()->get()->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()];
        }
        $groups[] = ['label' => 'Retur Pembelian', 'items' => $inv->returns()->get()->map(fn ($r) => self::item($r->return_number, null, null))->all()];
        $groups[] = ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($inv->journal)];
        $groups[] = ['label' => 'Pemasok', 'items' => $inv->supplier ? [self::item($inv->supplier->name, route('client.suppliers.show', $inv->supplier))] : []];

        return $groups;
    }

    public static function relatedForSalesOrder(SalesOrder $so): array
    {
        return [
            ['label' => 'Pelanggan', 'items' => $so->customer ? [self::item($so->customer->name, route('client.customers.show', $so->customer))] : []],
            ['label' => 'Pengiriman Barang', 'items' => $so->deliveryOrders()->orderBy('delivery_date')->get()->map(fn ($d) => self::item($d->delivery_number, route('client.sales.deliveries.show', $d), optional($d->delivery_date)->format('d M Y')))->all()],
            ['label' => 'Faktur Penjualan', 'items' => $so->invoices()->orderBy('invoice_date')->get()->map(fn ($i) => self::item($i->invoice_number, route('client.sales-invoices.show', $i), self::rp($i->total)))->all()],
            ['label' => 'Uang Muka', 'items' => $so->downPayments()->get()->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()],
        ];
    }

    public static function relatedForPurchaseOrder(PurchaseOrder $po): array
    {
        return [
            ['label' => 'Pemasok', 'items' => $po->supplier ? [self::item($po->supplier->name, route('client.suppliers.show', $po->supplier))] : []],
            ['label' => 'Penerimaan Barang', 'items' => $po->receipts()->orderBy('receipt_date')->get()->map(fn ($r) => self::item($r->receipt_number, route('client.purchases.receipts.show', $r), optional($r->receipt_date)->format('d M Y')))->all()],
            ['label' => 'Faktur Pembelian', 'items' => $po->invoices()->orderBy('invoice_date')->get()->map(fn ($i) => self::item($i->invoice_number, route('client.purchase-invoices.show', $i), self::rp($i->total)))->all()],
            ['label' => 'Uang Muka', 'items' => $po->downPayments()->get()->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()],
        ];
    }

    public static function relatedForDelivery(DeliveryOrder $do): array
    {
        $so = $do->salesOrder;

        return [
            ['label' => 'Pesanan Penjualan', 'items' => $so ? [self::item($so->order_number, route('client.sales.orders.show', $so), self::rp($so->total))] : []],
            ['label' => 'Faktur Penjualan', 'items' => $so ? $so->invoices()->get()->map(fn ($i) => self::item($i->invoice_number, route('client.sales-invoices.show', $i), self::rp($i->total)))->all() : []],
            ['label' => 'Pelanggan', 'items' => $do->customer ? [self::item($do->customer->name, route('client.customers.show', $do->customer))] : []],
            ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($do->journal)],
        ];
    }

    public static function relatedForReceipt(ItemReceipt $r): array
    {
        $po = $r->purchaseOrder;

        return [
            ['label' => 'Pesanan Pembelian', 'items' => $po ? [self::item($po->po_number, route('client.purchases.orders.show', $po), self::rp($po->total))] : []],
            ['label' => 'Faktur Pembelian', 'items' => $po ? $po->invoices()->get()->map(fn ($i) => self::item($i->invoice_number, route('client.purchase-invoices.show', $i), self::rp($i->total)))->all() : []],
            ['label' => 'Pemasok', 'items' => $r->supplier ? [self::item($r->supplier->name, route('client.suppliers.show', $r->supplier))] : []],
            ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($r->journal)],
        ];
    }
}
