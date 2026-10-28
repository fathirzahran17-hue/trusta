<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ItemReceipt;
use App\Models\ItemReceiptDetail;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemReceiptController extends Controller
{
    public function index()
    {
        $receipts = ItemReceipt::with(['supplier', 'purchaseOrder'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('receipt_date', 'desc')
            ->paginate(20);
            
        return view('client.item_receipts.index', compact('receipts'));
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get();
        
        $purchaseOrders = PurchaseOrder::with('supplier')->where('company_id', $companyId)
            ->whereIn('status', ['sent']) // Sent POs can be received
            ->orderBy('order_date', 'desc')
            ->get()
            ->filter(fn ($po) => \App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forPurchaseOrder($po)))
            ->values();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'goods')
            ->orderBy('name')
            ->get();
            
        $warehouses = \App\Models\Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
            
        // Auto number
        $receiptNumber = \App\Services\DocumentNumber::next(\App\Models\ItemReceipt::class, 'receipt_number', 'IR-', (int) $companyId, null);
            
        // Dari tombol "Buat Penerimaan" atau pilihan beberapa PO sekaligus (pemasok sama): isi pemasok & baris barang otomatis
        $poIds = array_values(array_filter(array_map('intval', \Illuminate\Support\Arr::wrap(request('purchase_order_id')))));
        $prefill = \App\Services\DocumentLinker::prefillFromSources('purchase', $poIds, [], (int) $companyId, 'fulfilled');
        $goodsIds = $products->pluck('id')->all();
        $prefill['rows'] = array_values(array_filter($prefill['rows'], fn ($r) => in_array($r['product_id'], $goodsIds)));
        foreach ($prefill['orders'] as $o) {
            if (!$purchaseOrders->contains('id', $o->id)) $purchaseOrders->push($o);
        }
        $checked = array_map(fn ($i) => "purchase_order_id=$i", $poIds);
        $options = $purchaseOrders->map(fn ($o) => ['id' => $o->id, 'param' => 'purchase_order_id', 'group' => 'Pesanan Pembelian (PO)', 'party_id' => $o->supplier_id, 'text' => $o->po_number . ' — ' . optional($o->supplier)->name])->all();

        return view('client.item_receipts.create', compact('suppliers', 'purchaseOrders', 'products', 'warehouses', 'receiptNumber', 'prefill', 'checked', 'options'));
    }

    public function show(ItemReceipt $itemReceipt)
    {
        abort_if($itemReceipt->company_id !== auth()->user()->company_id, 403);
        $itemReceipt->load(['supplier', 'purchaseOrder', 'details.product']);
        $orderNo = $itemReceipt->sourceOrders()->pluck('po_number', 'id');

        $lines = $itemReceipt->details->map(fn ($d) => [
            'name' => (optional($d->product)->name ?? '-') . (($o = $orderNo[$d->purchase_order_id ?: $itemReceipt->purchase_order_id] ?? null) && $orderNo->count() > 1 ? ' (' . $o . ')' : ''),
            'description' => $d->description,
            'quantity' => (float) $d->quantity,
        ])->all();

        return view('client.documents.show', [
            'title' => 'Penerimaan Barang',
            'number' => $itemReceipt->receipt_number,
            'backUrl' => route('client.purchases.receipts'),
            'backLabel' => 'Daftar Penerimaan',
            'meta' => [
                'Tanggal' => optional($itemReceipt->receipt_date)->format('d M Y'),
                'Pemasok' => optional($itemReceipt->supplier)->name,
                'Pesanan' => $orderNo->implode(', ') ?: null,
                'No. Surat Jalan' => $itemReceipt->delivery_note_number,
                'Status' => ['draft' => 'Draft', 'received' => 'Diterima', 'cancelled' => 'Dibatalkan'][$itemReceipt->status] ?? $itemReceipt->status,
                'Catatan' => $itemReceipt->notes,
            ],
            'lines' => $lines,
            'showPrices' => false,
            'total' => null,
            'actions' => $itemReceipt->status === 'cancelled' ? [] : array_values(array_filter([
                $itemReceipt->status === 'draft' ? null : ['label' => 'Buat Faktur', 'url' => route('client.purchase-invoices.create', ['item_receipt_id' => $itemReceipt->id])],
                ['label' => 'Batalkan Penerimaan', 'url' => route('client.purchases.receipts.cancel', $itemReceipt), 'method' => 'POST', 'style' => 'danger',
                    'confirm' => 'Batalkan penerimaan ' . $itemReceipt->receipt_number . '? Stok masuk akan ditarik kembali (hanya bila barangnya belum terpakai).'],
            ])),
            'groups' => \App\Services\DocumentLinker::relatedForReceipt($itemReceipt),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receipt_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('item_receipts', 'receipt_number')],
            'receipt_date' => 'required|date',
            'status' => 'required|in:draft,received',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
        ]);

        $companyId = auth()->user()->company_id;
        $requested = \App\Services\OrderProgress::requested($request->products, $request->quantities);
        try {
            [$rowOrders, $orders] = \App\Services\FulfilmentSources::resolve('purchase', $request, (int) $companyId, (int) $request->supplier_id, $request->status == 'received');
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $itemReceipt = ItemReceipt::create([
                'company_id' => $companyId,
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'purchase_order_id' => \App\Services\FulfilmentSources::headerOrder($rowOrders),
                'receipt_number' => $request->receipt_number,
                'receipt_date' => $request->receipt_date,
                'delivery_note_number' => $request->delivery_note_number,
                'status' => $request->status,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            $totalValue = 0;

            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                
                $product = Product::find($productId);
                $totalValue += ($product->buy_price * $qty);

                ItemReceiptDetail::create([
                    'item_receipt_id' => $itemReceipt->id,
                    'purchase_order_id' => $rowOrders[$index] ?? null,
                    'product_id' => $productId,
                    'description' => $request->row_descriptions[$index] ?? null,
                    'quantity' => $qty,
                ]);
            }

            // Jika status received, catat mutasi stok dan perbarui Moving Average (HPP)
            if ($request->status == 'received') {
                
                foreach ($request->products as $index => $productId) {
                    $qty = floatval($request->quantities[$index]);
                    $product = Product::find($productId);
                    // Harga perolehan mengikuti Pesanan Pembelian (setelah diskon baris); tanpa PO pakai harga beli master
                    $price = (float) $product->buy_price;
                    $po = ($rowOrders[$index] ?? null) ? $orders[$rowOrders[$index]] : null;
                    if ($po) {
                        $poItem = $po->items()->where('product_id', $productId)->first();
                        if ($poItem) {
                            $price = (float) $poItem->unit_price * (1 - (float) $poItem->discount_percent / 100);
                        }
                    }

                    if ($product->type === 'goods') {
                        // 1. Moving Average Calculation
                        $oldStock = $product->stock;
                        $oldPrice = $product->buy_price;
                        $newTotalStock = $oldStock + $qty;
                        
                        if ($newTotalStock > 0) {
                            $movingAverage = (($oldStock * $oldPrice) + ($qty * $price)) / $newTotalStock;
                            $product->buy_price = $movingAverage;
                            $product->save();
                        }

                        \App\Models\InventoryMovement::create([
                            'company_id' => $companyId,
                            'product_id' => $product->id,
                            'warehouse_id' => $request->warehouse_id,
                            'date' => $request->receipt_date,
                            'type' => 'in',
                            'quantity' => $qty,
                            'unit_cost' => $price,
                            'remaining_qty' => $qty,
                            'reference_type' => 'item_receipt',
                            'reference_id' => $itemReceipt->id,
                            'notes' => 'Penerimaan Barang ' . $itemReceipt->receipt_number,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            DB::commit();
            foreach ($orders as $po) {
                \App\Services\OrderProgress::syncPurchaseOrder($po->fresh());
            }
            return redirect()->route('client.purchases.receipts')->with('success', 'Penerimaan Barang berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function cancel(ItemReceipt $itemReceipt)
    {
        abort_if($itemReceipt->company_id !== auth()->user()->company_id, 403);
        try {
            \App\Services\OrderLifecycle::cancelReceipt($itemReceipt);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('client.purchases.receipts.show', $itemReceipt)->with('success', 'Penerimaan ' . $itemReceipt->receipt_number . ' dibatalkan, stok ditarik kembali.');
    }
}
