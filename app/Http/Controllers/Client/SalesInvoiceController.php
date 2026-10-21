<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesInvoiceController extends Controller
{
    public function index()
    {
        $invoices = SalesInvoice::with(['customer', 'tax'])
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('invoice_date', 'desc')
            ->get();
            
        return view('client.sales_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $customers = Customer::where('company_id', auth()->user()->company_id)->orderBy('name')->get();
        $products = Product::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->where('type', '!=', 'asset') // asalkan bukan aset tetap
            ->orderBy('name')
            ->get();
            
        $taxes = Tax::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();
            
        // Generate auto invoice number
        $invoiceNumber = \App\Services\DocumentNumber::next(\App\Models\SalesInvoice::class, 'invoice_number', 'INV-', (int) auth()->user()->company_id, null);
            
        $cid = auth()->user()->company_id;
        $prefill = request('delivery_order_id')
            ? \App\Services\DocumentLinker::prefillFromDelivery(request('delivery_order_id'), $cid)
            : \App\Services\DocumentLinker::prefillFromSalesOrder(request('sales_order_id'), $cid);
        $prefill['party_id'] ??= optional($prefill['order'])->customer_id;
        $prefill['source'] ??= null;
        $selectedSource = request('delivery_order_id') ? 'delivery_order_id=' . (int) request('delivery_order_id') : ($prefill['order'] ? 'sales_order_id=' . $prefill['order']->id : null);

        $deliveries = \App\Models\DeliveryOrder::with('customer')->where('company_id', $cid)
            ->whereIn('status', ['shipped', 'delivered'])->orderByDesc('id')->limit(100)->get()
            ->map(fn ($d) => ['id' => $d->id, 'param' => 'delivery_order_id', 'group' => 'Dari Pengiriman (DO)', 'text' => $d->delivery_number . ' — ' . optional($d->customer)->name])->all();

        $sourceOrders = \App\Models\SalesOrder::with('customer')->where('company_id', auth()->user()->company_id)
            ->whereIn('status', ['draft', 'open'])->orderByDesc('id')->get()
            ->map(fn ($o) => ['id' => $o->id, 'param' => 'sales_order_id', 'group' => 'Dari Pesanan (SO)', 'text' => $o->order_number . ' — ' . optional($o->customer)->name])->all();
        if ($prefill['order'] && !collect($sourceOrders)->contains('id', $prefill['order']->id)) {
            $sourceOrders[] = ['id' => $prefill['order']->id, 'param' => 'sales_order_id', 'group' => 'Dari Pesanan (SO)', 'text' => $prefill['order']->order_number . ' — ' . optional($prefill['order']->customer)->name];
        }

        return view('client.sales_invoices.create', compact('customers', 'products', 'taxes', 'invoiceNumber', 'prefill', 'sourceOrders', 'deliveries', 'selectedSource'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sales_order_id' => 'nullable|integer',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'products' => 'required|array|min:1',
            'products.*' => 'required|exists:products,id',
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

        $so = $request->sales_order_id ? \App\Models\SalesOrder::where('company_id', auth()->user()->company_id)->find($request->sales_order_id) : null;
        if ($request->sales_order_id && !$so) {
            return back()->withInput()->with('error', 'Pesanan Penjualan tidak ditemukan.');
        }
        if ($so) {
            try {
                \App\Services\OrderProgress::assertWithinRemaining(\App\Services\OrderProgress::forSalesOrder($so), 'invoiced', \App\Services\OrderProgress::requested($request->products, $request->quantities), 'faktur');
            } catch (\DomainException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }

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
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'discount_percent' => $lineDiscPct,
                    'total' => $total,
                    'description' => $request->row_descriptions[$index] ?? null,
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

            // Generate Invoice Number
            $invoiceNumber = \App\Services\DocumentNumber::next(\App\Models\SalesInvoice::class, 'invoice_number', 'INV-', (int) auth()->user()->company_id, $request->invoice_date);

            // 3. JURNAL OTOMATIS (Automated Bookkeeping)
            // Cari Akun Piutang (Account Receivable)
            $arAccount = \App\Services\AccountResolver::receivable(auth()->user()->company_id);
                
            if (!$arAccount) {
                throw new \Exception('Akun Piutang Usaha (Account Receivable) belum disiapkan di Master Data COA.');
            }

            // Buat Induk Jurnal
            $journal = \App\Models\Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $invoiceNumber,
                'type' => 'auto_sales',
                'transaction_date' => $request->invoice_date,
                'description' => 'Penjualan ke ' . Customer::find($request->customer_id)->name,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            // Baris 1: Piutang (Debit) = Grand Total
            \App\Models\JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $arAccount->id,
                'debit' => $grandTotal,
                'credit' => 0,
                'description' => 'Piutang Penjualan ' . $invoiceNumber,
            ]);

            // Baris 2: Pendapatan (Kredit) per Produk
            $revenueGroups = [];
            foreach ($items as $item) {
                $prod = Product::find($item['product_id']);
                // Akun pendapatan: dari produk; bila kosong pakai akun standar (Pendapatan Jasa untuk jasa, Pendapatan Penjualan untuk barang)
                $revId = $prod->revenue_account_id;
                if (!$revId) {
                    $cid = (int) auth()->user()->company_id;
                    $fallback = $prod->type === 'service' ? \App\Services\AccountResolver::serviceRevenue($cid) : \App\Services\AccountResolver::salesRevenue($cid);
                    $revId = optional($fallback)->id;
                }
                if (!$revId) {
                    throw new \Exception('Akun pendapatan untuk "' . $prod->name . '" belum ada. Buka menu Barang/Jasa lalu pilih Akun Pendapatan.');
                }

                if (!isset($revenueGroups[$revId])) {
                    $revenueGroups[$revId] = 0;
                }
                // Diskon keseluruhan dibagi proporsional ke pendapatan agar jurnal seimbang
                $revenueGroups[$revId] += $item['total'] * (1 - $overallDiscPct / 100);
            }
            
            foreach ($revenueGroups as $accountId => $amount) {
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Pendapatan Penjualan ' . $invoiceNumber,
                ]);
            }

            // Baris 3: Pajak Penjualan (Kredit) jika ada
            if ($taxAmount > 0) {
                $taxAcc = \App\Services\AccountResolver::outputTax(auth()->user()->company_id, $taxModel);
                if (!$taxAcc) {
                    throw new \Exception('Akun Pajak Keluaran belum diatur untuk pajak "' . $taxModel->name . '". Atur di menu Pajak.');
                }
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $taxAcc->id,
                    'debit' => 0,
                    'credit' => $taxAmount,
                    'description' => 'Pajak Keluaran ' . $taxModel->name,
                ]);
            }

            // 4. Create Invoice
            $invoice = SalesInvoice::create([
                'company_id' => auth()->user()->company_id,
                'invoice_number' => $invoiceNumber,
                'customer_id' => $request->customer_id,
                'sales_order_id' => $so?->id,
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
            $totalCogs = 0;
            foreach ($items as $item) {
                $invoice->items()->create($item);

                $prod = Product::find($item['product_id']);
                if ($prod->type === 'goods') {
                    $company = auth()->user()->company;
                    $method = $company->inventory_costing_method ?? 'average';
                    $qtyToCost = $item['quantity'];
                    $itemCogs = 0;

                    if ($method === 'fifo') {
                        // FIFO Logic
                        $batches = \App\Models\InventoryMovement::where('product_id', $prod->id)
                            ->where('type', 'in')
                            ->where('remaining_qty', '>', 0)
                            ->orderBy('date', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        foreach ($batches as $batch) {
                            if ($qtyToCost <= 0) break;

                            $qtyFromBatch = min($qtyToCost, $batch->remaining_qty);
                            $itemCogs += ($qtyFromBatch * $batch->unit_cost);
                            
                            $batch->remaining_qty -= $qtyFromBatch;
                            $batch->save();
                            
                            $qtyToCost -= $qtyFromBatch;
                        }

                        // Jika stok di sistem kurang (qtyToCost > 0), sisa barang dihitung pakai harga moving average (buy_price) terakhir
                        if ($qtyToCost > 0) {
                            $itemCogs += ($qtyToCost * $prod->buy_price);
                        }
                    } else {
                        // Moving Average Logic
                        $itemCogs = $prod->buy_price * $qtyToCost;
                    }

                    $totalCogs += $itemCogs;

                    // Faktur langsung (tanpa Pesanan/Surat Jalan): barang keluar saat faktur dibuat.
                    // Bila dari Pesanan, stok sudah dikurangi oleh Surat Jalan.
                    if (!$so) {
                        if ((float) $prod->stock < (float) $item['quantity']) {
                            throw new \Exception('Stok ' . $prod->name . ' tidak cukup (tersedia ' . (float) $prod->stock . ').');
                        }
                        \App\Models\InventoryMovement::create([
                            'company_id' => auth()->user()->company_id, 'product_id' => $prod->id, 'date' => $request->invoice_date,
                            'type' => 'out', 'quantity' => $item['quantity'], 'reference_type' => 'sales_invoice', 'reference_id' => $invoice->id,
                            'notes' => 'Penjualan langsung ' . $invoiceNumber, 'created_by' => auth()->id(),
                        ]);
                    }
                }
            }

            // 6. Jurnal HPP (Jika ada barang dagang)
            if ($totalCogs > 0) {
                $hppAccount = \App\Services\AccountResolver::cogs(auth()->user()->company_id);
                $inventoryAccount = \App\Services\AccountResolver::inventory(auth()->user()->company_id);
                
                if ($hppAccount && $inventoryAccount) {
                    // Debit HPP
                    \App\Models\JournalDetail::create([
                        'journal_id' => $journal->id,
                        'account_id' => $hppAccount->id,
                        'debit' => $totalCogs,
                        'credit' => 0,
                        'description' => 'HPP Penjualan ' . $invoiceNumber,
                    ]);

                    // Credit Persediaan
                    \App\Models\JournalDetail::create([
                        'journal_id' => $journal->id,
                        'account_id' => $inventoryAccount->id,
                        'debit' => 0,
                        'credit' => $totalCogs,
                        'description' => 'Pengeluaran Persediaan ' . $invoiceNumber,
                    ]);
                }
            }

            \App\Services\JournalGuard::assertBalanced($journal);

            DB::commit();
            if ($so) {
                \App\Services\OrderProgress::syncSalesOrder($so->fresh());
            }
            return redirect()->route('client.sales-invoices.index')->with('success', 'Faktur Penjualan berhasil dibuat dan Jurnal telah tercatat otomatis.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal membuat Invoice: ' . $e->getMessage());
        }
    }

    public function show(SalesInvoice $sales_invoice)
    {
        if ($sales_invoice->company_id !== auth()->user()->company_id) abort(403);
        
        $sales_invoice->load(['customer', 'items.product', 'tax', 'journal']);
        return view('client.sales_invoices.show', compact('sales_invoice'));
    }

}
