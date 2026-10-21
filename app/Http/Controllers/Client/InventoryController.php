<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Warehouse;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Account;
use App\Models\InventoryMovement;
use App\Services\AccountResolver;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    private function companyId() { return auth()->user()->company_id; }

    // ========== BARANG & JASA (Products) ==========
    /** Daftar Barang (stok). */
    public function index(Request $request)
    {
        return $this->listProducts($request, 'goods');
    }

    /** Gabungan Barang + Jasa dalam satu daftar. */
    public function allProducts(Request $request)
    {
        return $this->listProducts($request, 'all');
    }

    /** Daftar Jasa (pendapatan jasa; tidak ada stok/HPP). */
    public function services(Request $request)
    {
        return $this->listProducts($request, 'service');
    }

    private function listProducts(Request $request, string $kind)
    {
        $query = Product::where('company_id', $this->companyId())->whereIn('type', $kind === 'all' ? ['goods', 'service'] : [$kind])->with('category');
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        $products = $query->orderBy('name')->paginate(20)->withQueryString();
        $counts = Product::where('company_id', $this->companyId())->whereIn('type', ['goods', 'service'])->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');

        return view('client.inventory.index', ['products' => $products, 'kind' => $kind, 'counts' => $counts]);
    }

    public function show(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $movements = $product->inventoryMovements()->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
        return view('client.inventory.show', compact('product', 'movements'));
    }

    /** Data untuk form: akun pendapatan/biaya yang boleh dipilih (bukan akun induk), satuan, kategori, dan nilai awal otomatis. */
    private function formData(string $kind, ?Product $product = null): array
    {
        $cid = $this->companyId();
        $leaf = fn ($types) => Account::where('company_id', $cid)->whereIn('type', $types)->where('is_active', true)->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header)->values();

        $revenueAccounts = $leaf(['revenue', 'other_income']);
        $costAccounts = $leaf(['cost_of_sales', 'expense']);
        $defaultRevenue = $kind === 'service' ? AccountResolver::serviceRevenue($cid) : AccountResolver::salesRevenue($cid);

        return [
            'kind' => $kind,
            'product' => $product,
            'categories' => ProductCategory::where('company_id', $cid)->orderBy('name')->get(),
            'units' => Unit::where('company_id', $cid)->orderBy('name')->get(),
            'revenueAccounts' => $revenueAccounts,
            'costAccounts' => $costAccounts,
            'defaultRevenueId' => optional($defaultRevenue)->id,
            'defaultCostId' => $kind === 'goods' ? optional(AccountResolver::cogs($cid))->id : null,
            'nextSku' => $this->nextSku($kind),
        ];
    }

    private function nextSku(string $kind): string
    {
        $prefix = $kind === 'service' ? 'JSA-' : 'BRG-';
        $max = 0;
        foreach (Product::where('company_id', $this->companyId())->where('sku', 'like', $prefix . '%')->pluck('sku') as $sku) {
            if (preg_match('/(\d+)$/', (string) $sku, $m)) $max = max($max, (int) $m[1]);
        }
        return $prefix . str_pad($max + 1, 4, '0', STR_PAD_LEFT);
    }

    public function create(Request $request)
    {
        $kind = $request->query('type') === 'service' ? 'service' : 'goods';
        return view('client.inventory.create', $this->formData($kind));
    }

    public function createService()
    {
        return view('client.inventory.create', $this->formData('service'));
    }

    /** Validasi + normalisasi sesuai jenis: jasa tidak punya stok/HPP kecuali memakai subkontraktor. */
    private function productData(Request $request, string $kind, ?Product $product = null): array
    {
        $cid = $this->companyId();
        $skuRule = \Illuminate\Validation\Rule::unique('products', 'sku')->where('company_id', $cid)->ignore($product?->id);
        $accountRule = fn () => \Illuminate\Validation\Rule::exists('accounts', 'id')->where('company_id', $cid);

        $v = $request->validate([
            'sku' => ['required', 'string', 'max:50', $skuRule],
            'name' => 'required|string|max:255',
            'category_id' => ['nullable', \Illuminate\Validation\Rule::exists('product_categories', 'id')->where('company_id', $cid)],
            'unit' => 'nullable|string|max:20',
            'sell_price' => 'required|numeric|min:0',
            'buy_price' => 'nullable|numeric|min:0',
            'revenue_account_id' => ['nullable', $accountRule()],
            'expense_account_id' => ['nullable', $accountRule()],
            'is_subcontract' => 'nullable',
        ], ['sku.unique' => 'Kode ini sudah dipakai produk lain.']);

        $data = [
            'sku' => $v['sku'], 'name' => $v['name'], 'type' => $kind, 'category_id' => $v['category_id'] ?? null,
            'sell_price' => $v['sell_price'],
            'revenue_account_id' => $v['revenue_account_id'] ?? null,
        ];

        // Akun pendapatan otomatis bila tidak dipilih
        if (!$data['revenue_account_id']) {
            $acc = $kind === 'service' ? AccountResolver::serviceRevenue($cid) : AccountResolver::salesRevenue($cid);
            $data['revenue_account_id'] = optional($acc)->id;
        }

        if ($kind === 'service') {
            $data['unit'] = ($v['unit'] ?? null) ?: 'kali';
            if ($request->boolean('is_subcontract')) {
                $data['buy_price'] = $v['buy_price'] ?? 0;
                $data['expense_account_id'] = $v['expense_account_id'] ?? optional(AccountResolver::subcontractCost($cid))->id;
            } else {
                $data['buy_price'] = 0;
                $data['expense_account_id'] = null; // jasa murni: tidak ada HPP
            }
        } else {
            $data['unit'] = ($v['unit'] ?? null) ?: 'pcs';
            $data['buy_price'] = $v['buy_price'] ?? 0;
            $data['expense_account_id'] = $v['expense_account_id'] ?? optional(AccountResolver::cogs($cid))->id;
        }

        return $data;
    }

    public function store(Request $request)
    {
        $kind = $request->input('type') === 'service' ? 'service' : 'goods';
        $data = $this->productData($request, $kind);
        $data['company_id'] = $this->companyId();
        $data['is_active'] = true;
        Product::create($data);

        return redirect()->route($kind === 'service' ? 'client.inventory.services' : 'client.inventory.index')
            ->with('success', ($kind === 'service' ? 'Jasa' : 'Barang') . ' berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        return view('client.inventory.edit', $this->formData($product->type === 'service' ? 'service' : 'goods', $product));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $kind = $product->type === 'service' ? 'service' : 'goods';
        $data = $this->productData($request, $kind, $product);
        $data['is_active'] = $request->has('is_active');
        $product->update($data);

        return redirect()->route($kind === 'service' ? 'client.inventory.services' : 'client.inventory.index')
            ->with('success', ($kind === 'service' ? 'Jasa' : 'Barang') . ' berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $kind = $product->type === 'service' ? 'service' : 'goods';
        try {
            $product->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->with('error', 'Tidak bisa dihapus karena sudah dipakai di transaksi. Nonaktifkan saja lewat menu Ubah.');
        }
        return redirect()->route($kind === 'service' ? 'client.inventory.services' : 'client.inventory.index')->with('success', 'Data berhasil dihapus.');
    }

    // ========== GUDANG (Warehouse) ==========
    public function warehouses(Request $request)
    {
        $warehouses = Warehouse::where('company_id', $this->companyId())->orderBy('code')->get();
        return view('client.inventory.warehouses', compact('warehouses'));
    }

    public function storeWarehouse(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'person_in_charge' => 'nullable|string|max:255',
        ]);
        $validated['company_id'] = $this->companyId();
        Warehouse::create($validated);
        return redirect()->route('client.inventory.warehouses')->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function destroyWarehouse(Warehouse $warehouse)
    {
        if ($warehouse->company_id != $this->companyId()) abort(404);
        $warehouse->delete();
        return redirect()->route('client.inventory.warehouses')->with('success', 'Gudang berhasil dihapus.');
    }

    // ========== SATUAN (Unit) ==========
    public function units()
    {
        $units = Unit::where('company_id', $this->companyId())->orderBy('name')->get();
        return view('client.inventory.units', compact('units'));
    }

    public function storeUnit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'abbreviation' => 'required|string|max:10',
        ]);
        $validated['company_id'] = $this->companyId();
        Unit::create($validated);
        return redirect()->route('client.inventory.units')->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function destroyUnit(Unit $unit)
    {
        if ($unit->company_id != $this->companyId()) abort(404);
        $unit->delete();
        return redirect()->route('client.inventory.units')->with('success', 'Satuan berhasil dihapus.');
    }

    // ========== MEREK (Brand) ==========
    public function brands()
    {
        $brands = Brand::where('company_id', $this->companyId())->orderBy('name')->get();
        return view('client.inventory.brands', compact('brands'));
    }

    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        $validated['company_id'] = $this->companyId();
        Brand::create($validated);
        return redirect()->route('client.inventory.brands')->with('success', 'Merek berhasil ditambahkan.');
    }

    public function destroyBrand(Brand $brand)
    {
        if ($brand->company_id != $this->companyId()) abort(404);
        $brand->delete();
        return redirect()->route('client.inventory.brands')->with('success', 'Merek berhasil dihapus.');
    }

    // ========== PENYESUAIAN PERSEDIAAN ==========
    public function adjustments()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        $movements = InventoryMovement::whereHas('product', fn($q) => $q->where('company_id', $this->companyId()))
            ->where('reference_type', 'adjustment')
            ->with('product')
            ->orderBy('date', 'desc')
            ->paginate(20);
        return view('client.inventory.adjustments', compact('products', 'movements'));
    }

    public function storeAdjustment(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|not_in:0',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);
        $product = Product::findOrFail($validated['product_id']);
        if ($product->company_id != $this->companyId()) abort(404);

        $type = $validated['quantity'] > 0 ? 'in' : 'out';
        $qty = abs($validated['quantity']);

        InventoryMovement::create([
            'company_id' => $this->companyId(),
            'product_id' => $validated['product_id'],
            'type' => $type,
            'quantity' => $qty,
            'unit_cost' => $type === 'in' ? $product->buy_price : 0,
            'remaining_qty' => $type === 'in' ? $qty : 0,
            'date' => $validated['date'],
            'notes' => $validated['description'] ?? 'Penyesuaian stok',
            'reference_type' => 'adjustment',
            'created_by' => auth()->id(),
        ]);
        return redirect()->route('client.inventory.adjustments')->with('success', 'Penyesuaian persediaan berhasil dicatat.');
    }

    // ========== PERMINTAAN BARANG ==========
    public function requests()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        $movements = InventoryMovement::whereHas('product', fn($q) => $q->where('company_id', $this->companyId()))
            ->where('reference_type', 'request')
            ->with('product')
            ->orderBy('date', 'desc')
            ->paginate(20);
        return view('client.inventory.requests_list', compact('products', 'movements'));
    }

    public function storeRequest(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['product_id' => 'required|exists:products,id', 'quantity' => 'required|numeric|min:1', 'date' => 'required|date', 'description' => 'nullable|string|max:255']);
        $product = Product::findOrFail($validated['product_id']);
        if ($product->company_id != $this->companyId()) abort(404);
        InventoryMovement::create(['company_id' => $this->companyId(), 'product_id' => $validated['product_id'], 'type' => 'out', 'quantity' => $validated['quantity'], 'date' => $validated['date'], 'notes' => $validated['description'] ?? 'Permintaan barang', 'reference_type' => 'request', 'created_by' => auth()->id()]);
        return redirect()->route('client.inventory.requests')->with('success', 'Permintaan barang berhasil dicatat.');
    }

    // ========== PEMINDAHAN BARANG ==========
    public function transfers()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        $warehouses = Warehouse::where('company_id', $this->companyId())->get();
        $movements = InventoryMovement::whereHas('product', fn($q) => $q->where('company_id', $this->companyId()))
            ->where('reference_type', 'transfer')
            ->with('product')
            ->orderBy('date', 'desc')
            ->paginate(20);
        return view('client.inventory.transfers_list', compact('products', 'warehouses', 'movements'));
    }

    public function storeTransfer(\Illuminate\Http\Request $request)
    {
        $validated = $request->validate(['product_id' => 'required|exists:products,id', 'quantity' => 'required|numeric|min:1', 'date' => 'required|date', 'description' => 'nullable|string|max:255']);
        $product = Product::findOrFail($validated['product_id']);
        if ($product->company_id != $this->companyId()) abort(404);
        InventoryMovement::create(['company_id' => $this->companyId(), 'product_id' => $validated['product_id'], 'type' => 'out', 'quantity' => $validated['quantity'], 'date' => $validated['date'], 'notes' => $validated['description'] ?? 'Pemindahan barang', 'reference_type' => 'transfer', 'created_by' => auth()->id()]);
        InventoryMovement::create(['company_id' => $this->companyId(), 'product_id' => $validated['product_id'], 'type' => 'in', 'quantity' => $validated['quantity'], 'date' => $validated['date'], 'notes' => $validated['description'] ?? 'Penerimaan dari pemindahan', 'reference_type' => 'transfer', 'created_by' => auth()->id()]);
        return redirect()->route('client.inventory.transfers')->with('success', 'Pemindahan barang berhasil dicatat.');
    }

    // ========== STOK OPNAME ==========
    public function stockOpnameOrders()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        return view('client.inventory.stock_opname_orders', compact('products'));
    }

    public function stockOpnameResults()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        foreach ($products as $p) { $p->current_stock = $p->stock; }
        return view('client.inventory.stock_opname_results', compact('products'));
    }

    // ========== LAPORAN ==========
    public function orderFulfillments()
    {
        $invoices = \App\Models\SalesInvoice::where('company_id', $this->companyId())->whereIn('status', ['sent', 'paid'])->with('customer')->orderBy('invoice_date', 'desc')->paginate(20);
        return view('client.inventory.order_fulfillments', compact('invoices'));
    }

    public function itemsPerWarehouse()
    {
        $warehouses = Warehouse::where('company_id', $this->companyId())->get();
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        foreach ($products as $p) { $p->current_stock = $p->stock; }
        return view('client.inventory.items_per_warehouse', compact('warehouses', 'products'));
    }

    public function minimumStockItems()
    {
        $products = Product::where('company_id', $this->companyId())->where('type', 'goods')->orderBy('name')->get();
        foreach ($products as $p) { $p->current_stock = $p->stock; }
        $lowStock = $products->filter(fn($p) => $p->current_stock <= 5);
        return view('client.inventory.minimum_stock', compact('lowStock'));
    }
}
