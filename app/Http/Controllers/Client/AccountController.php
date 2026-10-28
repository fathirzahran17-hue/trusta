<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        // Kita ambil akun master (tanpa parent) lalu memuat relasi children secara hirarki
        $accounts = Account::with(['children' => function($q) {
                $q->orderBy('code', 'asc');
            }])
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->orderBy('code', 'asc')
            ->get();
                    
        return view('client.accounts.index', compact('accounts'));
    }

    public function beginningBalance()
    {
        $companyId = auth()->user()->company_id;
        $accounts = Account::where('company_id', $companyId)
            ->whereIn('type', ['asset', 'liability', 'equity']) // Biasanya saldo awal hanya untuk akun neraca
            ->orderBy('code', 'asc')
            ->get();
            
        $opening = \App\Services\OpeningBalanceService::current($companyId);

        return view('client.accounts.beginning_balance', compact('accounts', 'opening'));
    }

    public function storeBeginningBalance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        
        $request->validate([
            'balances' => 'required|array',
            'balances.*' => 'numeric',
            'date' => 'required|date'
        ]);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($request, $companyId) {
                $valid = Account::where('company_id', $companyId)->pluck('id')->flip();
                $overrides = [];
                foreach ($request->balances as $accountId => $balance) {
                    if ($valid->has((int) $accountId)) $overrides[(int) $accountId] = (float) $balance;
                }
                \App\Services\OpeningBalanceService::set($companyId, $request->date, $overrides, auth()->id());
            });
            return redirect()->route('client.accounts.index')->with('success', 'Saldo Awal berhasil disimpan dan dijurnal secara otomatis.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menyimpan saldo awal: ' . $e->getMessage());
        }
    }

    public function create()
    {
        $parents = Account::where('company_id', auth()->user()->company_id)->orderBy('code', 'asc')->get();
        return view('client.accounts.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:accounts,id',
            'code' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,cost_of_sales,expense,other_income,other_expense',
            'normal_balance' => 'required|in:debit,credit',
            'opening_balance' => 'numeric',
            'is_active' => 'boolean',
            'is_cash_bank' => 'boolean',
            'is_settlement_deduction' => 'boolean',
        ]);

        // Validate unique code per company
        $exists = Account::where('company_id', auth()->user()->company_id)
            ->where('code', $request->code)
            ->exists();
            
        if ($exists) {
            return back()->with('error', 'Kode Akun sudah digunakan!')->withInput();
        }

        $account = Account::create([
            'company_id' => auth()->user()->company_id,
            'parent_id' => $request->parent_id,
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'normal_balance' => $request->normal_balance,
            'opening_balance' => 0,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'is_cash_bank' => $request->has('is_cash_bank'),
            'is_settlement_deduction' => $request->has('is_settlement_deduction'),
            'is_system' => false,
        ]);

        if ((float) $request->opening_balance != 0.0) {
            $cid = auth()->user()->company_id;
            $date = \App\Services\OpeningBalanceService::journal($cid)?->transaction_date?->toDateString() ?? now()->toDateString();
            \App\Services\OpeningBalanceService::set($cid, $date, [$account->id => (float) $request->opening_balance], auth()->id());
        }

        return redirect()->route('client.accounts.index')->with('success', 'Akun berhasil ditambahkan.');
    }

    public function edit(Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);
        $parents = Account::where('company_id', auth()->user()->company_id)->where('id', '!=', $account->id)->orderBy('code', 'asc')->get();
        $account->opening_balance = \App\Services\OpeningBalanceService::current($account->company_id)[$account->id] ?? $account->opening_balance;
        return view('client.accounts.edit', compact('account', 'parents'));
    }

    public function update(Request $request, Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'parent_id' => 'nullable|exists:accounts,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:asset,liability,equity,revenue,cost_of_sales,expense,other_income,other_expense',
            'normal_balance' => 'required|in:debit,credit',
            'is_active' => 'boolean',
            'is_cash_bank' => 'boolean',
            'is_settlement_deduction' => 'boolean',
        ]);

        $data = $request->only(['parent_id', 'name', 'type', 'normal_balance']);
        $data['is_active'] = $request->has('is_active');
        $data['is_cash_bank'] = $request->has('is_cash_bank');
        $data['is_settlement_deduction'] = $request->has('is_settlement_deduction');
        
        // Code dan Opening Balance bisa diubah kalau bukan akun system
        if (!$account->is_system) {
            if ($request->has('code') && $request->code !== $account->code) {
                $exists = Account::where('company_id', auth()->user()->company_id)
                    ->where('code', $request->code)
                    ->exists();
                if ($exists) return back()->with('error', 'Kode Akun sudah digunakan!')->withInput();
                $data['code'] = $request->code;
            }
        }

        $account->update($data);

        if (!$account->is_system && $request->has('opening_balance')) {
            $cid = auth()->user()->company_id;
            $current = \App\Services\OpeningBalanceService::current($cid)[$account->id] ?? (float) $account->opening_balance;
            if (abs((float) $request->opening_balance - $current) >= 0.005) {
                $date = \App\Services\OpeningBalanceService::journal($cid)?->transaction_date?->toDateString() ?? now()->toDateString();
                \App\Services\OpeningBalanceService::set($cid, $date, [$account->id => (float) $request->opening_balance], auth()->id());
            }
        }
        return redirect()->route('client.accounts.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function show(Request $request, Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        $period = \App\Support\LedgerPeriod::resolve($request);
        $periodOpening = \App\Support\LedgerPeriod::openingBefore($account, $period['from']);
        $showVoid = \App\Support\LedgerPeriod::showVoided($request);
        $period['show_void'] = $showVoid;
        $period['hidden_void'] = $showVoid ? 0 : (int) ceil(count(array_intersect(\App\Support\LedgerPeriod::cancelledPairIds((int) $account->company_id), \App\Models\JournalDetail::where('account_id', $account->id)->pluck('journal_id')->all())) / 2);
        $mutations = \App\Support\LedgerPeriod::mutations($account, $period['from'], $period['to'], $showVoid);
        $totalCount = \App\Support\LedgerPeriod::totalCount($account, $showVoid);

        return view('client.accounts.show', compact('account', 'mutations', 'period', 'periodOpening', 'totalCount'));
    }

    public function destroy(Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        if ($account->is_system) {
            return back()->with('error', 'Akun sistem bawaan tidak dapat dihapus.');
        }
        
        $hasTransactions = \App\Models\JournalDetail::where('account_id', $account->id)->exists();
        if ($hasTransactions) {
            return back()->with('error', 'Akun tidak dapat dihapus karena sudah memiliki histori transaksi jurnal.');
        }

        $hasChildren = Account::where('parent_id', $account->id)->exists();
        if ($hasChildren) {
            return back()->with('error', 'Akun tidak dapat dihapus karena masih memiliki sub-akun (anak).');
        }
        
        $account->delete();
        return redirect()->route('client.accounts.index')->with('success', 'Akun berhasil dihapus.');
    }

    public function history(Request $request, Account $account = null)
    {
        $companyId = auth()->user()->company_id;
        $accounts = Account::where('company_id', $companyId)->orderBy('code')->get();
        
        // Handle query string ?account=xx
        if (!$account && $request->has('account')) {
            $account = Account::find($request->account);
        }
        
        $mutations = collect();
        if ($account && $account->company_id === $companyId) {
            $mutations = \App\Models\JournalDetail::where('account_id', $account->id)
                ->whereHas('journal', function($query) use ($companyId) {
                    $query->where('status', 'posted')
                          ->where('company_id', $companyId);
                })
                ->with('journal')
                ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
                ->orderBy('journals.transaction_date', 'asc')
                ->orderBy('journal_details.id', 'asc')
                ->select('journal_details.*')
                ->get();
        }

        return view('client.accounts.history', compact('accounts', 'account', 'mutations'));
    }
}
