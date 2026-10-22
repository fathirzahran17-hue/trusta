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
                'Status' => ['draft' => 'Draft', 'open' => 'Terbuka', 'closed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$salesOrder->status] ?? $salesOrder->status,
                'Catatan' => $salesOrder->notes,
            ],
            'lines' => $lines,
            'showPrices' => true,
            'total' => (float) $salesOrder->total,
            'actions' => $this->actionsFor($salesOrder),
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

    /** Tombol di halaman detail: dokumen lanjutan, ubah, hapus / batalkan sesuai keadaan pesanan. */
    private function actionsFor(SalesOrder $so): array
    {
        if ($so->status === 'cancelled') {
            return [];
        }
        $blockers = \App\Services\OrderLifecycle::salesOrderBlockers($so);
        $a = [];
        if (\App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forSalesOrder($so))) {
            // jasa tidak dikirim: tombol pengiriman hanya muncul bila masih ada barang yang belum dikirim
            $a[] = ['label' => 'Buat Pengiriman', 'url' => route('client.sales.deliveries.create', ['sales_order_id' => $so->id])];
        }
        $a[] = ['label' => 'Buat Faktur', 'url' => route('client.sales-invoices.create', ['sales_order_id' => $so->id])];
        if (!$blockers) {
            $a[] = ['label' => 'Ubah', 'url' => route('client.sales.orders.edit', $so), 'style' => 'secondary'];
            $a[] = ['label' => 'Hapus', 'url' => route('client.sales.orders.destroy', $so), 'method' => 'DELETE', 'style' => 'danger', 'confirm' => 'Hapus pesanan ' . $so->order_number . '? Tindakan ini tidak bisa dibatalkan.'];
        } elseif (\App\Services\OrderLifecycle::canCancelSales($so)) {
            $a[] = ['label' => 'Batalkan Pesanan', 'url' => route('client.sales.orders.cancel', $so), 'method' => 'POST', 'style' => 'danger',
                'confirm' => 'Batalkan pesanan ' . $so->order_number . '? Sisa yang belum diproses tidak bisa dikirim/ditagih lagi. Dokumen yang sudah dibuat (' . implode(', ', $blockers) . ') tetap ada.'];
        }

        return $a;
    }

    private function guardEditable(SalesOrder $so)
    {
        if ($so->status === 'cancelled') {
            return back()->with('error', 'Pesanan ini sudah dibatalkan.');
        }
        if ($blockers = \App\Services\OrderLifecycle::salesOrderBlockers($so)) {
            return back()->with('error', 'Pesanan tidak bisa diubah/dihapus karena sudah punya ' . implode(', ', $blockers) . '. Gunakan "Batalkan Pesanan" bila perlu.');
        }

        return null;
    }

    public function edit(SalesOrder $salesOrder)
    {
        if ($r = $this->guardEditable($salesOrder)) {
            return $r;
        }
        $cid = auth()->user()->company_id;
        $salesOrder->load('details');
        $customers = Customer::where('company_id', $cid)->orderBy('name')->get();
        $products = Product::where('company_id', $cid)->where('is_active', true)->where('type', '!=', 'asset')->orderBy('name')->get();
        $taxes = Tax::where('company_id', $cid)->where('is_active', true)->get();
        $order = $salesOrder;
        $orderNumber = $salesOrder->order_number;
        $prefillRows = $salesOrder->details->map(fn ($d) => [
            'product_id' => $d->product_id, 'description' => $d->description, 'quantity' => (float) $d->quantity,
            'price' => (float) $d->unit_price, 'discount' => (float) ($d->discount_percent ?? 0),
        ])->values()->all();
        $lines = (float) $salesOrder->details->sum('subtotal');
        $editDiscPct = $lines > 0 ? round((float) $salesOrder->discount_amount / $lines * 100, 2) : 0;
        $editTaxId = optional($salesOrder->details->first())->tax_id;

        return view('client.sales_orders.create', compact('customers', 'products', 'taxes', 'order', 'orderNumber', 'prefillRows', 'editDiscPct', 'editTaxId'));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        if ($r = $this->guardEditable($salesOrder)) {
            return $r;
        }
        $request->validate([
            'customer_id' => ['required', \Illuminate\Validation\Rule::exists('customers', 'id')->where('company_id', auth()->user()->company_id)],
            'order_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('sales_orders', 'order_number')->ignore($salesOrder->id)],
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
            foreach ($request->products as $i => $productId) {
                $qty = (float) $request->quantities[$i];
                $price = (float) $request->prices[$i];
                $pct = (float) ($request->discounts[$i] ?? 0);
                $gross = $qty * $price;
                $total = $gross - $gross * ($pct / 100);
                $subtotal += $total;
                $items[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $pct,
                    'discount_amount' => $gross * ($pct / 100), 'subtotal' => $total, 'description' => $request->row_descriptions[$i] ?? null];
            }
            $overallDiscAmt = $subtotal * ((float) ($request->discount_percent ?? 0) / 100);
            $after = $subtotal - $overallDiscAmt;
            $taxAmount = 0;
            if ($request->tax_id && ($tax = Tax::find($request->tax_id))) {
                $taxAmount = $after * ($tax->rate / 100);
            }

            $salesOrder->update([
                'customer_id' => $request->customer_id, 'order_number' => $request->order_number, 'order_date' => $request->order_date,
                'customer_po_number' => $request->customer_po_number, 'subtotal' => $subtotal, 'discount_amount' => $overallDiscAmt,
                'tax_amount' => $taxAmount, 'total' => $after + $taxAmount, 'notes' => $request->notes,
            ]);
            SalesOrderDetail::where('sales_order_id', $salesOrder->id)->delete();
            foreach ($items as $item) {
                SalesOrderDetail::create(array_merge($item, [
                    'sales_order_id' => $salesOrder->id, 'tax_id' => $request->tax_id,
                    'tax_amount' => ($subtotal > 0 ? $item['subtotal'] / $subtotal : 0) * $taxAmount,
                ]));
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }

        return redirect()->route('client.sales.orders.show', $salesOrder)->with('success', 'Pesanan Penjualan berhasil diubah.');
    }

    public function destroy(SalesOrder $salesOrder)
    {
        if ($r = $this->guardEditable($salesOrder)) {
            return $r;
        }
        DB::transaction(function () use ($salesOrder) {
            SalesOrderDetail::where('sales_order_id', $salesOrder->id)->delete();
            $salesOrder->delete();
        });

        return redirect()->route('client.sales.orders')->with('success', 'Pesanan ' . $salesOrder->order_number . ' dihapus.');
    }

    public function cancel(SalesOrder $salesOrder)
    {
        if (!\App\Services\OrderLifecycle::canCancelSales($salesOrder)) {
            return back()->with('error', 'Pesanan dengan status ini tidak bisa dibatalkan.');
        }
        $salesOrder->update(['status' => 'cancelled']);

        return redirect()->route('client.sales.orders.show', $salesOrder)->with('success', 'Pesanan ' . $salesOrder->order_number . ' dibatalkan.');
    }
}
