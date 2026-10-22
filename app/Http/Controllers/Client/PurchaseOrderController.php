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
                'Status' => ['draft' => 'Draft', 'sent' => 'Terkirim ke pemasok', 'received' => 'Barang diterima', 'converted' => 'Selesai ditagih', 'cancelled' => 'Dibatalkan'][$purchaseOrder->status] ?? $purchaseOrder->status,
                'Catatan' => $purchaseOrder->notes,
            ],
            'lines' => $lines,
            'showPrices' => true,
            'total' => (float) $purchaseOrder->total,
            'actions' => $this->actionsFor($purchaseOrder),
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

    private function actionsFor(PurchaseOrder $po): array
    {
        if ($po->status === 'cancelled') {
            return [];
        }
        $blockers = \App\Services\OrderLifecycle::purchaseOrderBlockers($po);
        $a = [];
        if (\App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forPurchaseOrder($po))) {
            $a[] = ['label' => 'Buat Penerimaan', 'url' => route('client.purchases.receipts.create', ['purchase_order_id' => $po->id])];
        }
        $a[] = ['label' => 'Buat Faktur', 'url' => route('client.purchase-invoices.create', ['po_id' => $po->id])];
        if (!$blockers) {
            $a[] = ['label' => 'Ubah', 'url' => route('client.purchases.orders.edit', $po), 'style' => 'secondary'];
            $a[] = ['label' => 'Hapus', 'url' => route('client.purchases.orders.destroy', $po), 'method' => 'DELETE', 'style' => 'danger', 'confirm' => 'Hapus pesanan ' . $po->po_number . '? Tindakan ini tidak bisa dibatalkan.'];
        } elseif (\App\Services\OrderLifecycle::canCancelPurchase($po)) {
            $a[] = ['label' => 'Batalkan Pesanan', 'url' => route('client.purchases.orders.cancel', $po), 'method' => 'POST', 'style' => 'danger',
                'confirm' => 'Batalkan pesanan ' . $po->po_number . '? Sisa yang belum diterima/ditagih tidak bisa diproses lagi. Dokumen yang sudah dibuat (' . implode(', ', $blockers) . ') tetap ada.'];
        }

        return $a;
    }

    private function guardEditable(PurchaseOrder $po)
    {
        if ($po->status === 'cancelled') {
            return back()->with('error', 'Pesanan ini sudah dibatalkan.');
        }
        if ($blockers = \App\Services\OrderLifecycle::purchaseOrderBlockers($po)) {
            return back()->with('error', 'Pesanan tidak bisa diubah/dihapus karena sudah punya ' . implode(', ', $blockers) . '. Gunakan "Batalkan Pesanan" bila perlu.');
        }

        return null;
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        if ($r = $this->guardEditable($purchaseOrder)) {
            return $r;
        }
        $cid = auth()->user()->company_id;
        $purchaseOrder->load('items');
        $suppliers = Supplier::where('company_id', $cid)->orderBy('name')->get();
        $products = Product::where('company_id', $cid)->where('is_active', true)->orderBy('name')->get();
        $taxes = Tax::where('company_id', $cid)->where('is_active', true)->get();
        $order = $purchaseOrder;
        $orderNumber = $purchaseOrder->po_number;
        $prefillRows = $purchaseOrder->items->map(fn ($i) => [
            'product_id' => $i->product_id, 'description' => $i->description, 'quantity' => (float) $i->quantity,
            'price' => (float) $i->unit_price, 'discount' => (float) ($i->discount_percent ?? 0),
        ])->values()->all();
        $editDiscPct = (float) $purchaseOrder->discount_percent;
        $after = (float) $purchaseOrder->subtotal - (float) $purchaseOrder->discount_amount;
        $rate = $after > 0 ? round((float) $purchaseOrder->tax_amount / $after * 100, 2) : 0;
        $editTaxId = $rate > 0 ? optional($taxes->first(fn ($t) => abs((float) $t->rate - $rate) < 0.05))->id : null;

        return view('client.purchase_orders.create', compact('suppliers', 'products', 'taxes', 'order', 'orderNumber', 'prefillRows', 'editDiscPct', 'editTaxId'));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($r = $this->guardEditable($purchaseOrder)) {
            return $r;
        }
        $request->validate([
            'supplier_id' => ['required', \Illuminate\Validation\Rule::exists('suppliers', 'id')->where('company_id', auth()->user()->company_id)],
            'po_number' => ['required', 'string', 'max:30', \App\Services\DocumentNumber::rule('purchase_orders', 'po_number')->ignore($purchaseOrder->id)],
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
                $items[] = ['product_id' => $productId, 'quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $pct, 'total' => $total,
                    'description' => $request->row_descriptions[$i] ?? null];
            }
            $overallPct = (float) ($request->discount_percent ?? 0);
            $overallDiscAmt = $subtotal * ($overallPct / 100);
            $after = $subtotal - $overallDiscAmt;
            $taxAmount = 0;
            if ($request->tax_id && ($tax = Tax::find($request->tax_id))) {
                $taxAmount = $after * ($tax->rate / 100);
            }

            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id, 'po_number' => $request->po_number, 'order_date' => $request->order_date,
                'expected_date' => $request->expected_date, 'subtotal' => $subtotal, 'discount_percent' => $overallPct,
                'discount_amount' => $overallDiscAmt, 'tax_amount' => $taxAmount, 'total' => $after + $taxAmount, 'notes' => $request->notes,
            ]);
            PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->delete();
            foreach ($items as $item) {
                PurchaseOrderItem::create(array_merge($item, ['purchase_order_id' => $purchaseOrder->id]));
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }

        return redirect()->route('client.purchases.orders.show', $purchaseOrder)->with('success', 'Pesanan Pembelian berhasil diubah.');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($r = $this->guardEditable($purchaseOrder)) {
            return $r;
        }
        DB::transaction(function () use ($purchaseOrder) {
            PurchaseOrderItem::where('purchase_order_id', $purchaseOrder->id)->delete();
            $purchaseOrder->delete();
        });

        return redirect()->route('client.purchases.orders')->with('success', 'Pesanan ' . $purchaseOrder->po_number . ' dihapus.');
    }

    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if (!\App\Services\OrderLifecycle::canCancelPurchase($purchaseOrder)) {
            return back()->with('error', 'Pesanan dengan status ini tidak bisa dibatalkan.');
        }
        $purchaseOrder->update(['status' => 'cancelled']);

        return redirect()->route('client.purchases.orders.show', $purchaseOrder)->with('success', 'Pesanan ' . $purchaseOrder->po_number . ' dibatalkan.');
    }
}
