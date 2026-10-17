<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\ItemReceipt;
use App\Models\Journal;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;

/** Pencarian global (Ctrl+K): dokumen, kontak, dan produk milik perusahaan pengguna. */
class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }
        $cid = auth()->user()->company_id;
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $out = [];

        $add = function (string $group, $rows, callable $map) use (&$out) {
            foreach ($rows as $r) {
                $out[] = ['group' => $group] + $map($r);
            }
        };

        $add('Faktur Penjualan', SalesInvoice::with('customer')->where('company_id', $cid)->where('invoice_number', 'like', $like)->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->invoice_number, 'sub' => optional($r->customer)->name, 'url' => route('client.sales-invoices.show', $r)]);
        $add('Faktur Pembelian', PurchaseInvoice::with('supplier')->where('company_id', $cid)
            ->where(fn ($w) => $w->where('invoice_number', 'like', $like)->orWhere('supplier_invoice_no', 'like', $like))->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->invoice_number, 'sub' => optional($r->supplier)->name, 'url' => route('client.purchase-invoices.show', $r)]);
        $add('Pesanan Penjualan', SalesOrder::with('customer')->where('company_id', $cid)
            ->where(fn ($w) => $w->where('order_number', 'like', $like)->orWhere('customer_po_number', 'like', $like))->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->order_number, 'sub' => optional($r->customer)->name, 'url' => route('client.sales.orders.show', $r)]);
        $add('Pesanan Pembelian', PurchaseOrder::with('supplier')->where('company_id', $cid)->where('po_number', 'like', $like)->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->po_number, 'sub' => optional($r->supplier)->name, 'url' => route('client.purchases.orders.show', $r)]);
        $add('Pengiriman', DeliveryOrder::with('customer')->where('company_id', $cid)->where('delivery_number', 'like', $like)->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->delivery_number, 'sub' => optional($r->customer)->name, 'url' => route('client.sales.deliveries.show', $r)]);
        $add('Penerimaan', ItemReceipt::with('supplier')->where('company_id', $cid)->where('receipt_number', 'like', $like)->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->receipt_number, 'sub' => optional($r->supplier)->name, 'url' => route('client.purchases.receipts.show', $r)]);
        $add('Pelanggan', Customer::where('company_id', $cid)->where('name', 'like', $like)->orderBy('name')->limit(5)->get(),
            fn ($r) => ['label' => $r->name, 'sub' => $r->phone, 'url' => route('client.customers.show', $r)]);
        $add('Pemasok', Supplier::where('company_id', $cid)->where('name', 'like', $like)->orderBy('name')->limit(5)->get(),
            fn ($r) => ['label' => $r->name, 'sub' => $r->phone, 'url' => route('client.suppliers.show', $r)]);
        $add('Produk', Product::where('company_id', $cid)->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like))->orderBy('name')->limit(5)->get(),
            fn ($r) => ['label' => $r->name, 'sub' => $r->sku, 'url' => route('client.inventory.show', $r)]);
        $add('Jurnal', Journal::where('company_id', $cid)->where(fn ($w) => $w->where('ref_number', 'like', $like)->orWhere('description', 'like', $like))->latest('id')->limit(5)->get(),
            fn ($r) => ['label' => $r->ref_number, 'sub' => \Illuminate\Support\Str::limit((string) $r->description, 60), 'url' => route('client.journals.show', $r)]);

        return response()->json(['results' => $out]);
    }
}
