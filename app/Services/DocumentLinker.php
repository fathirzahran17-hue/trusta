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
    public static function prefillFromSalesOrder($id, int $companyId, string $mode = 'invoiced'): array
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

        return ['order' => $order, 'rows' => self::limitToRemaining($rows, OrderProgress::remaining(OrderProgress::forSalesOrder($order), $mode))];
    }

    /** Data awal form dari Pesanan Pembelian: pemasok + baris barang. */
    public static function prefillFromPurchaseOrder($id, int $companyId, string $mode = 'invoiced'): array
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

        return ['order' => $order, 'rows' => self::limitToRemaining($rows, OrderProgress::remaining(OrderProgress::forPurchaseOrder($order), $mode))];
    }

    /**
     * Data awal Faktur Penjualan dari sebuah Pengiriman: pelanggan + barang yang dikirim, harga dari SO-nya,
     * dibatasi sisa yang belum ditagih pada SO tersebut.
     */
    public static function prefillFromDelivery($id, int $companyId): array
    {
        $do = $id ? DeliveryOrder::with(['details.product', 'salesOrder.details'])->where('company_id', $companyId)->find($id) : null;
        if (!$do) {
            return ['order' => null, 'rows' => [], 'party_id' => null, 'source' => null];
        }
        $so = $do->salesOrder;
        $soLines = $so ? $so->details->groupBy('product_id') : collect();

        $rows = [];
        foreach ($do->details as $d) {
            $line = optional($soLines->get($d->product_id))->first();
            $rows[] = [
                'product_id' => $d->product_id,
                'description' => $line->description ?? null,
                'quantity' => (float) $d->quantity,
                'price' => (float) ($line->unit_price ?? optional($d->product)->sell_price ?? 0),
                'discount' => (float) ($line->discount_percent ?? 0),
            ];
        }
        if ($so) {
            $rows = self::limitToRemaining($rows, OrderProgress::remaining(OrderProgress::forSalesOrder($so), 'invoiced'));
        }

        return ['order' => $so, 'rows' => $rows, 'party_id' => $do->customer_id, 'source' => $do->delivery_number];
    }

    /** Data awal Faktur Pembelian dari sebuah Penerimaan Barang. */
    public static function prefillFromReceipt($id, int $companyId): array
    {
        $r = $id ? ItemReceipt::with(['details.product', 'purchaseOrder.items'])->where('company_id', $companyId)->find($id) : null;
        if (!$r) {
            return ['order' => null, 'rows' => [], 'party_id' => null, 'source' => null];
        }
        $po = $r->purchaseOrder;
        $poLines = $po ? $po->items->groupBy('product_id') : collect();

        $rows = [];
        foreach ($r->details as $d) {
            $line = optional($poLines->get($d->product_id))->first();
            $rows[] = [
                'product_id' => $d->product_id,
                'description' => $line->description ?? null,
                'quantity' => (float) $d->quantity,
                'price' => (float) ($line->unit_price ?? optional($d->product)->buy_price ?? 0),
                'discount' => (float) ($line->discount_percent ?? 0),
            ];
        }
        if ($po) {
            $rows = self::limitToRemaining($rows, OrderProgress::remaining(OrderProgress::forPurchaseOrder($po), 'invoiced'));
        }

        return ['order' => $po, 'rows' => $rows, 'party_id' => $r->supplier_id, 'source' => $r->receipt_number];
    }

    /**
     * Data awal Faktur dari BEBERAPA dokumen sumber sekaligus (pesanan dan/atau pengiriman/penerimaan) milik pelanggan/pemasok yang sama.
     * Tiap baris membawa order_id pesanan asalnya; jumlah dibatasi sisa yang belum ditagih per pesanan.
     *
     * @param 'sales'|'purchase' $kind
     * @param int[] $orderIds   id SO/PO
     * @param int[] $docIds     id Pengiriman/Penerimaan
     */
    public static function prefillFromSources(string $kind, array $orderIds, array $docIds, int $companyId): array
    {
        $sales = $kind === 'sales';
        $empty = ['orders' => collect(), 'order' => null, 'rows' => [], 'party_id' => null, 'source' => null, 'sources' => [], 'ignored' => 0];

        $docs = collect();
        if ($docIds) {
            $docs = $sales
                ? DeliveryOrder::with(['details.product', 'salesOrder.details'])->where('company_id', $companyId)->whereIn('id', $docIds)->get()
                : ItemReceipt::with(['details.product', 'purchaseOrder.items'])->where('company_id', $companyId)->whereIn('id', $docIds)->get();
        }
        $orders = collect();
        if ($orderIds) {
            $orders = $sales
                ? SalesOrder::with('details')->where('company_id', $companyId)->whereIn('id', $orderIds)->get()
                : PurchaseOrder::with('items')->where('company_id', $companyId)->whereIn('id', $orderIds)->get();
        }
        if ($docs->isEmpty() && $orders->isEmpty()) {
            return $empty;
        }

        // semua sumber harus dari pihak yang sama: ikuti sumber pertama, sisanya diabaikan
        $partyCol = $sales ? 'customer_id' : 'supplier_id';
        $party = ($docs->first() ?? $orders->first())->{$partyCol};
        $ignored = $docs->filter(fn ($d) => $d->{$partyCol} != $party)->count() + $orders->filter(fn ($o) => $o->{$partyCol} != $party)->count();
        $docs = $docs->filter(fn ($d) => $d->{$partyCol} == $party)->values();
        $orders = $orders->filter(fn ($o) => $o->{$partyCol} == $party)->values();

        $remaining = [];   // order_id => product_id => sisa
        $remainingOf = function ($o) use (&$remaining, $sales) {
            return $remaining[$o->id] ??= OrderProgress::remaining($sales ? OrderProgress::forSalesOrder($o) : OrderProgress::forPurchaseOrder($o), 'invoiced');
        };

        $rows = [];
        $labels = [];
        $usedOrders = collect();
        $take = function (array $cand, $ord, ?string $from = null) use (&$rows, $remainingOf, &$remaining, $sales) {
            $left = $ord ? $remainingOf($ord) : null;
            foreach ($cand as $c) {
                $q = $c['quantity'];
                if ($ord) {
                    $avail = $left[$c['product_id']] ?? 0;
                    if ($avail <= 0) {
                        continue;
                    }
                    $q = min($q, $avail);
                    $left[$c['product_id']] = $avail - $q;
                }
                $rows[] = $c + [];
                $rows[array_key_last($rows)]['quantity'] = $q;
                $rows[array_key_last($rows)]['order_id'] = $ord?->id;
                $rows[array_key_last($rows)]['order_no'] = $ord ? ($sales ? $ord->order_number : $ord->po_number) : null;
            }
            if ($ord) {
                $remaining[$ord->id] = $left;
            }
        };

        // pengiriman/penerimaan dulu (jumlah nyata yang dikirim/diterima), lalu pesanan menarik sisanya
        foreach ($docs as $d) {
            $ord = $sales ? $d->salesOrder : $d->purchaseOrder;
            $lines = $ord ? ($sales ? $ord->details : $ord->items)->groupBy('product_id') : collect();
            $cand = [];
            foreach ($d->details as $x) {
                $line = optional($lines->get($x->product_id))->first();
                $cand[] = [
                    'product_id' => $x->product_id,
                    'description' => $line->description ?? null,
                    'quantity' => (float) $x->quantity,
                    'price' => (float) ($line->unit_price ?? ($sales ? optional($x->product)->sell_price : optional($x->product)->buy_price) ?? 0),
                    'discount' => (float) ($line->discount_percent ?? 0),
                ];
            }
            $take($cand, $ord);
            $labels[] = $sales ? $d->delivery_number : $d->receipt_number;
            if ($ord) {
                $usedOrders->push($ord);
            }
        }
        foreach ($orders as $o) {
            $cand = ($sales ? $o->details : $o->items)->map(fn ($x) => [
                'product_id' => $x->product_id,
                'description' => $x->description,
                'quantity' => (float) $x->quantity,
                'price' => (float) $x->unit_price,
                'discount' => (float) ($x->discount_percent ?? 0),
            ])->all();
            // barang yang sudah ditarik dari pengiriman/penerimaan untuk pesanan yang sama tidak ditarik dua kali (dibatasi sisa)
            $take($cand, $o);
            $labels[] = $sales ? $o->order_number : $o->po_number;
            $usedOrders->push($o);
        }

        $usedOrders = $usedOrders->unique('id')->values();

        return [
            'orders' => $usedOrders, 'order' => $usedOrders->first(), 'rows' => $rows, 'party_id' => $party,
            'source' => implode(', ', $labels), 'sources' => $labels, 'ignored' => $ignored,
        ];
    }

    /** Batasi jumlah tiap baris ke sisa pesanan (per produk); baris yang sudah habis dibuang. */
    private static function limitToRemaining(array $rows, array $remaining): array
    {
        $out = [];
        foreach ($rows as $r) {
            $left = $remaining[$r['product_id']] ?? 0;
            if ($left <= 0) {
                continue;
            }
            $r['quantity'] = min($r['quantity'], $left);
            $remaining[$r['product_id']] = $left - $r['quantity'];
            $out[] = $r;
        }

        return $out;
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

        // Penerimaan/pembayaran faktur (satu jurnal bisa melunasi beberapa faktur)
        $pays = \App\Models\InvoicePayment::where('company_id', $cid)->where('journal_id', $id)->get();
        if ($pays->isNotEmpty()) {
            $first = $pays->first();
            if ($first->sales_invoice_id) {
                $invs = SalesInvoice::whereIn('id', $pays->pluck('sales_invoice_id'))->get();
                return ['label' => 'Penerimaan Penjualan', 'number' => $invs->pluck('invoice_number')->implode(', '),
                    'url' => $invs->count() === 1 ? route('client.sales-invoices.show', $invs->first()) : route('client.sales.receipts')];
            }
            $invs = PurchaseInvoice::whereIn('id', $pays->pluck('purchase_invoice_id'))->get();
            return ['label' => 'Pembayaran Pembelian', 'number' => $invs->pluck('invoice_number')->implode(', '),
                'url' => $invs->count() === 1 ? route('client.purchase-invoices.show', $invs->first()) : route('client.purchases.payments')];
        }
        if ($m = \App\Models\SalesReturn::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Retur Penjualan', 'number' => $m->return_number, 'url' => route('client.sales.returns')];
        }
        if ($m = \App\Models\PurchaseReturn::where('company_id', $cid)->where('journal_id', $id)->first()) {
            return ['label' => 'Retur Pembelian', 'number' => $m->return_number, 'url' => route('client.purchases.returns')];
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
        $orders = $inv->sourceOrders();
        if ($orders->isNotEmpty()) {
            $groups[] = ['label' => 'Pesanan Penjualan', 'items' => $orders->map(fn ($so) => self::item($so->order_number, route('client.sales.orders.show', $so), self::rp($so->total)))->all()];
            $dos = \App\Models\DeliveryOrder::whereIn('sales_order_id', $orders->pluck('id'))->orderBy('delivery_date')->get();
            $groups[] = ['label' => 'Pengiriman Barang', 'items' => $dos->map(fn ($d) => self::item($d->delivery_number, route('client.sales.deliveries.show', $d), optional($d->delivery_date)->format('d M Y')))->all()];
            $dps = \App\Models\DownPayment::whereIn('sales_order_id', $orders->pluck('id'))->get();
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
        $orders = $inv->sourceOrders();
        if ($orders->isNotEmpty()) {
            $groups[] = ['label' => 'Pesanan Pembelian', 'items' => $orders->map(fn ($po) => self::item($po->po_number, route('client.purchases.orders.show', $po), self::rp($po->total)))->all()];
            $rcs = \App\Models\ItemReceipt::whereIn('purchase_order_id', $orders->pluck('id'))->orderBy('receipt_date')->get();
            $groups[] = ['label' => 'Penerimaan Barang', 'items' => $rcs->map(fn ($r) => self::item($r->receipt_number, route('client.purchases.receipts.show', $r), optional($r->receipt_date)->format('d M Y')))->all()];
            $dps = \App\Models\DownPayment::whereIn('purchase_order_id', $orders->pluck('id'))->get();
            $groups[] = ['label' => 'Uang Muka', 'items' => $dps->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()];
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
            ['label' => 'Faktur Penjualan', 'items' => $so->allInvoices()->orderBy('invoice_date')->get()->map(fn ($i) => self::item($i->invoice_number, route('client.sales-invoices.show', $i), self::rp($i->total)))->all()],
            ['label' => 'Uang Muka', 'items' => $so->downPayments()->get()->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()],
        ];
    }

    public static function relatedForPurchaseOrder(PurchaseOrder $po): array
    {
        return [
            ['label' => 'Pemasok', 'items' => $po->supplier ? [self::item($po->supplier->name, route('client.suppliers.show', $po->supplier))] : []],
            ['label' => 'Penerimaan Barang', 'items' => $po->receipts()->orderBy('receipt_date')->get()->map(fn ($r) => self::item($r->receipt_number, route('client.purchases.receipts.show', $r), optional($r->receipt_date)->format('d M Y')))->all()],
            ['label' => 'Faktur Pembelian', 'items' => $po->allInvoices()->orderBy('invoice_date')->get()->map(fn ($i) => self::item($i->invoice_number, route('client.purchase-invoices.show', $i), self::rp($i->total)))->all()],
            ['label' => 'Uang Muka', 'items' => $po->downPayments()->get()->map(fn ($d) => self::item($d->payment_number, null, self::rp($d->amount)))->all()],
        ];
    }

    public static function relatedForDelivery(DeliveryOrder $do): array
    {
        $so = $do->salesOrder;

        return [
            ['label' => 'Pesanan Penjualan', 'items' => $so ? [self::item($so->order_number, route('client.sales.orders.show', $so), self::rp($so->total))] : []],
            ['label' => 'Faktur Penjualan', 'items' => $so ? $so->allInvoices()->get()->map(fn ($i) => self::item($i->invoice_number, route('client.sales-invoices.show', $i), self::rp($i->total)))->all() : []],
            ['label' => 'Pelanggan', 'items' => $do->customer ? [self::item($do->customer->name, route('client.customers.show', $do->customer))] : []],
            ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($do->journal)],
        ];
    }

    public static function relatedForReceipt(ItemReceipt $r): array
    {
        $po = $r->purchaseOrder;

        return [
            ['label' => 'Pesanan Pembelian', 'items' => $po ? [self::item($po->po_number, route('client.purchases.orders.show', $po), self::rp($po->total))] : []],
            ['label' => 'Faktur Pembelian', 'items' => $po ? $po->allInvoices()->get()->map(fn ($i) => self::item($i->invoice_number, route('client.purchase-invoices.show', $i), self::rp($i->total)))->all() : []],
            ['label' => 'Pemasok', 'items' => $r->supplier ? [self::item($r->supplier->name, route('client.suppliers.show', $r->supplier))] : []],
            ['label' => 'Jurnal Akuntansi', 'items' => self::journalItems($r->journal)],
        ];
    }
}
