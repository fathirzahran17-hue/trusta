<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DownPayment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseDownPaymentController extends Controller
{
    public function index()
    {
        $downPayments = DownPayment::with(['supplier', 'purchaseOrder'])
            ->where('company_id', auth()->user()->company_id)
            ->where('type', 'purchase')
            ->orderBy('payment_date', 'desc')
            ->paginate(20);
            
        return view('client.purchase_down_payments.index', compact('downPayments'));
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $suppliers = Supplier::where('company_id', $companyId)->orderBy('name')->get();
        
        $purchaseOrders = PurchaseOrder::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'received'])
            ->orderBy('order_date', 'desc')
            ->get();
            
        // Accounts for Cash/Bank
        $accounts = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('name', 'like', '%Kas%')
            ->orWhere('name', 'like', '%Bank%')
            ->get();
            
        // Auto number
        $paymentNumber = \App\Services\DocumentNumber::next(\App\Models\DownPayment::class, 'payment_number', 'DP-P-', (int) $companyId, null, fn($q) => $q->where('type', 'purchase'));
            
        return view('client.purchase_down_payments.create', compact('suppliers', 'purchaseOrders', 'accounts', 'paymentNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'payment_number' => ['required', 'string', 'max:100', \App\Services\DocumentNumber::rule('down_payments', 'payment_number')],
            'supplier_id' => 'required|exists:suppliers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'required|exists:accounts,id'
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $downPayment = DownPayment::create([
                'company_id' => $companyId,
                'type' => 'purchase',
                'supplier_id' => $request->supplier_id,
                'purchase_order_id' => $request->purchase_order_id,
                'payment_number' => $request->payment_number,
                'payment_date' => $request->payment_date,
                'payment_method' => 'bank_transfer',
                'amount' => $request->amount,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Jurnal Pengeluaran Uang Muka: Dr Uang Muka Pembelian (aset), Cr Kas/Bank
            $cashAccount = Account::where('company_id', $companyId)->find($request->account_id);
            $payableAccount = \App\Services\AccountResolver::purchaseAdvance($companyId);
            if (!$cashAccount) throw new \Exception('Akun kas/bank tidak valid.');
            if (!$payableAccount) throw new \Exception('Akun "Uang Muka Pembelian" belum ada di Bagan Akun.');

            {
                $journal = Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-DPP-' . time(),
                    'type' => 'general',
                    'transaction_date' => $request->payment_date,
                    'description' => 'Pembayaran Uang Muka Pembelian ' . $downPayment->payment_number,
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);

                // Debit Hutang (Mengurangi hutang)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $payableAccount->id,
                    'description' => 'Uang Muka Pembelian ' . $downPayment->payment_number,
                    'debit' => $request->amount,
                    'credit' => 0,
                ]);

                // Credit Kas/Bank
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $cashAccount->id,
                    'description' => 'Pembayaran Uang Muka Pembelian ' . $downPayment->payment_number,
                    'debit' => 0,
                    'credit' => $request->amount,
                ]);

                \App\Services\JournalGuard::assertBalanced($journal);
                $downPayment->update(['journal_id' => $journal->id]);
            }

            DB::commit();
            return redirect()->route('client.purchases.down_payments')->with('success', 'Uang Muka Pembelian berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
