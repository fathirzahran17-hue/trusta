<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with(['supplier', 'purchaseInvoice'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('return_date', 'desc')
            ->paginate(20);
            
        return view('client.purchase_returns.index', compact('returns'));
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get();
        
        $invoices = PurchaseInvoice::where('company_id', $companyId)
            ->whereIn('status', ['unpaid', 'paid'])
            ->orderBy('invoice_date', 'desc')
            ->get();
            
        $products = Product::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        // Auto number
        $lastReturn = PurchaseReturn::where('company_id', $companyId)
            ->whereYear('return_date', date('Y'))
            ->whereMonth('return_date', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        $nextId = $lastReturn ? (intval(substr($lastReturn->return_number, -4)) + 1) : 1;
        $returnNumber = 'PR-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
        return view('client.purchase_returns.create', compact('suppliers', 'invoices', 'products', 'returnNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
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

            $purchaseReturn = PurchaseReturn::create([
                'company_id' => $companyId,
                'supplier_id' => $request->supplier_id,
                'purchase_invoice_id' => $request->purchase_invoice_id,
                'return_number' => $request->return_number,
                'return_date' => $request->return_date,
                'status' => 'approved', // Langsung approved
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($items as $item) {
                PurchaseReturnItem::create(array_merge($item, [
                    'purchase_return_id' => $purchaseReturn->id,
                ]));
            }

            // Jurnal Retur Pembelian
            // Debit Hutang Usaha, Credit Persediaan
            $payableAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Hutang Usaha%')->first();
            $inventoryAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Persediaan%')->first();
            
            if ($payableAccount && $inventoryAccount) {
                $journal = Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-PR-' . time(),
                    'type' => 'general',
                    'transaction_date' => $request->return_date,
                    'description' => 'Retur Pembelian ' . $purchaseReturn->return_number,
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);

                // Debit Hutang Usaha (Mengurangi hutang)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $payableAccount->id,
                    'description' => 'Retur Pembelian ' . $purchaseReturn->return_number,
                    'debit' => $subtotal,
                    'credit' => 0,
                ]);

                // Credit Persediaan (Mengurangi stok/aset)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $inventoryAccount->id,
                    'description' => 'Retur Pembelian ' . $purchaseReturn->return_number,
                    'debit' => 0,
                    'credit' => $subtotal,
                ]);

                $purchaseReturn->update(['journal_id' => $journal->id]);
            }

            DB::commit();
            return redirect()->route('client.purchases.returns')->with('success', 'Retur Pembelian berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
