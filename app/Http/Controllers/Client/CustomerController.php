<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $categories = $this->categoryOptions();
        $customers = Customer::where('company_id', auth()->user()->company_id)->with('category')
            ->when($request->filled('kategori'), fn ($q) => $q->where('customer_category_id', (int) $request->kategori))
            ->orderBy('name')->get();
        return view('client.customers.index', compact('customers', 'categories'));
    }

    private function categoryOptions()
    {
        return \App\Models\CustomerCategory::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
    }

    private function categoryRule(): array
    {
        return ['nullable', \Illuminate\Validation\Rule::exists('customer_categories', 'id')->where('company_id', auth()->user()->company_id)];
    }

    public function create()
    {
        return view('client.customers.create', ['categories' => $this->categoryOptions()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'npwp' => 'nullable|string|max:30',
            'default_term_days' => 'nullable|integer|min:0',
            'custom_attr_1' => 'nullable|string|max:255',
            'customer_category_id' => $this->categoryRule(),
            'is_active' => 'boolean',
        ]);

        Customer::create([
            'company_id' => auth()->user()->company_id,
            'name' => $request->name,
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'npwp' => $request->npwp,
            'default_term_days' => $request->default_term_days ?? 30,
            'customer_category_id' => $request->customer_category_id ?: null,
            'custom_attr_1' => $request->custom_attr_1,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
        ]);

        return redirect()->route('client.customers.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Customer $customer)
    {
        if ($customer->company_id !== auth()->user()->company_id) abort(403);
        return view('client.customers.edit', ['customer' => $customer, 'categories' => $this->categoryOptions()]);
    }

    public function update(Request $request, Customer $customer)
    {
        if ($customer->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'npwp' => 'nullable|string|max:30',
            'default_term_days' => 'nullable|integer|min:0',
            'custom_attr_1' => 'nullable|string|max:255',
            'customer_category_id' => $this->categoryRule(),
            'is_active' => 'boolean',
        ]);

        $customer->update([
            'name' => $request->name,
            'company_name' => $request->company_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'npwp' => $request->npwp,
            'default_term_days' => $request->default_term_days ?? 30,
            'customer_category_id' => $request->customer_category_id ?: null,
            'custom_attr_1' => $request->custom_attr_1,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('client.customers.index')->with('success', 'Data Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->company_id !== auth()->user()->company_id) abort(403);
        
        // Proteksi: jangan hapus customer yang sudah punya invoice
        if ($customer->salesInvoices()->count() > 0) {
            return back()->with('error', 'Pelanggan tidak bisa dihapus karena sudah memiliki ' . $customer->salesInvoices()->count() . ' faktur penjualan.');
        }
        
        $customer->delete();
        return redirect()->route('client.customers.index')->with('success', 'Pelanggan berhasil dihapus.');
    }

    public function show(Request $request, Customer $customer)
    {
        if ($customer->company_id !== auth()->user()->company_id) abort(403);

        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->format('Y-m-d'));

        $query = \App\Models\SalesInvoice::where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->orderBy('invoice_date', 'asc');

        $invoices = $query->get();
        $totalPiutang = $customer->saldo;

        // Export Excel (Maatwebsite)
        if ($request->has('export') && $request->export == 'excel') {
            $data = compact('customer', 'invoices', 'startDate', 'endDate', 'totalPiutang');
            return \Maatwebsite\Excel\Facades\Excel::download(new class($data) implements \Maatwebsite\Excel\Concerns\FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): \Illuminate\Contracts\View\View { return view('client.customers.exports.excel', $this->data); }
            }, 'Buku_Piutang_' . $customer->name . '.xlsx');
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
                    $invoice->total,
                    $invoice->amount_due,
                ];
            }
            $rows[] = ['', '', '', '', 'Total Piutang', $totalPiutang];
            
            return \App\Helpers\CsvExporter::download(
                'Buku_Piutang_' . $customer->name . '.csv',
                $headers,
                $rows
            );
        }

        if ($request->has('export') && $request->export == 'pdf') {
            return view('client.customers.exports.pdf', compact('customer', 'invoices', 'startDate', 'endDate', 'totalPiutang'));
        }

        return view('client.customers.show', compact('customer', 'invoices', 'startDate', 'endDate', 'totalPiutang'));
    }

    public function categories()
    {
        $categories = \App\Models\CustomerCategory::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        return view('client.customers.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:255']);
        \App\Models\CustomerCategory::create(['company_id' => auth()->user()->company_id, 'name' => $request->name, 'description' => $request->description]);
        return redirect()->route('client.customers.categories')->with('success', 'Kategori pelanggan berhasil ditambahkan.');
    }

    public function destroyCategory(\App\Models\CustomerCategory $customerCategory)
    {
        if ($customerCategory->company_id != auth()->user()->company_id) abort(404);
        $customerCategory->delete();
        return redirect()->route('client.customers.categories')->with('success', 'Kategori pelanggan berhasil dihapus.');
    }
}
