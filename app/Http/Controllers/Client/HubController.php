<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\ItemReceipt;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;

/** Halaman pusat Penjualan / Pembelian: alur kerja yang bisa diklik, ringkasan piutang/hutang, dan pintasan. */
class HubController extends Controller
{
    public function sales()    { return $this->hub('sales'); }
    public function purchases() { return $this->hub('purchase'); }

    private function hub(string $kind)
    {
        $cid = (int) auth()->user()->company_id;
        $sales = $kind === 'sales';
        $inv = $sales ? SalesInvoice::class : PurchaseInvoice::class;
        $party = $sales ? 'customer' : 'supplier';
        $partyCol = $sales ? 'customer_id' : 'supplier_id';

        $open = $inv::with($party)->where('company_id', $cid)->whereIn('status', ['unpaid', 'partial'])->get();
        $overdue = $open->filter(fn ($i) => $i->due_date && $i->due_date->lt(today()));
        $monthTotal = (float) $inv::where('company_id', $cid)->where('status', '!=', 'void')
            ->whereYear('invoice_date', now()->year)->whereMonth('invoice_date', now()->month)->sum('total');

        $stats = [
            'open' => round($open->sum('amount_due'), 2),
            'openCount' => $open->count(),
            'overdue' => round($overdue->sum('amount_due'), 2),
            'overdueCount' => $overdue->count(),
            'month' => round($monthTotal, 2),
        ];

        // alur kerja: [judul, jumlah yang masih berjalan, label jumlah, rute daftar, rute buat (atau null)]
        if ($sales) {
            $flow = [
                ['Pesanan Penjualan', SalesOrder::where('company_id', $cid)->whereIn('status', ['draft', 'open'])->count(), 'belum selesai', 'client.sales.orders', 'client.sales.orders.create', 'Buat Pesanan'],
                ['Pengiriman Pesanan', DeliveryOrder::where('company_id', $cid)->whereIn('status', ['draft', 'shipped'])->count(), 'belum terkirim', 'client.sales.deliveries', 'client.sales.deliveries.create', 'Buat Pengiriman'],
                ['Faktur Penjualan', $stats['openCount'], 'belum lunas', 'client.sales-invoices.index', 'client.sales-invoices.create', 'Buat Faktur'],
                ['Penerimaan Penjualan', $stats['overdueCount'], 'jatuh tempo', 'client.sales.settlement', 'client.sales.receipts', 'Terima Pembayaran'],
            ];
            $shortcuts = [
                ['Pelanggan', Customer::where('company_id', $cid)->count() . ' pelanggan', 'client.customers.index', 'client.customers.create'],
                ['Barang Dijual', Product::where('company_id', $cid)->where('type', 'goods')->count() . ' barang', 'client.inventory.index', 'client.inventory.create'],
                ['Jasa Dijual', Product::where('company_id', $cid)->where('type', 'service')->count() . ' jasa', 'client.inventory.services', 'client.inventory.services.create'],
                ['Produk & Jasa (semua)', 'Barang + jasa', 'client.inventory.all', null],
                ['Uang Muka Penjualan', 'Deposit pelanggan', 'client.sales.down_payments', 'client.sales.down_payments.create'],
                ['Retur Penjualan', 'Barang kembali', 'client.sales.returns', 'client.sales.returns.create'],
                ['Status Pelunasan', 'Lunas / belum', 'client.sales.settlement', null],
                ['Laporan Penjualan', 'Ringkasan & analisis', 'client.reports.sales-report', null],
            ];
        } else {
            $flow = [
                ['Pesanan Pembelian', PurchaseOrder::where('company_id', $cid)->whereIn('status', ['draft', 'sent'])->count(), 'belum diterima', 'client.purchases.orders', 'client.purchases.orders.create', 'Buat Pesanan'],
                ['Penerimaan Barang', ItemReceipt::where('company_id', $cid)->where('status', 'draft')->count(), 'draft', 'client.purchases.receipts', 'client.purchases.receipts.create', 'Catat Penerimaan'],
                ['Faktur Pembelian', $stats['openCount'], 'belum lunas', 'client.purchase-invoices.index', 'client.purchase-invoices.create', 'Buat Faktur'],
                ['Pembayaran Pembelian', $stats['overdueCount'], 'jatuh tempo', 'client.purchases.settlement', 'client.purchases.payments', 'Catat Pembayaran'],
            ];
            $shortcuts = [
                ['Pemasok', Supplier::where('company_id', $cid)->count() . ' pemasok', 'client.suppliers.index', 'client.suppliers.create'],
                ['Barang Dibeli', Product::where('company_id', $cid)->where('type', 'goods')->count() . ' barang', 'client.inventory.index', 'client.inventory.create'],
                ['Produk & Jasa (semua)', 'Barang + jasa', 'client.inventory.all', null],
                ['Uang Muka Pembelian', 'Deposit ke pemasok', 'client.purchases.down_payments', 'client.purchases.down_payments.create'],
                ['Retur Pembelian', 'Barang dikembalikan', 'client.purchases.returns', 'client.purchases.returns.create'],
                ['Status Pelunasan', 'Lunas / belum', 'client.purchases.settlement', null],
            ];
        }

        // faktur belum lunas yang paling dekat/lewat jatuh tempo, dan pihak dengan saldo terbesar
        $nearest = $open->sortBy(fn ($i) => optional($i->due_date)->timestamp ?? PHP_INT_MAX)->take(8)->values();
        $topParties = $open->groupBy($partyCol)->map(fn ($g) => [
            'name' => optional($g->first()->{$party})->name ?: '-',
            'count' => $g->count(),
            'due' => round($g->sum('amount_due'), 2),
        ])->sortByDesc('due')->take(5)->values();

        return view('client.hub.center', compact('kind', 'stats', 'flow', 'shortcuts', 'nearest', 'topParties'));
    }
}
