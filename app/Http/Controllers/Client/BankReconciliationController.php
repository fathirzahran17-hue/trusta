<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BankReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi Bank: mencocokkan mutasi kas/bank di buku dengan rekening koran.
 * Mutasi yang sudah muncul di rekening koran dicentang; saldo tercentang harus sama dengan saldo rekening koran.
 * Mutasi yang belum dicentang = transaksi dalam perjalanan (outstanding) dan muncul lagi di rekonsiliasi berikutnya.
 */
class BankReconciliationController extends Controller
{
    private function cid(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function accounts()
    {
        return Account::where('company_id', $this->cid())->where('is_cash_bank', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();
    }

    /** Saldo yang sudah cocok (saldo awal akun + semua mutasi yang sudah direkonsiliasi). */
    private function clearedBalance(Account $acc): float
    {
        $row = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->join('bank_reconciliations', 'bank_reconciliations.id', '=', 'journal_details.bank_reconciliation_id')
            ->whereNull('bank_reconciliations.voided_at')
            ->where('journal_details.account_id', $acc->id)->where('journals.status', 'posted')
            ->selectRaw('COALESCE(SUM(journal_details.debit),0) d, COALESCE(SUM(journal_details.credit),0) c')->first();

        return round((float) $acc->opening_balance + (float) $row->d - (float) $row->c, 2);
    }

    private function bookBalance(Account $acc, string $date): float
    {
        $row = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journal_details.account_id', $acc->id)->where('journals.status', 'posted')->where('journals.transaction_date', '<=', $date . ' 23:59:59')
            ->selectRaw('COALESCE(SUM(journal_details.debit),0) d, COALESCE(SUM(journal_details.credit),0) c')->first();

        return round((float) $acc->opening_balance + (float) $row->d - (float) $row->c, 2);
    }

    public function index(Request $request)
    {
        $accounts = $this->accounts();
        $account = $accounts->firstWhere('id', (int) $request->query('account_id')) ?? null;
        $date = $request->query('date') ?: now()->toDateString();
        $rows = collect();
        $cleared = $book = 0.0;
        $last = null;
        if ($account) {
            $rows = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
                ->where('journal_details.account_id', $account->id)->where('journals.status', 'posted')
                ->where('journals.transaction_date', '<=', $date . ' 23:59:59')
                ->where(function ($q) {
                    $q->whereNull('journal_details.bank_reconciliation_id')
                        ->orWhereIn('journal_details.bank_reconciliation_id', BankReconciliation::whereNotNull('voided_at')->select('id'));
                })
                ->orderBy('journals.transaction_date')->orderBy('journal_details.id')
                ->select('journal_details.id', 'journal_details.debit', 'journal_details.credit', 'journal_details.description', 'journals.id as journal_id', 'journals.ref_number', 'journals.transaction_date', 'journals.description as jdesc')
                ->get();
            $cleared = $this->clearedBalance($account);
            $book = $this->bookBalance($account, $date);
            $last = BankReconciliation::where('company_id', $this->cid())->where('account_id', $account->id)->whereNull('voided_at')->latest('statement_date')->latest('id')->first();
        }
        $history = BankReconciliation::with('account')->where('company_id', $this->cid())->orderByDesc('statement_date')->orderByDesc('id')->limit(30)->get();
        $latestIds = BankReconciliation::where('company_id', $this->cid())->whereNull('voided_at')->selectRaw('MAX(id) id')->groupBy('account_id')->pluck('id')->all();

        return view('client.cash_bank.reconciliation', compact('accounts', 'account', 'date', 'rows', 'cleared', 'book', 'last', 'history', 'latestIds'));
    }

    public function store(Request $request)
    {
        \App\Support\Money::normalize($request, ['statement_balance']);
        $d = $request->validate([
            'account_id' => 'required|integer',
            'date' => 'required|date',
            'statement_balance' => 'required|numeric',
            'lines' => 'nullable|array',
            'lines.*' => 'integer',
            'notes' => 'nullable|string|max:200',
        ]);
        $account = $this->accounts()->firstWhere('id', (int) $d['account_id']);
        if (! $account) {
            return back()->withInput()->with('error', 'Pilih akun kas/bank yang valid.');
        }
        $voided = BankReconciliation::whereNotNull('voided_at')->pluck('id')->all();
        $lines = DB::table('journal_details')->join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $this->cid())->where('journal_details.account_id', $account->id)->where('journals.status', 'posted')
            ->where('journals.transaction_date', '<=', $d['date'] . ' 23:59:59')
            ->whereIn('journal_details.id', $d['lines'] ?? [])
            ->where(fn ($q) => $q->whereNull('journal_details.bank_reconciliation_id')->orWhereIn('journal_details.bank_reconciliation_id', $voided ?: [0]))
            ->select('journal_details.id', 'journal_details.debit', 'journal_details.credit')->get();
        $cleared = round($this->clearedBalance($account) + $lines->sum('debit') - $lines->sum('credit'), 2);
        $diff = round((float) $d['statement_balance'] - $cleared, 2);
        if (abs($diff) > 0.004) {
            return back()->withInput()->with('error', 'Belum cocok: saldo yang dicentang Rp ' . number_format($cleared, 2, ',', '.') . ', saldo rekening koran Rp ' . number_format((float) $d['statement_balance'], 2, ',', '.')
                . ' (selisih Rp ' . number_format($diff, 2, ',', '.') . '). Cek lagi centangnya, atau catat dulu biaya/bunga bank yang belum dibukukan.');
        }

        DB::transaction(function () use ($account, $d, $cleared, $lines) {
            $rec = BankReconciliation::create([
                'company_id' => $this->cid(), 'account_id' => $account->id, 'statement_date' => $d['date'],
                'statement_balance' => $d['statement_balance'], 'cleared_balance' => $cleared, 'cleared_count' => $lines->count(),
                'notes' => $d['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            // tanpa event model: hanya menandai, tidak mengubah nilai jurnal (aman juga untuk periode yang sudah dikunci)
            DB::table('journal_details')->whereIn('id', $lines->pluck('id'))->update(['bank_reconciliation_id' => $rec->id]);
        });

        return redirect()->route('client.cash-bank.reconciliation', ['account_id' => $account->id])
            ->with('success', 'Rekonsiliasi ' . $account->name . ' per ' . date('d/m/Y', strtotime($d['date'])) . ' disimpan (' . $lines->count() . ' mutasi cocok).');
    }

    public function void(BankReconciliation $reconciliation)
    {
        abort_if($reconciliation->company_id !== $this->cid(), 404);
        if ($reconciliation->voided_at) {
            return back()->with('error', 'Rekonsiliasi ini sudah dibatalkan.');
        }
        $newer = BankReconciliation::where('company_id', $this->cid())->where('account_id', $reconciliation->account_id)->whereNull('voided_at')->where('id', '>', $reconciliation->id)->exists();
        if ($newer) {
            return back()->with('error', 'Batalkan dulu rekonsiliasi yang lebih baru untuk akun ini.');
        }
        DB::transaction(function () use ($reconciliation) {
            DB::table('journal_details')->where('bank_reconciliation_id', $reconciliation->id)->update(['bank_reconciliation_id' => null]);
            $reconciliation->update(['voided_at' => now()]);
        });

        return back()->with('success', 'Rekonsiliasi dibatalkan; mutasinya kembali belum dicocokkan.');
    }
}
