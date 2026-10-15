<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SalesInvoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function index()
    {
        $returns = SalesReturn::with(['customer', 'salesInvoice'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('return_date', 'desc')
            ->paginate(20);
            
        return view('client.sales_returns.index', compact('returns'));
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        
        $invoices = SalesInvoice::where('company_id', $companyId)
            ->whereIn('status', ['unpaid', 'paid'])
            ->orderBy('invoice_date', 'desc')
            ->get();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        // Auto number
        $lastReturn = SalesReturn::where('company_id', $companyId)
            ->whereYear('return_date', date('Y'))
            ->whereMonth('return_date', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        $nextId = $lastReturn ? (intval(substr($lastReturn->return_number, -4)) + 1) : 1;
        $returnNumber = 'SR-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
        return view('client.sales_returns.create', compact('customers', 'invoices', 'products', 'returnNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sales_invoice_id' => 'nullable|exists:sales_invoices,id',
            'return_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
            'prices' => 'required|array|min:1',
            'prices.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;
            
            $subtotal = 0;
            $items = [];
            
            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                $price = floatval($request->prices[$index]);
                $total = $qty * $price;
                $subtotal += $total;
                
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total' => $total,
                    'description' => $request->row_descriptions[$index] ?? null,
                ];
            }

            $salesReturn = SalesReturn::create([
                'company_id' => $companyId,
                'customer_id' => $request->customer_id,
                'sales_invoice_id' => $request->sales_invoice_id,
                'return_number' => $request->return_number,
                'return_date' => $request->return_date,
                'status' => 'approved', // Langsung approved
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            $totalHpp = 0;

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                if ($product && $product->type == 'product') {
                    $totalHpp += ($product->buy_price * $item['quantity']);
                }

                SalesReturnItem::create(array_merge($item, [
                    'sales_return_id' => $salesReturn->id,
                ]));
            }

            // Jurnal Retur Penjualan
            $receivableAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Piutang Usaha%')->first();
            $revenueAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Pendapatan Penjualan%')->first();
            
            if ($receivableAccount && $revenueAccount) {
                $journal = Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-SR-' . time(),
                    'type' => 'general',
                    'transaction_date' => $request->return_date,
                    'description' => 'Retur Penjualan ' . $salesReturn->return_number,
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);

                // Debit Pendapatan Penjualan (Mengurangi pendapatan)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $revenueAccount->id,
                    'description' => 'Retur Penjualan ' . $salesReturn->return_number,
                    'debit' => $subtotal,
                    'credit' => 0,
                ]);

                // Credit Piutang Usaha (Mengurangi piutang)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $receivableAccount->id,
                    'description' => 'Retur Penjualan ' . $salesReturn->return_number,
                    'debit' => 0,
                    'credit' => $subtotal,
                ]);

                // Kembalikan HPP & Stok
                if ($totalHpp > 0) {
                    $hppAccount = Account::where('company_id', $companyId)->where('type', 'cost_of_sales')->first();
                    $inventoryAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Persediaan%')->first();
                    
                    if ($hppAccount && $inventoryAccount) {
                        // Debit Persediaan (Tambah Stok)
                        JournalDetail::create([
                            'journal_id' => $journal->id,
                            'account_id' => $inventoryAccount->id,
                            'description' => 'Stok Retur ' . $salesReturn->return_number,
                            'debit' => $totalHpp,
                            'credit' => 0,
                        ]);

                        // Credit HPP (Kurangi HPP)
                        JournalDetail::create([
                            'journal_id' => $journal->id,
                            'account_id' => $hppAccount->id,
                            'description' => 'Stok Retur ' . $salesReturn->return_number,
                            'debit' => 0,
                            'credit' => $totalHpp,
                        ]);
                    }
                }

                $salesReturn->update(['journal_id' => $journal->id]);
            }

            DB::commit();
            return redirect()->route('client.sales.returns')->with('success', 'Retur Penjualan berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
