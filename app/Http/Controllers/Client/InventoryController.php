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
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    private function companyId() { return auth()->user()->company_id; }

    // ========== BARANG & JASA (Products) ==========
    public function index(Request $request)
    {
        $query = Product::where('company_id', $this->companyId())->with('category');
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        if ($request->type) $query->where('type', $request->type);
        $products = $query->orderBy('name')->paginate(20);
        return view('client.inventory.index', compact('products'));
    }

    public function show(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $movements = $product->inventoryMovements()->orderBy('date', 'desc')->orderBy('id', 'desc')->get();
        return view('client.inventory.show', compact('product', 'movements'));
    }

    public function create()
    {
        $categories = ProductCategory::where('company_id', $this->companyId())->get();
        $accounts = Account::where('company_id', $this->companyId())->orderBy('code')->get();
        $units = Unit::where('company_id', $this->companyId())->get();
        $brands = Brand::where('company_id', $this->companyId())->get();
        return view('client.inventory.create', compact('categories', 'accounts', 'units', 'brands'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:goods,service',
            'category_id' => 'nullable|exists:product_categories,id',
            'unit' => 'nullable|string|max:20',
            'sell_price' => 'required|numeric|min:0',
            'buy_price' => 'required|numeric|min:0',
            'revenue_account_id' => 'nullable|exists:accounts,id',
            'expense_account_id' => 'nullable|exists:accounts,id',
        ]);
        $validated['company_id'] = $this->companyId();
        $validated['is_active'] = true;
        Product::create($validated);
        return redirect()->route('client.inventory.index')->with('success', 'Barang/Jasa berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $categories = ProductCategory::where('company_id', $this->companyId())->get();
        $accounts = Account::where('company_id', $this->companyId())->orderBy('code')->get();
        $units = Unit::where('company_id', $this->companyId())->get();
        $brands = Brand::where('company_id', $this->companyId())->get();
        return view('client.inventory.edit', compact('product', 'categories', 'accounts', 'units', 'brands'));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $validated = $request->validate([
            'sku' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:goods,service',
            'category_id' => 'nullable|exists:product_categories,id',
            'unit' => 'nullable|string|max:20',
            'sell_price' => 'required|numeric|min:0',
            'buy_price' => 'required|numeric|min:0',
            'revenue_account_id' => 'nullable|exists:accounts,id',
            'expense_account_id' => 'nullable|exists:accounts,id',
            'is_active' => 'nullable',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $product->update($validated);
        return redirect()->route('client.inventory.index')->with('success', 'Barang/Jasa berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        if ($product->company_id != $this->companyId()) abort(404);
        $product->delete();
        return redirect()->route('client.inventory.index')->with('success', 'Barang/Jasa berhasil dihapus.');
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
