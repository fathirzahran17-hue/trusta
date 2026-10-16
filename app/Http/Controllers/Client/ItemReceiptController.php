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
        
        $purchaseOrders = PurchaseOrder::where('company_id', $companyId)
            ->whereIn('status', ['sent']) // Sent POs can be received
            ->orderBy('order_date', 'desc')
            ->get();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'goods')
            ->orderBy('name')
            ->get();
            
        $warehouses = \App\Models\Warehouse::where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
            
        // Auto number
        $lastReceipt = ItemReceipt::where('company_id', $companyId)
            ->whereYear('receipt_date', date('Y'))
            ->whereMonth('receipt_date', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        $nextId = $lastReceipt ? (intval(substr($lastReceipt->receipt_number, -4)) + 1) : 1;
        $receiptNumber = 'IR-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
        return view('client.item_receipts.create', compact('suppliers', 'purchaseOrders', 'products', 'warehouses', 'receiptNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'receipt_number' => 'required|string|max:100',
            'receipt_date' => 'required|date',
            'status' => 'required|in:draft,received',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $itemReceipt = ItemReceipt::create([
                'company_id' => $companyId,
                'supplier_id' => $request->supplier_id,
                'warehouse_id' => $request->warehouse_id,
                'purchase_order_id' => $request->purchase_order_id,
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
                    'product_id' => $productId,
                    'description' => $request->row_descriptions[$index] ?? null,
                    'quantity' => $qty,
                ]);
            }

            // Jika status received, catat mutasi stok dan perbarui Moving Average (HPP)
            if ($request->status == 'received' && $totalValue > 0) {
                
                foreach ($request->products as $index => $productId) {
                    $qty = floatval($request->quantities[$index]);
                    $product = Product::find($productId);
                    $price = $product->buy_price; // Harga beli baru yang diinput (sementara pakai harga master)
                    // TODO: Jika form input Penerimaan memiliki harga beli sendiri, harusnya pakai harga form. 
                    // Saat ini kita pakai harga standar produk.
                    
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
                
                // Update PO status jika ada
                if ($request->purchase_order_id) {
                    $po = PurchaseOrder::find($request->purchase_order_id);
                    if ($po) {
                        $po->update(['status' => 'received']);
                    }
                }
            }

            DB::commit();
            return redirect()->route('client.purchases.receipts')->with('success', 'Penerimaan Barang berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
