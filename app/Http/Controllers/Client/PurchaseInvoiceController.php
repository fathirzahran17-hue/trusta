<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        $invoices = PurchaseInvoice::with(['supplier', 'tax'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('invoice_date', 'desc')
            ->get();
            
        return view('client.purchase_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $suppliers = Supplier::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        // Pembelian bisa barang dagangan atau jasa, kita ambil semua yang aktif
        $products = Product::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
            
        $taxes = Tax::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();
            
        // Generate auto invoice number untuk internal kita (meskipun ada nomor invoice dari supplier)
        $invoiceNumber = \App\Services\DocumentNumber::next(\App\Models\PurchaseInvoice::class, 'invoice_number', 'PI-', (int) auth()->user()->company_id, null);
            
        $cid = auth()->user()->company_id;
        $poIds = array_map('intval', \Illuminate\Support\Arr::wrap(request('po_id')));
        $rcIds = array_map('intval', \Illuminate\Support\Arr::wrap(request('item_receipt_id')));
        $prefill = \App\Services\DocumentLinker::prefillFromSources('purchase', $poIds, $rcIds, (int) $cid);
        $checked = array_merge(array_map(fn ($i) => "po_id=$i", $poIds), array_map(fn ($i) => "item_receipt_id=$i", $rcIds));

        $receipts = \App\Models\ItemReceipt::with('supplier')->where('company_id', $cid)
            ->where('status', 'received')->orderByDesc('id')->limit(100)->get()
            ->map(fn ($r) => ['id' => $r->id, 'param' => 'item_receipt_id', 'group' => 'Penerimaan Barang', 'party_id' => $r->supplier_id, 'text' => $r->receipt_number . ' — ' . optional($r->supplier)->name])->all();

        $sourceOrders = \App\Models\PurchaseOrder::with('supplier')->where('company_id', $cid)
            ->whereIn('status', ['sent', 'received'])->orderByDesc('id')->get()
            ->map(fn ($o) => ['id' => $o->id, 'param' => 'po_id', 'group' => 'Pesanan Pembelian (PO)', 'party_id' => $o->supplier_id, 'text' => $o->po_number . ' — ' . optional($o->supplier)->name])->all();

        $expenseAccounts = self::expenseAccounts((int) $cid);
        $mode = request('mode') === 'biaya' ? 'biaya' : 'umum';

        return view('client.purchase_invoices.create', compact('suppliers', 'products', 'taxes', 'invoiceNumber', 'prefill', 'sourceOrders', 'receipts', 'checked', 'expenseAccounts', 'mode'));
    }

    /** Akun yang boleh dipilih langsung di baris faktur: biaya, HPP, biaya lain, dan aset (bukan kas/bank). */
    public static function expenseAccounts(int $companyId)
    {
        return Account::where('company_id', $companyId)->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('type', ['expense', 'cost_of_sales', 'other_expense'])->orWhere(fn ($w) => $w->where('type', 'asset')->where('is_cash_bank', false)))
            ->orderBy('code')->get()
            ->reject(fn ($a) => $a->is_header || preg_match('/^(piutang|hutang|utang)\b/i', trim($a->name)) === 1)
            ->sortBy(fn ($a) => ($a->type === 'asset' ? '1' : '0') . $a->code)->values(); // biaya dulu, aset di bawahnya
    }

    public function store(Request $request)
    {
        // Baris bisa berupa produk/jasa (id) atau akun langsung ("a<id>", mis. akun biaya)
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
        foreach ($cleanProducts as $i => $v) {
            if ($v === null && !isset($rowAccounts[$i])) {
                return back()->withInput()->with('error', 'Pilih produk/jasa atau akun biaya pada setiap baris.');
            }
        }
        $allowed = self::expenseAccounts((int) auth()->user()->company_id)->pluck('id')->all();
        foreach ($rowAccounts as $accId) {
            if (!in_array($accId, $allowed, true)) {
                return back()->withInput()->with('error', 'Akun pada salah satu baris tidak valid.');
            }
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'po_id' => 'nullable|integer',
            'supplier_invoice_no' => 'nullable|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'products' => 'required|array|min:1',
            'products.*' => 'nullable|exists:products,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|numeric|min:0.01',
            'prices' => 'required|array|min:1',
            'prices.*' => 'required|numeric|min:0',
            'discounts' => 'nullable|array',
            'discounts.*' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'tax_id' => 'nullable|exists:taxes,id',
            'notes' => 'nullable|string',
        ]);

        try {
            [$rowOrders, $orders] = \App\Services\InvoiceSources::resolve('purchase', $request, (int) auth()->user()->company_id, (int) $request->supplier_id);
            $rowDocs = \App\Services\InvoiceSources::resolveDocs('purchase', $request, (int) auth()->user()->company_id, (int) $request->supplier_id, $rowOrders);
            \App\Services\ServiceLines::assertPriced($request->products, $request->prices);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $po = $orders->first();

        try {
            DB::beginTransaction();

            $subtotal = 0;
            $items = [];
            
            // 1. Calculate Items & Subtotal (with line discount)
            foreach ($request->products as $index => $productId) {
                $qty = floatval($request->quantities[$index]);
                $price = floatval($request->prices[$index]);
                $lineDiscPct = floatval($request->discounts[$index] ?? 0);
                $lineTotal = $qty * $price;
                $lineDiscAmt = $lineTotal * ($lineDiscPct / 100);
                $total = $lineTotal - $lineDiscAmt;
                $subtotal += $total;
                
                $items[] = [
                    'po_id' => $rowOrders[$index] ?? null,
                    'item_receipt_id' => $rowDocs[$index] ?? null,
                    'product_id' => $productId,
                    'account_id' => $rowAccounts[$index] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_percent' => $lineDiscPct,
                    'total' => $total,
                    'description' => ($request->row_descriptions[$index] ?? null) ?: (isset($rowAccounts[$index]) ? Account::find($rowAccounts[$index])?->name : null),
                ];
            }

            // 2. Calculate Overall Discount
            $overallDiscPct = floatval($request->discount_percent ?? 0);
            $overallDiscAmt = $subtotal * ($overallDiscPct / 100);
            $afterDiscount = $subtotal - $overallDiscAmt;

            // 3. Calculate Tax (on after-discount amount)
            $taxAmount = 0;
            $taxPercent = 0;
            $taxModel = null;
            
            if ($request->filled('tax_id')) {
                $taxModel = Tax::find($request->tax_id);
                if ($taxModel) {
                    $taxPercent = $taxModel->rate;
                    $taxAmount = $afterDiscount * ($taxPercent / 100);
                }
            }

            $grandTotal = $afterDiscount + $taxAmount;

            // Generate Invoice Number Internal
            $invoiceNumber = \App\Services\DocumentNumber::next(\App\Models\PurchaseInvoice::class, 'invoice_number', 'PI-', (int) auth()->user()->company_id, $request->invoice_date);

            // 3. JURNAL OTOMATIS (Automated Bookkeeping)
            // Cari Akun Hutang Usaha (Account Payable)
            $apAccount = \App\Services\AccountResolver::payable(auth()->user()->company_id);
                
            if (!$apAccount) {
                throw new \Exception('Akun Hutang Usaha (Account Payable) belum disiapkan di Master Data COA.');
            }

            // Buat Induk Jurnal
            $journal = \App\Models\Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $invoiceNumber,
                'type' => 'auto_purchase',
                'transaction_date' => $request->invoice_date,
                'description' => 'Pembelian dari ' . Supplier::find($request->supplier_id)->name . ($request->supplier_invoice_no ? ' (Ref: ' . $request->supplier_invoice_no . ')' : ''),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            // Baris 1: Biaya / Harga Pokok Pembelian (Debit) per Produk
            $expenseGroups = [];
            foreach ($items as $item) {
                // Baris akun langsung (mis. Biaya Listrik): debit ke akun yang dipilih
                if (!$item['product_id']) {
                    $expenseGroups[$item['account_id']] = ($expenseGroups[$item['account_id']] ?? 0) + $item['total'] * (1 - $overallDiscPct / 100);
                    continue;
                }
                $prod = Product::find($item['product_id']);

                // Barang dagang -> Persediaan (stok bertambah lewat Penerimaan Barang);
                // jasa / non-stok -> akun biaya yang dipetakan pada produk.
                if ($prod->type === 'goods') {
                    $invAcc = \App\Services\AccountResolver::inventory(auth()->user()->company_id);
                    if (!$invAcc) {
                        throw new \Exception('Akun Persediaan belum disiapkan di Master Data COA.');
                    }
                    $targetAccountId = $invAcc->id;
                } else {
                    if (!$prod->expense_account_id) {
                        throw new \Exception('Produk ' . $prod->name . ' belum dipetakan ke Akun Biaya (Expense Account).');
                    }
                    $targetAccountId = $prod->expense_account_id;
                }

                if (!isset($expenseGroups[$targetAccountId])) {
                    $expenseGroups[$targetAccountId] = 0;
                }
                // Diskon keseluruhan dibagi proporsional agar jurnal seimbang
                $expenseGroups[$targetAccountId] += $item['total'] * (1 - $overallDiscPct / 100);
            }
            
            foreach ($expenseGroups as $accountId => $amount) {
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Biaya Pembelian ' . $invoiceNumber,
                ]);
            }

            // Baris 2: Pajak Pembelian (Debit) jika ada
            if ($taxAmount > 0) {
                $taxAcc = \App\Services\AccountResolver::inputTax(auth()->user()->company_id, $taxModel);
                if (!$taxAcc) {
                    throw new \Exception('Akun Pajak Masukan belum diatur untuk pajak "' . $taxModel->name . '". Atur di menu Pajak.');
                }
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $taxAcc->id,
                    'debit' => $taxAmount,
                    'credit' => 0,
                    'description' => 'Pajak Masukan ' . $taxModel->name,
                ]);
            }

            // Baris 3: Hutang Usaha (Kredit) = Grand Total
            \App\Models\JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $apAccount->id,
                'debit' => 0,
                'credit' => $grandTotal,
                'description' => 'Hutang Pembelian ' . $invoiceNumber,
            ]);

            // 4. Create Invoice
            $invoice = PurchaseInvoice::create([
                'company_id' => auth()->user()->company_id,
                'invoice_number' => $invoiceNumber,
                'supplier_invoice_no' => $request->supplier_invoice_no,
                'supplier_id' => $request->supplier_id,
                'po_id' => $po?->id,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_percent' => $overallDiscPct,
                'discount_amount' => $overallDiscAmt,
                'tax_id' => $request->tax_id,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total' => $grandTotal,
                'amount_due' => $grandTotal,
                'notes' => $request->notes,
                'journal_id' => $journal->id,
                'created_by' => auth()->id(),
            ]);

            // 5. Create Invoice Items
            foreach ($items as $item) {
                $invoice->items()->create($item);
                // Note: Mutasi stok fisik (InventoryMovement) sekarang HANYA ditangani oleh Penerimaan Barang (Item Receipt).
            }

            \App\Services\JournalGuard::assertBalanced($journal);

            DB::commit();
            \App\Services\InvoiceSources::syncFor($invoice, 'purchase');
            return redirect()->route('client.purchase-invoices.index')->with('success', 'Faktur Pembelian berhasil dicatat dan di-Jurnal secara otomatis.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal mencatat Invoice: ' . $e->getMessage());
        }
    }

    public function show(PurchaseInvoice $purchase_invoice)
    {
        if ($purchase_invoice->company_id !== auth()->user()->company_id) abort(403);
        
        $purchase_invoice->load(['supplier', 'items.product', 'tax', 'journal']);
        return view('client.purchase_invoices.show', compact('purchase_invoice'));
    }

}
