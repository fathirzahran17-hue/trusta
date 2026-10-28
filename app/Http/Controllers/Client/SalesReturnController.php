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
        $returnNumber = \App\Services\DocumentNumber::next(\App\Models\SalesReturn::class, 'return_number', 'SR-', (int) $companyId, null);
            
        return view('client.sales_returns.create', compact('customers', 'invoices', 'products', 'returnNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'return_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('sales_returns', 'return_number')],
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
            $invoice = $request->sales_invoice_id
                ? SalesInvoice::where('company_id', $companyId)->with('items')->find($request->sales_invoice_id)
                : null;
            if ($request->sales_invoice_id && !$invoice) abort(404);

            // Jumlah retur tidak boleh melebihi jumlah pada faktur (dikurangi retur sebelumnya)
            if ($invoice) {
                $already = SalesReturnItem::whereHas('salesReturn', fn ($q) => $q->where('sales_invoice_id', $invoice->id))
                    ->selectRaw('product_id, SUM(quantity) as q')->groupBy('product_id')->pluck('q', 'product_id');
                $invoiced = $invoice->items->groupBy('product_id')->map(fn ($g) => (float) $g->sum('quantity'));
                $asked = [];
                foreach ($request->products as $i => $pid) $asked[$pid] = ($asked[$pid] ?? 0) + (float) $request->quantities[$i];
                foreach ($asked as $pid => $q) {
                    $max = ($invoiced[$pid] ?? 0) - (float) ($already[$pid] ?? 0);
                    if ($q > $max + 0.0001) {
                        throw new \Exception('Jumlah retur ' . Product::find($pid)?->name . ' (' . $q . ') melebihi sisa yang bisa diretur (' . max(0, $max) . ').');
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
                $items[] = [
                    'product_id' => $productId, 'quantity' => $qty, 'unit_price' => $price, 'total' => $total,
                    'description' => $request->row_descriptions[$index] ?? null,
                ];
            }

            // Retur mengikuti diskon & pajak pada faktur asal
            $discPct = (float) ($invoice?->discount_percent ?? 0);
            $net = $subtotal * (1 - $discPct / 100);
            $taxPct = (float) ($invoice?->tax_percent ?? 0);
            $taxAmount = $net * $taxPct / 100;
            $grand = $net + $taxAmount;

            $salesReturn = SalesReturn::create([
                'company_id' => $companyId, 'customer_id' => $request->customer_id, 'sales_invoice_id' => $invoice?->id,
                'return_number' => $request->return_number, 'return_date' => $request->return_date, 'status' => 'approved',
                'subtotal' => $subtotal, 'total' => $grand, 'notes' => $request->notes, 'created_by' => auth()->id(),
            ]);

            $totalHpp = 0;
            $hppByInv = [];
            foreach ($items as $item) {
                $product = Product::find($item['product_id']);
                SalesReturnItem::create(array_merge($item, ['sales_return_id' => $salesReturn->id]));

                // Barang kembali ke stok, nilai mengikuti harga pokok rata-rata
                if ($product && $product->type === 'goods') {
                    $cost = (float) $product->buy_price;
                    $totalHpp += $cost * $item['quantity'];
                    $ia = optional(\App\Services\AccountResolver::inventoryFor($product, (int) $companyId))->id;
                    $hppByInv[$ia] = ($hppByInv[$ia] ?? 0) + $cost * $item['quantity'];
                    \App\Models\InventoryMovement::create([
                        'company_id' => $companyId, 'product_id' => $product->id, 'date' => $request->return_date, 'type' => 'in',
                        'quantity' => $item['quantity'], 'unit_cost' => $cost, 'remaining_qty' => $item['quantity'],
                        'reference_type' => 'sales_return', 'reference_id' => $salesReturn->id,
                        'notes' => 'Retur Penjualan ' . $salesReturn->return_number, 'created_by' => auth()->id(),
                    ]);
                }
            }

            $ar = \App\Services\AccountResolver::receivable($companyId);
            if (!$ar) throw new \Exception('Akun Piutang belum disiapkan di Bagan Akun.');

            $journal = Journal::create([
                'company_id' => $companyId, 'ref_number' => $salesReturn->return_number, 'type' => 'general',
                'transaction_date' => $request->return_date, 'description' => 'Retur Penjualan ' . $salesReturn->return_number,
                'status' => 'posted', 'created_by' => auth()->id(),
            ]);

            // Dr Retur Penjualan (kontra pendapatan) / Dr Pajak Keluaran / Cr Piutang
            $contra = \App\Services\AccountResolver::salesReturn($companyId);
            $groups = [];
            foreach ($items as $item) {
                $prod = Product::find($item['product_id']);
                $accId = $contra?->id ?? $prod?->revenue_account_id;
                if (!$accId) throw new \Exception('Akun Retur Penjualan / Pendapatan belum diatur untuk ' . ($prod?->name ?? 'produk') . '.');
                $groups[$accId] = ($groups[$accId] ?? 0) + $item['total'] * (1 - $discPct / 100);
            }
            foreach ($groups as $accId => $amount) {
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $accId, 'debit' => $amount, 'credit' => 0,
                    'description' => 'Retur Penjualan ' . $salesReturn->return_number]);
            }
            if ($taxAmount > 0) {
                $taxAcc = \App\Services\AccountResolver::outputTax($companyId, $invoice->tax);
                if (!$taxAcc) throw new \Exception('Akun Pajak Keluaran belum diatur.');
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $taxAcc->id, 'debit' => $taxAmount, 'credit' => 0,
                    'description' => 'Koreksi Pajak Keluaran ' . $salesReturn->return_number]);
            }
            JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $ar->id, 'debit' => 0, 'credit' => $grand,
                'description' => 'Pengurang Piutang ' . $salesReturn->return_number]);

            // Dr Persediaan / Cr HPP untuk barang yang kembali
            if ($totalHpp > 0) {
                $hpp = \App\Services\AccountResolver::cogs($companyId);
                if (!$hpp || array_key_exists('', $hppByInv)) throw new \Exception('Akun HPP / Persediaan belum disiapkan di Bagan Akun.');
                foreach ($hppByInv as $ia => $amt) {
                    JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $ia, 'debit' => round($amt, 2), 'credit' => 0,
                        'description' => 'Stok Retur ' . $salesReturn->return_number]);
                }
                JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $hpp->id, 'debit' => 0, 'credit' => array_sum(array_map(fn ($v) => round($v, 2), $hppByInv)),
                    'description' => 'Koreksi HPP ' . $salesReturn->return_number]);
            }
            \App\Services\JournalGuard::assertBalanced($journal);
            $salesReturn->update(['journal_id' => $journal->id]);

            // Kurangi sisa tagihan faktur (maksimal sebesar sisa)
            if ($invoice) {
                $reduce = min($grand, (float) $invoice->amount_due);
                $due = round((float) $invoice->amount_due - $reduce, 2);
                $invoice->update([
                    'amount_due' => $due,
                    'status' => $due <= 0.004 ? 'paid' : ((float) $invoice->amount_paid > 0 ? 'partial' : 'unpaid'),
                ]);
            }

            DB::commit();
            return redirect()->route('client.sales.returns')->with('success', 'Retur Penjualan berhasil dicatat (jurnal, stok, dan sisa tagihan faktur diperbarui).');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
