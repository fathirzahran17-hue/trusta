<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\ProjectJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashBankController extends Controller
{
    public function index()
    {
        $accounts = Account::where('company_id', auth()->user()->company_id)
            ->where('is_cash_bank', true)
            ->get();
            
        return view('client.cash_bank.index', compact('accounts'));
    }

    public function show(Request $request, Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        $period = \App\Support\LedgerPeriod::resolve($request);
        $periodOpening = \App\Support\LedgerPeriod::openingBefore($account, $period['from']);
        // Menaik, dibalik di view (terbaru di atas) setelah saldo berjalan dihitung.
        $mutations = \App\Support\LedgerPeriod::mutations($account, $period['from'], $period['to'])->reverse()->values();
        $totalCount = \App\Support\LedgerPeriod::totalCount($account);

        $allAccounts = Account::where('company_id', auth()->user()->company_id)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get()
            ->reject(fn ($a) => $a->is_header)->values();
            
        $jobs = ProjectJob::where('company_id', auth()->user()->company_id)->where('is_active', true)->get();

        return view('client.cash_bank.show', compact('account', 'mutations', 'allAccounts', 'jobs', 'period', 'periodOpening', 'totalCount'));
    }

    public function storeTransaction(Request $request, Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        // Kompatibel dengan form lama (satu akun lawan): ubah ke bentuk banyak baris
        if (!is_array($request->input('contra_account_id'))) {
            $request->merge([
                'contra_account_id' => [$request->input('contra_account_id')],
                'project_job_id' => [$request->input('project_job_id')],
                'amounts' => [$request->input('amount')],
            ]);
        }
        \App\Support\Money::normalize($request, ['amounts.*']);

        $request->validate([
            'transaction_type' => 'required|in:spend,receive',
            'transaction_date' => 'required|date',
            'contra_account_id' => 'required|array|min:1|max:50',
            'contra_account_id.*' => ['required', \Illuminate\Validation\Rule::exists('accounts', 'id')->where('company_id', auth()->user()->company_id)],
            'project_job_id' => 'nullable|array',
            'project_job_id.*' => 'nullable|integer',
            'amounts' => 'required|array|min:1',
            'amounts.*' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
        ]);
        if (count($request->amounts) !== count($request->contra_account_id)) {
            return back()->withInput()->with('error', 'Setiap akun lawan harus punya nominal.');
        }
        if (in_array($account->id, array_map('intval', $request->contra_account_id), true)) {
            return back()->withInput()->with('error', 'Akun lawan tidak boleh sama dengan akun kas/bank yang sedang dikelola.');
        }
        $lines = [];
        foreach ($request->contra_account_id as $i => $accId) {
            $lines[] = ['account_id' => (int) $accId, 'amount' => round((float) $request->amounts[$i], 2), 'job' => $request->project_job_id[$i] ?? null];
        }
        $total = round(array_sum(array_column($lines, 'amount')), 2);
        $spend = $request->transaction_type == 'spend';

        try {
            DB::beginTransaction();

            $prefix = $request->transaction_type == 'spend' ? 'SP-' : 'RC-';
            $refNumber = $this->generateRefNumber($prefix, $request->transaction_date);

            $fullDescription = $request->description;
            if ($request->contact_name) {
                $fullDescription .= ' - ' . $request->contact_name;
            }

            $journal = Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $refNumber,
                'type' => 'auto_cash',
                'transaction_date' => $request->transaction_date,
                'description' => $fullDescription,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            // Akun kas/bank: satu baris sebesar total; akun lawan: satu baris per split
            JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $account->id,
                'debit' => $spend ? 0 : $total,
                'credit' => $spend ? $total : 0,
                'description' => $request->description,
            ]);
            foreach ($lines as $l) {
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $l['account_id'],
                    'debit' => $spend ? $l['amount'] : 0,
                    'credit' => $spend ? 0 : $l['amount'],
                    'description' => $request->description,
                    'project_job_id' => $l['job'] ?: null,
                ]);
            }
            \App\Services\JournalGuard::assertBalanced($journal);
            $msg = ($spend ? 'Pengeluaran Uang (Spend Money)' : 'Penerimaan Uang (Receive Money)') . ' berhasil dicatat' . (count($lines) > 1 ? ' dengan ' . count($lines) . ' akun lawan.' : '.');

            DB::commit();
            return redirect()->route('client.cash-bank.show', $account->id)->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal mencatat transaksi: ' . $e->getMessage());
        }
    }

    public function payments()
    {
        $companyId = auth()->user()->company_id;
        $cashAccounts = Account::where('company_id', $companyId)->where('is_cash_bank', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
        $allAccounts = Account::where('company_id', $companyId)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
        $jobs = ProjectJob::where('company_id', $companyId)->where('is_active', true)->get();
        return view('client.cash_bank.payments', compact('cashAccounts', 'allAccounts', 'jobs'));
    }

    /**
     * Pelunasan faktur harus lewat menu Penerimaan/Pembayaran agar status faktur ikut berubah.
     * Jurnal kas bebas ke akun Piutang/Hutang akan membuat buku besar dan faktur tidak sinkron.
     */
    private function rejectSubledgerAccount(array $accountIds, string $kind): ?string
    {
        $cid = auth()->user()->company_id;
        $acc = $kind === 'sales' ? \App\Services\AccountResolver::receivable($cid) : \App\Services\AccountResolver::payable($cid);
        if ($acc && in_array((int) $acc->id, array_map('intval', array_filter($accountIds)), true)) {
            return $kind === 'sales'
                ? 'Pembayaran dari pelanggan untuk faktur harus dicatat lewat menu Penjualan → Penerimaan Penjualan, supaya faktur otomatis ditandai lunas. Form ini hanya untuk penerimaan di luar faktur.'
                : 'Pembayaran ke pemasok untuk faktur harus dicatat lewat menu Pembelian → Pembayaran Pembelian, supaya faktur otomatis ditandai lunas. Form ini hanya untuk pengeluaran di luar faktur.';
        }
        return null;
    }

    public function storePayments(Request $request)
    {
        $request->validate([
            'pay_from' => 'required|exists:accounts,id',
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'expense_accounts' => 'required|array|min:1',
            'amounts' => 'required|array|min:1',
        ]);

        if ($msg = $this->rejectSubledgerAccount((array) $request->expense_accounts, 'purchase')) {
            return back()->withInput()->with('error', $msg);
        }

        try {
            DB::beginTransaction();

            $refNumber = $request->ref_number ?? $this->generateRefNumber('SP-', $request->transaction_date);

            $journal = Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $refNumber,
                'type' => 'auto_cash',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description . ($request->payee ? ' - ' . $request->payee : ''),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $totalAmount = 0;

            foreach ($request->expense_accounts as $key => $accountId) {
                if (!$accountId || !isset($request->amounts[$key]) || $request->amounts[$key] <= 0) continue;
                
                $amt = $request->amounts[$key];
                $totalAmount += $amt;
                
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'debit' => $amt,
                    'credit' => 0,
                    'description' => $request->line_desc[$key] ?? '',
                    'project_job_id' => $request->jobs[$key] ?? null,
                ]);
            }

            if ($totalAmount <= 0) {
                throw new \Exception("Total pembayaran tidak boleh nol.");
            }

            // Kredit akun kas
            JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $request->pay_from,
                'debit' => 0,
                'credit' => $totalAmount,
                'description' => $request->description,
            ]);

            DB::commit();
            return redirect()->route('client.cash-bank.index')->with('success', 'Pembayaran berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal mencatat pembayaran: ' . $e->getMessage());
        }
    }

    public function receipts()
    {
        $companyId = auth()->user()->company_id;
        $cashAccounts = Account::where('company_id', $companyId)->where('is_cash_bank', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
        $allAccounts = Account::where('company_id', $companyId)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
        $jobs = ProjectJob::where('company_id', $companyId)->where('is_active', true)->get();
        return view('client.cash_bank.receipts', compact('cashAccounts', 'allAccounts', 'jobs'));
    }

    public function storeReceipts(Request $request)
    {
        $request->validate([
            'receive_to' => 'required|exists:accounts,id',
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'income_accounts' => 'required|array|min:1',
            'amounts' => 'required|array|min:1',
        ]);

        if ($msg = $this->rejectSubledgerAccount((array) $request->income_accounts, 'sales')) {
            return back()->withInput()->with('error', $msg);
        }

        try {
            DB::beginTransaction();

            $refNumber = $request->ref_number ?? $this->generateRefNumber('RC-', $request->transaction_date);

            $journal = Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $refNumber,
                'type' => 'auto_cash',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description . ($request->payer ? ' - ' . $request->payer : ''),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $totalAmount = 0;

            foreach ($request->income_accounts as $key => $accountId) {
                if (!$accountId || !isset($request->amounts[$key]) || $request->amounts[$key] <= 0) continue;
                
                $amt = $request->amounts[$key];
                $totalAmount += $amt;
                
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'debit' => 0,
                    'credit' => $amt,
                    'description' => $request->line_desc[$key] ?? '',
                    'project_job_id' => $request->jobs[$key] ?? null,
                ]);
            }

            if ($totalAmount <= 0) {
                throw new \Exception("Total penerimaan tidak boleh nol.");
            }

            // Debit akun kas
            JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $request->receive_to,
                'debit' => $totalAmount,
                'credit' => 0,
                'description' => $request->description,
            ]);

            DB::commit();
            return redirect()->route('client.cash-bank.index')->with('success', 'Penerimaan berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal mencatat penerimaan: ' . $e->getMessage());
        }
    }

    public function transfers()
    {
        $companyId = auth()->user()->company_id;
        $cashAccounts = Account::where('company_id', $companyId)->where('is_cash_bank', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
        return view('client.cash_bank.transfers', compact('cashAccounts'));
    }

    public function storeTransfers(Request $request)
    {
        $request->validate([
            'from_account' => 'required|exists:accounts,id',
            'to_account' => 'required|exists:accounts,id|different:from_account',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $refNumber = $request->ref_number ?? $this->generateRefNumber('TF-', $request->transaction_date);

            $journal = Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $refNumber,
                'type' => 'auto_cash',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description ?? 'Transfer Bank',
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            // Kredit dari pengirim
            JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $request->from_account,
                'debit' => 0,
                'credit' => $request->amount,
                'description' => $request->description,
            ]);

            // Debit ke penerima
            JournalDetail::create([
                'journal_id' => $journal->id,
                'account_id' => $request->to_account,
                'debit' => $request->amount,
                'credit' => 0,
                'description' => $request->description,
            ]);

            DB::commit();
            return redirect()->route('client.cash-bank.index')->with('success', 'Transfer bank berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Gagal mencatat transfer: ' . $e->getMessage());
        }
    }

    public function statements()
    {
        $companyId = auth()->user()->company_id;
        
        $cashAccountIds = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where(function($q) {
                $q->where('name', 'like', '%Kas%')
                  ->orWhere('name', 'like', '%Bank%')
                  ->orWhere('name', 'like', '%Cash%');
            })->pluck('id');

        $transactions = JournalDetail::whereIn('account_id', $cashAccountIds)
            ->whereHas('journal', function($query) use ($companyId) {
                $query->where('status', 'posted')
                      ->where('company_id', $companyId);
            })
            ->with(['journal', 'account'])
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->orderBy('journals.transaction_date', 'desc')
            ->orderBy('journal_details.id', 'desc')
            ->select('journal_details.*')
            ->paginate(25);
            
        return view('client.cash_bank.statements', compact('transactions'));
    }


    public function reconciliation()
    {
        $companyId = auth()->user()->company_id;
        $accounts = Account::where('company_id', $companyId)->whereIn('type', ['asset'])->where('name', 'like', '%bank%')->orWhere('name', 'like', '%kas%')->where('company_id', $companyId)->orderBy('code')->get();
        return view('client.cash_bank.reconciliation', compact('accounts'));
    }

    private function generateRefNumber($prefix, $date)
    {
        return \App\Services\DocumentNumber::next(Journal::class, 'ref_number', $prefix, (int) auth()->user()->company_id, $date);
    }
}
