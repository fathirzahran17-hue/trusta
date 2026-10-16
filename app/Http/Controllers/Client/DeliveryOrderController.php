<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderDetail;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        $deliveries = DeliveryOrder::with(['customer', 'salesOrder'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('delivery_date', 'desc')
            ->paginate(20);
            
        return view('client.sales_deliveries.index', compact('deliveries'));
    }

    public function create(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        
        $salesOrders = SalesOrder::where('company_id', $companyId)
            ->whereIn('status', ['open'])
            ->orderBy('order_date', 'desc')
            ->get();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'goods')
            ->orderBy('name')
            ->get();
            
        // Auto number
        $lastDO = DeliveryOrder::where('company_id', $companyId)
            ->whereYear('delivery_date', date('Y'))
            ->whereMonth('delivery_date', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        $nextId = $lastDO ? (intval(substr($lastDO->delivery_number, -4)) + 1) : 1;
        $deliveryNumber = 'DO-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
        return view('client.sales_deliveries.create', compact('customers', 'salesOrders', 'products', 'deliveryNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_number' => 'required|string|max:100',
            'delivery_date' => 'required|date',
            'status' => 'required|in:draft,shipped',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $deliveryOrder = DeliveryOrder::create([
                'company_id' => $companyId,
                'customer_id' => $request->customer_id,
                'sales_order_id' => $request->sales_order_id,
                'delivery_number' => $request->delivery_number,
                'delivery_date' => $request->delivery_date,
                'driver_name' => $request->driver_name,
                'license_plate' => $request->license_plate,
                'shipping_address' => $request->shipping_address,
                'status' => $request->status,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            $totalHpp = 0;

            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                
                $product = Product::find($productId);
                $totalHpp += ($product->buy_price * $qty);

                DeliveryOrderDetail::create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'product_id' => $productId,
                    'description' => $request->row_descriptions[$index] ?? null,
                    'quantity' => $qty,
                ]);
            }

            // Jika status shipped, catat mutasi stok pengeluaran
            if ($request->status == 'shipped') {
                foreach ($request->products as $index => $productId) {
                    $qty = floatval($request->quantities[$index]);
                    $product = Product::find($productId);
                    
                    if ($product->type === 'goods') {
                        \App\Models\InventoryMovement::create([
                            'company_id' => $companyId,
                            'product_id' => $product->id,
                            'date' => $request->delivery_date,
                            'type' => 'out',
                            'quantity' => $qty,
                            'reference_type' => 'delivery_order',
                            'reference_id' => $deliveryOrder->id,
                            'notes' => 'Pengiriman Barang ' . $deliveryOrder->delivery_number,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            DB::commit();
            return redirect()->route('client.sales.deliveries')->with('success', 'Pengiriman Pesanan berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
