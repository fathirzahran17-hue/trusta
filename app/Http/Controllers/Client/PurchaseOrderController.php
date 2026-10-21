<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $orders = PurchaseOrder::with(['supplier'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('order_date', 'desc')
            ->paginate(20);
            
        return view('client.purchase_orders.index', compact('orders'));
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        abort_if($purchaseOrder->company_id !== auth()->user()->company_id, 403);
        $purchaseOrder->load(['supplier', 'items.product']);

        $lines = $purchaseOrder->items->map(fn ($i) => [
            'name' => optional($i->product)->name ?? '-',
            'description' => $i->description,
            'quantity' => (float) $i->quantity,
            'price' => (float) $i->unit_price,
            'total' => (float) $i->total,
        ])->all();

        return view('client.documents.show', [
            'title' => 'Pesanan Pembelian',
            'number' => $purchaseOrder->po_number,
            'backUrl' => route('client.purchases.orders'),
            'backLabel' => 'Daftar Pesanan Pembelian',
            'meta' => [
                'Tanggal' => optional($purchaseOrder->order_date)->format('d M Y'),
                'Perkiraan Tiba' => optional($purchaseOrder->expected_date)->format('d M Y'),
                'Pemasok' => optional($purchaseOrder->supplier)->name,
                'Status' => $purchaseOrder->status,
                'Catatan' => $purchaseOrder->notes,
            ],
            'lines' => $lines,
            'showPrices' => true,
            'total' => (float) $purchaseOrder->total,
            'actions' => array_values(array_filter([
                \App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forPurchaseOrder($purchaseOrder))
                    ? ['label' => 'Buat Penerimaan', 'url' => route('client.purchases.receipts.create', ['purchase_order_id' => $purchaseOrder->id])] : null,
                ['label' => 'Buat Faktur', 'url' => route('client.purchase-invoices.create', ['po_id' => $purchaseOrder->id])],
            ])),
            'progress' => ['fulfilledLabel' => 'Diterima', 'rows' => \App\Services\OrderProgress::forPurchaseOrder($purchaseOrder)],
            'groups' => \App\Services\DocumentLinker::relatedForPurchaseOrder($purchaseOrder),
        ]);
    }

    public function create()
    {
        $suppliers = Supplier::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        $products = Product::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $taxes = Tax::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();
            
        // Generate auto PO number
        $orderNumber = \App\Services\DocumentNumber::next(\App\Models\PurchaseOrder::class, 'po_number', 'PO-', (int) auth()->user()->company_id, null);
            
        return view('client.purchase_orders.create', compact('suppliers', 'products', 'taxes', 'orderNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_number' => ['required', 'string', 'max:30', \App\Services\DocumentNumber::rule('purchase_orders', 'po_number')],
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
                    'total' => $total,
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

            $purchaseOrder = PurchaseOrder::create([
                'company_id' => auth()->user()->company_id,
                'supplier_id' => $request->supplier_id,
                'po_number' => $request->po_number,
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'status' => 'sent', // Sent to supplier
                'subtotal' => $subtotal,
                'discount_percent' => $overallDiscPct,
                'discount_amount' => $overallDiscAmt,
                'tax_amount' => $taxAmount,
                'total' => $grandTotal,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                PurchaseOrderItem::create(array_merge($item, [
                    'purchase_order_id' => $purchaseOrder->id,
                ]));
            }

            DB::commit();

            return redirect()->route('client.purchases.orders')->with('success', 'Pesanan Pembelian (PO) berhasil dibuat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
