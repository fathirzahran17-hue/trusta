<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('client.suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('client.suppliers.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'tax_address' => 'nullable|string',
            'npwp' => 'nullable|string|max:50',
            'default_term_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        Supplier::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'tax_address' => $request->tax_address,
            'npwp' => $request->npwp,
            'default_term_days' => $request->default_term_days ?? 30,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return redirect()->route('client.suppliers.index')->with('success', 'Pemasok berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier)
    {
        if ($supplier->company_id !== auth()->user()->company_id) abort(403);
        return view('client.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'tax_address' => 'nullable|string',
            'npwp' => 'nullable|string|max:50',
            'default_term_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $supplier->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'tax_address' => $request->tax_address,
            'npwp' => $request->npwp,
            'default_term_days' => $request->default_term_days ?? 30,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('client.suppliers.index')->with('success', 'Data Pemasok berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->company_id !== auth()->user()->company_id) abort(403);
        
        // Proteksi: jangan hapus supplier yang sudah punya invoice
        if ($supplier->purchaseInvoices()->count() > 0) {
            return back()->with('error', 'Pemasok tidak bisa dihapus karena sudah memiliki ' . $supplier->purchaseInvoices()->count() . ' faktur pembelian.');
        }
        
        $supplier->delete();
        return redirect()->route('client.suppliers.index')->with('success', 'Pemasok berhasil dihapus.');
    }

    public function show(Request $request, Supplier $supplier)
    {
        if ($supplier->company_id !== auth()->user()->company_id) abort(403);

        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->format('Y-m-d'));

        $query = \App\Models\PurchaseInvoice::where('supplier_id', $supplier->id)
            ->where('status', '!=', 'void')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('invoice_date', 'asc');

        $invoices = $query->get();
        $totalHutang = $supplier->saldo;

        // Export Excel (Maatwebsite)
        if ($request->has('export') && $request->export == 'excel') {
            $data = compact('supplier', 'invoices', 'startDate', 'endDate', 'totalHutang');
            return \Maatwebsite\Excel\Facades\Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): \Illuminate\Contracts\View\View { return view('client.suppliers.exports.excel', $this->data); }
            }, 'Buku_Hutang_' . $supplier->name . '.xlsx');
        }

        // Export CSV (Lokal / Alternatif)
        if ($request->has('export') && $request->export == 'csv') {
            $headers = ['Tanggal', 'No. Invoice', 'Jatuh Tempo', 'Status', 'Total Tagihan', 'Sisa Tagihan'];
            $rows = [];
            foreach ($invoices as $invoice) {
                $rows[] = [
                    $invoice->invoice_date,
                    $invoice->invoice_number,
                    $invoice->due_date,
                    $invoice->status,
                    $invoice->grand_total,
                    $invoice->amount_due,
                ];
            }
            $rows[] = ['', '', '', '', 'Total Hutang', $totalHutang];
            
            return \App\Helpers\CsvExporter::download(
                'Buku_Hutang_' . $supplier->name . '.csv',
                $headers,
                $rows
            );
        }

        if ($request->has('export') && $request->export == 'pdf') {
            return view('client.suppliers.exports.pdf', compact('supplier', 'invoices', 'startDate', 'endDate', 'totalHutang'));
        }

        return view('client.suppliers.show', compact('supplier', 'invoices', 'startDate', 'endDate', 'totalHutang'));
    }

    public function categories()
    {
        $categories = \App\Models\SupplierCategory::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('client.suppliers.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:255']);
        \App\Models\SupplierCategory::create(['company_id' => auth()->user()->company_id, 'name' => $request->name, 'description' => $request->description]);
        return redirect()->route('client.suppliers.categories')->with('success', 'Kategori pemasok berhasil ditambahkan.');
    }

    public function destroyCategory(\App\Models\SupplierCategory $supplierCategory)
    {
        if ($supplierCategory->company_id != auth()->user()->company_id) abort(404);
        $supplierCategory->delete();
        return redirect()->route('client.suppliers.categories')->with('success', 'Kategori pemasok berhasil dihapus.');
    }
}
