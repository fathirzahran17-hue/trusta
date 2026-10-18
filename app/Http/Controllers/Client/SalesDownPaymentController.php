<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\DownPayment;
use App\Models\SalesOrder;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesDownPaymentController extends Controller
{
    public function index()
    {
        $downPayments = DownPayment::with(['customer', 'salesOrder'])
            ->where('company_id', auth()->user()->company_id)
            ->where('type', 'sales')
            ->orderBy('payment_date', 'desc')
            ->paginate(20);
            
        return view('client.sales_down_payments.index', compact('downPayments'));
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $customers = Customer::where('company_id', $companyId)->orderBy('name')->get();
        
        $salesOrders = SalesOrder::where('company_id', $companyId)
            ->whereIn('status', ['open'])
            ->orderBy('order_date', 'desc')
            ->get();
            
        // Accounts for Cash/Bank
        $accounts = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('name', 'like', '%Kas%')
            ->orWhere('name', 'like', '%Bank%')
            ->get();
            
        // Auto number
        $lastDP = DownPayment::where('company_id', $companyId)
            ->where('type', 'sales')
            ->whereYear('payment_date', date('Y'))
            ->whereMonth('payment_date', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        $nextId = $lastDP ? (intval(substr($lastDP->payment_number, -4)) + 1) : 1;
        $paymentNumber = 'DP-S-' . date('Ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            
        return view('client.sales_down_payments.create', compact('customers', 'salesOrders', 'accounts', 'paymentNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'account_id' => 'required|exists:accounts,id'
        ]);

        try {
            DB::beginTransaction();

            $companyId = auth()->user()->company_id;

            $downPayment = DownPayment::create([
                'company_id' => $companyId,
                'type' => 'sales',
                'customer_id' => $request->customer_id,
                'sales_order_id' => $request->sales_order_id,
                'payment_number' => $request->payment_number,
                'payment_date' => $request->payment_date,
                'payment_method' => 'bank_transfer',
                'amount' => $request->amount,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            // Jurnal Penerimaan Uang Muka: Dr Kas/Bank, Cr Uang Muka Penjualan (kewajiban sampai dipakai pada faktur)
            $cashAccount = Account::where('company_id', $companyId)->find($request->account_id);
            $receivableAccount = \App\Services\AccountResolver::salesAdvance($companyId);
            if (!$cashAccount) throw new \Exception('Akun kas/bank tidak valid.');
            if (!$receivableAccount) throw new \Exception('Akun "Uang Muka Penjualan" belum ada di Bagan Akun.');

            {
                $journal = Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-DPS-' . time(),
                    'type' => 'general',
                    'transaction_date' => $request->payment_date,
                    'description' => 'Penerimaan Uang Muka Penjualan ' . $downPayment->payment_number,
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);

                // Debit Kas/Bank
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $cashAccount->id,
                    'description' => 'Penerimaan Uang Muka Penjualan ' . $downPayment->payment_number,
                    'debit' => $request->amount,
                    'credit' => 0,
                ]);

                // Credit Uang Muka Penjualan (kewajiban)
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $receivableAccount->id,
                    'description' => 'Uang Muka Penjualan ' . $downPayment->payment_number,
                    'debit' => 0,
                    'credit' => $request->amount,
                ]);

                \App\Services\JournalGuard::assertBalanced($journal);
                $downPayment->update(['journal_id' => $journal->id]);
            }

            DB::commit();
            return redirect()->route('client.sales.down_payments')->with('success', 'Uang Muka Penjualan berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
