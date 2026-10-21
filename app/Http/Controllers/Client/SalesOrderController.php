<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index()
    {
        $orders = SalesOrder::with(['customer', 'deliveryOrders'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('order_date', 'desc')
            ->paginate(20);
            
        return view('client.sales_orders.index', compact('orders'));
    }

    public function show(SalesOrder $salesOrder)
    {
        abort_if($salesOrder->company_id !== auth()->user()->company_id, 403);
        $salesOrder->load(['customer', 'details.product']);

        $lines = $salesOrder->details->map(function ($d) {
            $gross = (float) $d->quantity * (float) $d->unit_price;
            return [
                'name' => optional($d->product)->name ?? '-',
                'description' => $d->description,
                'quantity' => (float) $d->quantity,
                'price' => (float) $d->unit_price,
                'total' => $gross - $gross * ((float) ($d->discount_percent ?? 0) / 100),
            ];
        })->all();

        return view('client.documents.show', [
            'title' => 'Pesanan Penjualan',
            'number' => $salesOrder->order_number,
            'backUrl' => route('client.sales.orders'),
            'backLabel' => 'Daftar Pesanan Penjualan',
            'meta' => [
                'Tanggal' => optional($salesOrder->order_date)->format('d M Y'),
                'Pelanggan' => optional($salesOrder->customer)->name,
                'No. PO Pelanggan' => $salesOrder->customer_po_number,
                'Status' => $salesOrder->status,
                'Catatan' => $salesOrder->notes,
            ],
            'lines' => $lines,
            'showPrices' => true,
            'total' => (float) $salesOrder->total,
            'actions' => array_values(array_filter([
                // jasa tidak dikirim: tombol pengiriman hanya muncul bila masih ada barang yang belum dikirim
                \App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forSalesOrder($salesOrder))
                    ? ['label' => 'Buat Pengiriman', 'url' => route('client.sales.deliveries.create', ['sales_order_id' => $salesOrder->id])] : null,
                ['label' => 'Buat Faktur', 'url' => route('client.sales-invoices.create', ['sales_order_id' => $salesOrder->id])],
            ])),
            'progress' => ['fulfilledLabel' => 'Terkirim', 'rows' => \App\Services\OrderProgress::forSalesOrder($salesOrder)],
            'groups' => \App\Services\DocumentLinker::relatedForSalesOrder($salesOrder),
        ]);
    }

    public function create()
    {
        $customers = Customer::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        $products = Product::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->where('type', '!=', 'asset')
            ->orderBy('name')
            ->get();
            
        $taxes = Tax::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();
            
        // Generate auto PO number
        $orderNumber = \App\Services\DocumentNumber::next(\App\Models\SalesOrder::class, 'order_number', 'SO-', (int) auth()->user()->company_id, null);
            
        return view('client.sales_orders.create', compact('customers', 'products', 'taxes', 'orderNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('sales_orders', 'order_number')],
            'order_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
            'prices' => 'required|array|min:1',
            'prices.*' => 'required|numeric|min:0',
            'discounts' => 'nullable|array',
            'discounts.*' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            \App\Services\ServiceLines::assertPriced($request->products, $request->prices);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $items = [];
            
            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                $price = floatval($request->prices[$index]);
                $lineDiscPct = floatval($request->discounts[$index] ?? 0);
                $lineTotal = $qty * $price;
                $lineDiscAmt = $lineTotal * ($lineDiscPct / 100);
                $total = $lineTotal - $lineDiscAmt;
                $subtotal += $total;
                
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_percent' => $lineDiscPct,
                    'discount_amount' => $lineDiscAmt,
                    'subtotal' => $total,
                    'description' => $request->row_descriptions[$index] ?? null,
                ];
            }

            $overallDiscPct = floatval($request->discount_percent ?? 0);
            $overallDiscAmt = $subtotal * ($overallDiscPct / 100);
            $afterDiscount = $subtotal - $overallDiscAmt;
            
            $taxAmount = 0;
            if ($request->tax_id) {
                $tax = Tax::find($request->tax_id);
                if ($tax) {
                    $taxAmount = $afterDiscount * ($tax->rate / 100);
                }
            }
            
            $grandTotal = $afterDiscount + $taxAmount;

            $salesOrder = SalesOrder::create([
                'company_id' => auth()->user()->company_id,
                'customer_id' => $request->customer_id,
                'order_number' => $request->order_number,
                'order_date' => $request->order_date,
                'customer_po_number' => $request->customer_po_number,
                'status' => 'open',
                'subtotal' => $subtotal,
                'discount_amount' => $overallDiscAmt,
                'tax_amount' => $taxAmount,
                'total' => $grandTotal,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                SalesOrderDetail::create(array_merge($item, [
                    'sales_order_id' => $salesOrder->id,
                    'tax_id' => $request->tax_id,
                    'tax_amount' => ($subtotal > 0 ? $item['subtotal'] / $subtotal : 0) * $taxAmount, // proportional tax per item
                ]));
            }

            DB::commit();

            return redirect()->route('client.sales.orders')->with('success', 'Pesanan Penjualan berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
