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
            
        return view('client.accounts.beginning_balance', compact('accounts'));
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
            \Illuminate\Support\Facades\DB::beginTransaction();

            $totalDebit = 0;
            $totalCredit = 0;

            // Cari jurnal saldo awal yang sudah ada (jika pernah diinput)
            $existingJournal = \App\Models\Journal::where('company_id', $companyId)
                ->where('description', 'like', '%Saldo Awal%')
                ->first();

            if ($existingJournal) {
                $existingJournal->details()->delete(); // Hapus detail lama
                $journal = $existingJournal;
                $journal->update([
                    'transaction_date' => $request->date,
                    'updated_by' => auth()->id()
                ]);
            } else {
                $journal = \App\Models\Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-OB-' . time(),
                    'type' => 'general',
                    'transaction_date' => $request->date,
                    'description' => 'Saldo Awal Sistem',
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);
            }

            foreach ($request->balances as $accountId => $balance) {
                if ($balance == 0) continue; // Skip jika 0

                $account = Account::find($accountId);
                if (!$account || $account->company_id != $companyId) continue;
                
                // Update kolom opening_balance di master account
                $account->update(['opening_balance' => $balance]);

                $debit = 0;
                $credit = 0;

                // Saldo normal
                if ($account->normal_balance == 'debit') {
                    if ($balance > 0) { $debit = $balance; } else { $credit = abs($balance); }
                } else {
                    if ($balance > 0) { $credit = $balance; } else { $debit = abs($balance); }
                }

                $totalDebit += $debit;
                $totalCredit += $credit;

                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $account->id,
                    'description' => 'Saldo Awal ' . $account->name,
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            // Jika tidak balance, tampung selisihnya di Opening Balance Equity (Ekuitas Saldo Awal)
            $difference = $totalDebit - $totalCredit;
            
            if ($difference != 0) {
                $equityAccount = Account::where('company_id', $companyId)->where('name', 'like', '%Ekuitas Saldo Awal%')->first();
                if (!$equityAccount) {
                    $equityAccount = Account::create([
                        'company_id' => $companyId,
                        'code' => '3-9999',
                        'name' => 'Ekuitas Saldo Awal',
                        'type' => 'equity',
                        'normal_balance' => 'credit',
                        'is_system' => true,
                    ]);
                }

                $debit = 0;
                $credit = 0;

                if ($difference > 0) {
                    // Debit > Credit -> Kita perlu menambah Credit agar balance
                    $credit = $difference;
                } else {
                    $debit = abs($difference);
                }

                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $equityAccount->id,
                    'description' => 'Penyeimbang Saldo Awal',
                    'debit' => $debit,
                    'credit' => $credit,
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return redirect()->route('client.accounts.index')->with('success', 'Saldo Awal berhasil disimpan dan dijurnal secara otomatis.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
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
        ]);

        // Validate unique code per company
        $exists = Account::where('company_id', auth()->user()->company_id)
            ->where('code', $request->code)
            ->exists();
            
        if ($exists) {
            return back()->with('error', 'Kode Akun sudah digunakan!')->withInput();
        }

        Account::create([
            'company_id' => auth()->user()->company_id,
            'parent_id' => $request->parent_id,
            'code' => $request->code,
            'name' => $request->name,
            'type' => $request->type,
            'normal_balance' => $request->normal_balance,
            'opening_balance' => $request->opening_balance ?? 0,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'is_cash_bank' => $request->has('is_cash_bank'),
            'is_system' => false,
        ]);

        return redirect()->route('client.accounts.index')->with('success', 'Akun berhasil ditambahkan.');
    }

    public function edit(Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);
        $parents = Account::where('company_id', auth()->user()->company_id)->where('id', '!=', $account->id)->orderBy('code', 'asc')->get();
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
        ]);

        $data = $request->only(['parent_id', 'name', 'type', 'normal_balance']);
        $data['is_active'] = $request->has('is_active');
        $data['is_cash_bank'] = $request->has('is_cash_bank');
        
        // Code dan Opening Balance bisa diubah kalau bukan akun system
        if (!$account->is_system) {
            if ($request->has('code') && $request->code !== $account->code) {
                $exists = Account::where('company_id', auth()->user()->company_id)
                    ->where('code', $request->code)
                    ->exists();
                if ($exists) return back()->with('error', 'Kode Akun sudah digunakan!')->withInput();
                $data['code'] = $request->code;
            }
            if ($request->has('opening_balance')) {
                $data['opening_balance'] = $request->opening_balance;
            }
        }

        $account->update($data);
        return redirect()->route('client.accounts.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function show(Account $account)
    {
        if ($account->company_id !== auth()->user()->company_id) abort(403);

        // Ambil semua detail jurnal yang berkaitan dengan akun ini
        // Urutkan berdasarkan tanggal jurnal, lalu id detail
        $mutations = \App\Models\JournalDetail::where('account_id', $account->id)
            ->whereHas('journal', function($query) {
                $query->where('status', 'posted')
                      ->where('company_id', auth()->user()->company_id);
            })
            ->with('journal')
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->orderBy('journals.transaction_date', 'asc')
            ->orderBy('journal_details.id', 'asc')
            ->select('journal_details.*')
            ->get();

        return view('client.accounts.show', compact('account', 'mutations'));
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
