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
        
        $salesOrders = SalesOrder::with('customer')->where('company_id', $companyId)
            ->whereIn('status', ['open'])
            ->orderBy('order_date', 'desc')
            ->get()
            // pesanan khusus jasa (atau semua barangnya sudah dikirim) tidak perlu pengiriman
            ->filter(fn ($so) => \App\Services\OrderProgress::needsFulfilment(\App\Services\OrderProgress::forSalesOrder($so)))
            ->values();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('type', 'goods')
            ->orderBy('name')
            ->get();
            
        // Auto number
        $deliveryNumber = \App\Services\DocumentNumber::next(\App\Models\DeliveryOrder::class, 'delivery_number', 'DO-', (int) $companyId, null);
            
        // Dari tombol "Buat Pengiriman" pada Pesanan Penjualan: isi pelanggan & baris barang otomatis
        $prefill = \App\Services\DocumentLinker::prefillFromSalesOrder($request->sales_order_id, $companyId, 'fulfilled');
        $goodsIds = $products->pluck('id')->all();
        $prefill['rows'] = array_values(array_filter($prefill['rows'], fn ($r) => in_array($r['product_id'], $goodsIds)));
        if ($prefill['order'] && !$salesOrders->contains('id', $prefill['order']->id)) {
            $salesOrders->push($prefill['order']);
        }

        return view('client.sales_deliveries.create', compact('customers', 'salesOrders', 'products', 'deliveryNumber', 'prefill'));
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        abort_if($deliveryOrder->company_id !== auth()->user()->company_id, 403);
        $deliveryOrder->load(['customer', 'salesOrder', 'details.product']);

        $lines = $deliveryOrder->details->map(fn ($d) => [
            'name' => optional($d->product)->name ?? '-',
            'description' => $d->description,
            'quantity' => (float) $d->quantity,
        ])->all();

        return view('client.documents.show', [
            'title' => 'Pengiriman Barang',
            'number' => $deliveryOrder->delivery_number,
            'backUrl' => route('client.sales.deliveries'),
            'backLabel' => 'Daftar Pengiriman',
            'meta' => [
                'Tanggal' => optional($deliveryOrder->delivery_date)->format('d M Y'),
                'Pelanggan' => optional($deliveryOrder->customer)->name,
                'Pengemudi' => $deliveryOrder->driver_name,
                'Plat Nomor' => $deliveryOrder->license_plate,
                'Alamat Kirim' => $deliveryOrder->shipping_address,
                'Status' => $deliveryOrder->status,
                'Catatan' => $deliveryOrder->notes,
            ],
            'lines' => $lines,
            'showPrices' => false,
            'total' => null,
            'actions' => $deliveryOrder->sales_order_id
                ? [['label' => 'Buat Faktur', 'url' => route('client.sales-invoices.create', ['delivery_order_id' => $deliveryOrder->id])]]
                : [],
            'groups' => \App\Services\DocumentLinker::relatedForDelivery($deliveryOrder),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('delivery_orders', 'delivery_number')],
            'delivery_date' => 'required|date',
            'status' => 'required|in:draft,shipped',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
        ]);

        $companyId = auth()->user()->company_id;
        $requested = \App\Services\OrderProgress::requested($request->products, $request->quantities);
        $so = $request->sales_order_id ? SalesOrder::where('company_id', $companyId)->find($request->sales_order_id) : null;
        if ($request->sales_order_id && !$so) {
            return back()->withInput()->with('error', 'Pesanan Penjualan tidak ditemukan.');
        }
        try {
            if ($so) {
                \App\Services\OrderProgress::assertWithinRemaining(\App\Services\OrderProgress::forSalesOrder($so), 'fulfilled', $requested, 'pengiriman');
            }
            if ($request->status == 'shipped') {
                foreach ($requested as $pid => $qty) {
                    $p = Product::where('company_id', $companyId)->find($pid);
                    if ($p && $p->type === 'goods' && $p->stock + 0.0001 < $qty) {
                        throw new \DomainException("Stok {$p->name} tidak cukup (tersedia " . (float) $p->stock . ", diminta " . (float) $qty . ').');
                    }
                }
            }
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $deliveryOrder = DeliveryOrder::create([
                'company_id' => $companyId,
                'customer_id' => $request->customer_id,
                'sales_order_id' => $so?->id,
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
            if ($so) {
                \App\Services\OrderProgress::syncSalesOrder($so->fresh());
            }
            return redirect()->route('client.sales.deliveries')->with('success', 'Pengiriman Pesanan berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
