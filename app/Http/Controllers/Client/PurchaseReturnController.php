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
        $returnNumber = \App\Services\DocumentNumber::next(\App\Models\PurchaseReturn::class, 'return_number', 'PR-', (int) $companyId, null);
            
        $expenseAccounts = PurchaseInvoiceController::expenseAccounts((int) $companyId);

        return view('client.purchase_returns.create', compact('suppliers', 'invoices', 'products', 'returnNumber', 'expenseAccounts'));
    }

    public function store(Request $request)
    {
        // Baris bisa berupa barang/jasa (id) atau akun langsung ("a<id>"), sama seperti di Faktur Pembelian.
        $rowAccounts = [];
        $cleanProducts = [];
        foreach ((array) $request->input('products', []) as $i => $v) {
            if (is_string($v) && preg_match('/^a(\d+)$/', $v, $m)) {
                $rowAccounts[$i] = (int) $m[1];
                $cleanProducts[$i] = null;
            } else {
                $cleanProducts[$i] = $v === '' ? null : $v;
            }
        }
        $request->merge(['products' => $cleanProducts]);
        $allowed = PurchaseInvoiceController::expenseAccounts((int) auth()->user()->company_id)->pluck('id')->all();
        foreach ($cleanProducts as $i => $v) {
            if ($v === null && !isset($rowAccounts[$i])) {
                return back()->withInput()->with('error', 'Pilih barang/jasa atau akun biaya pada setiap baris.');
            }
            if (isset($rowAccounts[$i]) && !in_array($rowAccounts[$i], $allowed, true)) {
                return back()->withInput()->with('error', 'Akun pada salah satu baris tidak valid.');
            }
        }

        $request->validate([
            'return_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('purchase_returns', 'return_number')],
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_invoice_id' => 'nullable|exists:purchase_invoices,id',
            'return_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*' => 'nullable|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
            'prices' => 'required|array|min:1',
            'prices.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;
            $invoice = $request->purchase_invoice_id
                ? \App\Models\PurchaseInvoice::where('company_id', $companyId)->with('items')->find($request->purchase_invoice_id)
                : null;
            if ($request->purchase_invoice_id && !$invoice) abort(404);

            if ($invoice) {
                // Kunci baris: barang/jasa "p<id>", akun langsung "a<id>"
                $key = fn ($pid, $aid) => $pid ? 'p' . $pid : 'a' . $aid;
                $already = PurchaseReturnItem::whereHas('purchaseReturn', fn ($q) => $q->where('purchase_invoice_id', $invoice->id))->get()
                    ->groupBy(fn ($r) => $key($r->product_id, $r->account_id))->map(fn ($g) => (float) $g->sum('quantity'));
                $invoiced = $invoice->items->groupBy(fn ($r) => $key($r->product_id, $r->account_id ?? null))->map(fn ($g) => (float) $g->sum('quantity'));
                $asked = [];
                foreach ($request->products as $i => $pid) {
                    $k = $key($pid, $rowAccounts[$i] ?? null);
                    $asked[$k] = ($asked[$k] ?? 0) + (float) $request->quantities[$i];
                }
                foreach ($asked as $k => $q) {
                    $max = ($invoiced[$k] ?? 0) - (float) ($already[$k] ?? 0);
                    if ($q > $max + 0.0001) {
                        $name = $k[0] === 'p' ? \App\Models\Product::find((int) substr($k, 1))?->name : \App\Models\Account::find((int) substr($k, 1))?->name;
                        throw new \Exception('Jumlah retur ' . $name . ' (' . $q . ') melebihi sisa yang bisa diretur (' . max(0, $max) . ').');
                    }
                }
            }

            $subtotal = 0;
            $items = [];
            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                $price = floatval($request->prices[$index]);
                $total = $qty * $price;
                $subtotal += $total;
                $items[] = ['product_id' => $productId, 'account_id' => $rowAccounts[$index] ?? null, 'quantity' => $qty, 'unit_price' => $price, 'total' => $total,
                    'description' => ($request->row_descriptions[$index] ?? null) ?: (isset($rowAccounts[$index]) ? \App\Models\Account::find($rowAccounts[$index])?->name : null)];
            }

            $discPct = (float) ($invoice?->discount_percent ?? 0);
            $net = $subtotal * (1 - $discPct / 100);
            $taxPct = (float) ($invoice?->tax_percent ?? 0);
            $taxAmount = $net * $taxPct / 100;
            $grand = $net + $taxAmount;

            $purchaseReturn = PurchaseReturn::create([
                'company_id' => $companyId, 'supplier_id' => $request->supplier_id, 'purchase_invoice_id' => $invoice?->id,
                'return_number' => $request->return_number, 'return_date' => $request->return_date, 'status' => 'approved',
                'subtotal' => $subtotal, 'total' => $grand, 'notes' => $request->notes, 'created_by' => auth()->id(),
            ]);

            $inv = \App\Services\AccountResolver::inventory($companyId);
            $ap = \App\Services\AccountResolver::payable($companyId);
            if (!$ap) throw new \Exception('Akun Hutang belum disiapkan di Bagan Akun.');

            $journal = Journal::create([
                'company_id' => $companyId, 'ref_number' => $purchaseReturn->return_number, 'type' => 'general',
                'transaction_date' => $request->return_date, 'description' => 'Retur Pembelian ' . $purchaseReturn->return_number,
                'status' => 'posted', 'created_by' => auth()->id(),
            ]);

            // Dr Hutang / Cr Persediaan (barang) atau Biaya (jasa) / Cr Pajak Masukan
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $ap->id, 'debit' => $grand, 'credit' => 0,
                'description' => 'Pengurang Hutang ' . $purchaseReturn->return_number]);

            $groups = [];
            foreach ($items as $item) {
                $product = $item['product_id'] ? \App\Models\Product::find($item['product_id']) : null;
                PurchaseReturnItem::create(array_merge($item, ['purchase_return_id' => $purchaseReturn->id]));

                if (!$product) {
                    $accId = $item['account_id']; // baris akun langsung: kredit akun biaya/aset yang sama dengan faktur
                } elseif ($product->type === 'goods') {
                    if (!$inv) throw new \Exception('Akun Persediaan belum disiapkan di Bagan Akun.');
                    if ((float) $product->stock < $item['quantity']) {
                        throw new \Exception('Stok ' . $product->name . ' tidak cukup untuk diretur (tersedia ' . (float) $product->stock . ').');
                    }
                    \App\Models\InventoryMovement::create([
                        'company_id' => $companyId, 'product_id' => $product->id, 'date' => $request->return_date, 'type' => 'out',
                        'quantity' => $item['quantity'], 'reference_type' => 'purchase_return', 'reference_id' => $purchaseReturn->id,
                        'notes' => 'Retur Pembelian ' . $purchaseReturn->return_number, 'created_by' => auth()->id(),
                    ]);
                    $accId = $inv->id;
                } else {
                    $accId = $product->expense_account_id;
                    if (!$accId) throw new \Exception('Produk ' . $product->name . ' belum dipetakan ke Akun Biaya.');
                }
                $groups[$accId] = ($groups[$accId] ?? 0) + $item['total'] * (1 - $discPct / 100);
            }
            foreach ($groups as $accId => $amount) {
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $accId, 'debit' => 0, 'credit' => $amount,
                    'description' => 'Retur Pembelian ' . $purchaseReturn->return_number]);
            }
            if ($taxAmount > 0) {
                $taxAcc = \App\Services\AccountResolver::inputTax($companyId, $invoice->tax);
                if (!$taxAcc) throw new \Exception('Akun Pajak Masukan belum diatur.');
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $taxAcc->id, 'debit' => 0, 'credit' => $taxAmount,
                    'description' => 'Koreksi Pajak Masukan ' . $purchaseReturn->return_number]);
            }
            \App\Services\JournalGuard::assertBalanced($journal);
            $purchaseReturn->update(['journal_id' => $journal->id]);

            if ($invoice) {
                $reduce = min($grand, (float) $invoice->amount_due);
                $due = round((float) $invoice->amount_due - $reduce, 2);
                $invoice->update([
                    'amount_due' => $due,
                    'status' => $due <= 0.004 ? 'paid' : ((float) $invoice->amount_paid > 0 ? 'partial' : 'unpaid'),
                ]);
            }

            DB::commit();
            return redirect()->route('client.purchases.returns')->with('success', 'Retur Pembelian berhasil dicatat (jurnal, stok, dan sisa hutang faktur diperbarui).');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
