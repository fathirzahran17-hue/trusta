<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('client.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('client.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        ProductCategory::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('client.inventory.categories')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(ProductCategory $category)
    {
        if ($category->company_id !== auth()->user()->company_id) abort(403);
        return view('client.categories.edit', compact('category'));
    }

    public function update(Request $request, ProductCategory $category)
    {
        if ($category->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($request->only('name', 'description'));

        return redirect()->route('client.inventory.categories')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(ProductCategory $category)
    {
        if ($category->company_id !== auth()->user()->company_id) abort(403);
        
        // Cek apakah kategori ini sudah dipakai oleh produk
        if ($category->products()->count() > 0) {
            return redirect()->route('client.inventory.categories')->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.');
        }
        $category->delete();
        return redirect()->route('client.inventory.categories')->with('success', 'Kategori berhasil dihapus.');
    }
}
