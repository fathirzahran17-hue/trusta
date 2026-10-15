<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalDetail;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return view('client.reports.index');
    }

    public function integrated(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $asOfDate = $endDate;
        $companyId = auth()->user()->company_id;

        // --- PROFIT & LOSS DATA ---
        $revenues = Account::where('company_id', $companyId)->where('type', 'revenue')->get();
        $expenses = Account::where('company_id', $companyId)->where('type', 'expense')->get();

        $totalRevenue = 0;
        $totalExpense = 0;

        foreach ($revenues as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->whereBetween('transaction_date', [$startDate, $endDate]);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $acc->period_balance = ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalRevenue += $acc->period_balance;
        }

        foreach ($expenses as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->whereBetween('transaction_date', [$startDate, $endDate]);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $acc->period_balance = ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
            $totalExpense += $acc->period_balance;
        }
        $netProfit = $totalRevenue - $totalExpense;

        // --- BALANCE SHEET DATA ---
        $assets = Account::where('company_id', $companyId)->where('type', 'asset')->get();
        $liabilities = Account::where('company_id', $companyId)->where('type', 'liability')->get();
        $equities = Account::where('company_id', $companyId)->where('type', 'equity')->get();

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        foreach ($assets as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
            $totalAssets += $acc->as_of_balance;
        }

        foreach ($liabilities as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalLiabilities += $acc->as_of_balance;
        }

        foreach ($equities as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalEquity += $acc->as_of_balance;
        }

        $histRevenue = 0;
        $histExpense = 0;
        foreach ($revenues as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $histRevenue += ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
        }
        foreach ($expenses as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate);
                })->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
            $histExpense += ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
        }

        $retainedEarnings = $histRevenue - $histExpense;
        $totalEquityAndRetained = $totalEquity + $retainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityAndRetained;

        $data = compact(
            'startDate', 'endDate', 'asOfDate',
            'revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netProfit',
            'assets', 'liabilities', 'equities', 
            'totalAssets', 'totalLiabilities', 'totalEquity', 
            'retainedEarnings', 'totalEquityAndRetained', 'totalLiabilitiesAndEquity'
        );

        if ($request->has('export') && $request->export == 'excel') {
            return Excel::download(new class($data) implements FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): View { return view('client.reports.exports.index', $this->data); }
            }, 'Laporan_Keuangan_Terpadu_' . $asOfDate . '.xlsx');
        }

        if ($request->has('export') && $request->export == 'csv') {
            $headers = ['Kategori', 'Kode Akun', 'Nama Akun', 'Saldo'];
            $rows = [];
            $rows[] = ['=== PENDAPATAN ===', '', '', ''];
            foreach ($revenues as $acc) { $rows[] = ['Pendapatan', $acc->code, $acc->name, $acc->period_balance ?? 0]; }
            $rows[] = ['', '', 'Total Pendapatan', $totalRevenue];
            $rows[] = ['=== BEBAN ===', '', '', ''];
            foreach ($expenses as $acc) { $rows[] = ['Beban', $acc->code, $acc->name, $acc->period_balance ?? 0]; }
            $rows[] = ['', '', 'Total Beban', $totalExpense];
            $rows[] = ['', '', 'LABA/RUGI BERSIH', $netProfit];
            $rows[] = [''];
            $rows[] = ['=== ASET ===', '', '', ''];
            foreach ($assets as $acc) { $rows[] = ['Aset', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Total Aset', $totalAssets];
            $rows[] = ['=== KEWAJIBAN ===', '', '', ''];
            foreach ($liabilities as $acc) { $rows[] = ['Kewajiban', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Total Kewajiban', $totalLiabilities];
            $rows[] = ['=== EKUITAS ===', '', '', ''];
            foreach ($equities as $acc) { $rows[] = ['Ekuitas', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Laba Ditahan', $retainedEarnings];
            $rows[] = ['', '', 'Total Ekuitas + Laba Ditahan', $totalEquityAndRetained];
            return \App\Helpers\CsvExporter::download('Laporan_Keuangan_' . $asOfDate . '.csv', $headers, $rows);
        }

        return view('client.reports.integrated', $data);
    }

    public function profitLoss(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $companyId = auth()->user()->company_id;

        // Ambil semua akun Pendapatan (Revenue)
        $revenues = Account::where('company_id', $companyId)->where('type', 'revenue')->get();
        // Ambil semua akun Biaya (Expense)
        $expenses = Account::where('company_id', $companyId)->where('type', 'expense')->get();

        $totalRevenue = 0;
        $totalExpense = 0;

        // Hitung mutasi per akun untuk rentang tanggal tersebut
        foreach ($revenues as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->whereBetween('transaction_date', [$startDate, $endDate]);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
                
            // Revenue normal balance is Credit
            $acc->period_balance = ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalRevenue += $acc->period_balance;
        }

        foreach ($expenses as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $startDate, $endDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->whereBetween('transaction_date', [$startDate, $endDate]);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
                
            // Expense normal balance is Debit
            $acc->period_balance = ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
            $totalExpense += $acc->period_balance;
        }

        $netProfit = $totalRevenue - $totalExpense;

        $data = compact('revenues', 'expenses', 'totalRevenue', 'totalExpense', 'netProfit', 'startDate', 'endDate');

        // Export Excel (Maatwebsite)
        if ($request->has('export') && $request->export == 'excel') {
            return Excel::download(new class($data) implements FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): View { return view('client.reports.exports.profit_loss', $this->data); }
            }, 'Laba_Rugi_' . $startDate . '_sd_' . $endDate . '.xlsx');
        }

        // Export CSV (Alternatif)
        if ($request->has('export') && $request->export == 'csv') {
            $headers = ['Kategori', 'Kode Akun', 'Nama Akun', 'Saldo Periode'];
            $rows = [];
            $rows[] = ['=== PENDAPATAN ===', '', '', ''];
            foreach ($revenues as $acc) { $rows[] = ['Pendapatan', $acc->code, $acc->name, $acc->period_balance ?? 0]; }
            $rows[] = ['', '', 'Total Pendapatan', $totalRevenue];
            $rows[] = ['=== BEBAN ===', '', '', ''];
            foreach ($expenses as $acc) { $rows[] = ['Beban', $acc->code, $acc->name, $acc->period_balance ?? 0]; }
            $rows[] = ['', '', 'Total Beban', $totalExpense];
            $rows[] = ['', '', 'LABA/RUGI BERSIH', $netProfit];
            return \App\Helpers\CsvExporter::download('Laba_Rugi_' . $startDate . '_sd_' . $endDate . '.csv', $headers, $rows);
        }

        return view('client.reports.profit_loss', $data);
    }

    public function sptPpn(Request $request)
    {
        return view('client.reports.spt_ppn');
    }

    public function taxCalculator(Request $request)
    {
        return view('client.reports.tax_calculator');
    }

    public function balanceSheet(Request $request)
    {
        $asOfDate = $request->input('as_of_date', Carbon::now()->format('Y-m-d'));
        $companyId = auth()->user()->company_id;

        // Ambil akun Aset, Kewajiban, Ekuitas
        $assets = Account::where('company_id', $companyId)->where('type', 'asset')->get();
        $liabilities = Account::where('company_id', $companyId)->where('type', 'liability')->get();
        $equities = Account::where('company_id', $companyId)->where('type', 'equity')->get();

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        // Hitung Saldo Aset (sampai tanggal As Of)
        foreach ($assets as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->where('transaction_date', '<=', $asOfDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            // Asset normal balance is Debit
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
            $totalAssets += $acc->as_of_balance;
        }

        // Hitung Saldo Kewajiban (sampai tanggal As Of)
        foreach ($liabilities as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->where('transaction_date', '<=', $asOfDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            // Liability normal balance is Credit
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalLiabilities += $acc->as_of_balance;
        }

        // Hitung Saldo Ekuitas (sampai tanggal As Of)
        foreach ($equities as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->where('transaction_date', '<=', $asOfDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            
            // Equity normal balance is Credit
            $acc->as_of_balance = $acc->opening_balance + ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
            $totalEquity += $acc->as_of_balance;
        }

        // Kalkulasi Laba Ditahan (Retained Earnings) dari awal sampai As Of Date
        $revenues = Account::where('company_id', $companyId)->where('type', 'revenue')->get();
        $expenses = Account::where('company_id', $companyId)->where('type', 'expense')->get();
        
        $histRevenue = 0;
        $histExpense = 0;

        foreach ($revenues as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->where('transaction_date', '<=', $asOfDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            $histRevenue += ($mutations->total_credit ?? 0) - ($mutations->total_debit ?? 0);
        }

        foreach ($expenses as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', function($q) use ($companyId, $asOfDate) {
                    $q->where('company_id', $companyId)
                      ->where('status', 'posted')
                      ->where('transaction_date', '<=', $asOfDate);
                })
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            $histExpense += ($mutations->total_debit ?? 0) - ($mutations->total_credit ?? 0);
        }

        $retainedEarnings = $histRevenue - $histExpense;
        $totalEquityAndRetained = $totalEquity + $retainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityAndRetained;

        $data = compact(
            'assets', 'liabilities', 'equities', 
            'totalAssets', 'totalLiabilities', 'totalEquity', 
            'retainedEarnings', 'totalEquityAndRetained', 'totalLiabilitiesAndEquity',
            'asOfDate'
        );

        // Export Excel (Maatwebsite)
        if ($request->has('export') && $request->export == 'excel') {
            return Excel::download(new class($data) implements FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): View { return view('client.reports.exports.balance_sheet', $this->data); }
            }, 'Neraca_' . $asOfDate . '.xlsx');
        }

        // Export CSV (Alternatif)
        if ($request->has('export') && $request->export == 'csv') {
            $headers = ['Kategori', 'Kode Akun', 'Nama Akun', 'Saldo'];
            $rows = [];
            $rows[] = ['=== ASET ===', '', '', ''];
            foreach ($assets as $acc) { $rows[] = ['Aset', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Total Aset', $totalAssets];
            $rows[] = ['=== KEWAJIBAN ===', '', '', ''];
            foreach ($liabilities as $acc) { $rows[] = ['Kewajiban', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Total Kewajiban', $totalLiabilities];
            $rows[] = ['=== EKUITAS ===', '', '', ''];
            foreach ($equities as $acc) { $rows[] = ['Ekuitas', $acc->code, $acc->name, $acc->as_of_balance ?? 0]; }
            $rows[] = ['', '', 'Laba Ditahan', $retainedEarnings];
            $rows[] = ['', '', 'Total Ekuitas + Laba Ditahan', $totalEquityAndRetained];
            $rows[] = ['', '', 'Total Kewajiban + Ekuitas', $totalLiabilitiesAndEquity];
            return \App\Helpers\CsvExporter::download('Neraca_' . $asOfDate . '.csv', $headers, $rows);
        }

        return view('client.reports.balance_sheet', $data);
    }

    public function generalLedger(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $accountId = $request->input('account_id');

        $accounts = Account::where('company_id', $companyId)->orderBy('code')->get();
        $ledgerData = collect();

        $query = Account::where('company_id', $companyId)->orderBy('code');
        if ($accountId) $query->where('id', $accountId);
        $filteredAccounts = $query->get();

        foreach ($filteredAccounts as $acc) {
            $details = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', fn($q) => $q->where('company_id', $companyId)->where('status', 'posted')->whereBetween('transaction_date', [$startDate, $endDate]))
                ->with(['journal'])
                ->get()
                ->sortBy(fn($d) => $d->journal->transaction_date);

            if ($details->count() > 0) {
                $ledgerData->push(['account' => $acc, 'details' => $details]);
            }
        }

        return view('client.reports.general_ledger', compact('accounts', 'ledgerData', 'startDate', 'endDate', 'accountId'));
    }

    public function trialBalance(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $asOfDate = $request->input('as_of_date', Carbon::now()->format('Y-m-d'));

        $accounts = Account::where('company_id', $companyId)->orderBy('code')->get();
        $trialData = collect();
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($accounts as $acc) {
            $mutations = JournalDetail::where('account_id', $acc->id)
                ->whereHas('journal', fn($q) => $q->where('company_id', $companyId)->where('status', 'posted')->where('transaction_date', '<=', $asOfDate))
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $debit = $mutations->total_debit ?? 0;
            $credit = $mutations->total_credit ?? 0;

            $isDebitNormal = in_array($acc->type, ['asset', 'expense']);
            $balance = $isDebitNormal ? ($debit - $credit) : ($credit - $debit);

            if ($balance != 0) {
                $balanceDebit = $isDebitNormal ? abs($balance) : 0;
                $balanceCredit = !$isDebitNormal ? abs($balance) : 0;
                if ($balance < 0) { $balanceDebit = !$isDebitNormal ? 0 : 0; $balanceCredit = $isDebitNormal ? 0 : 0; $balanceDebit = $isDebitNormal ? 0 : abs($balance); $balanceCredit = !$isDebitNormal ? 0 : abs($balance); }

                $saldoDebit = $balance > 0 && $isDebitNormal ? $balance : (!$isDebitNormal && $balance < 0 ? abs($balance) : 0);
                $saldoCredit = $balance > 0 && !$isDebitNormal ? $balance : ($isDebitNormal && $balance < 0 ? abs($balance) : 0);

                $totalDebit += $saldoDebit;
                $totalCredit += $saldoCredit;

                $trialData->push([
                    'account' => $acc,
                    'debit' => $debit,
                    'credit' => $credit,
                    'saldo_debit' => $saldoDebit,
                    'saldo_credit' => $saldoCredit,
                ]);
            }
        }

        return view('client.reports.trial_balance', compact('trialData', 'asOfDate', 'totalDebit', 'totalCredit'));
    }

    public function receivableAging(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $invoices = \App\Models\SalesInvoice::where('company_id', $companyId)
            ->where('status', '!=', 'paid')
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $agingData = [];
        foreach ($invoices as $inv) {
            $dueDate = Carbon::parse($inv->due_date);
            $daysOverdue = $dueDate->isPast() ? Carbon::now()->diffInDays($dueDate) : 0;
            $bucket = $daysOverdue == 0 ? 'Belum Jatuh Tempo' : ($daysOverdue <= 30 ? '1-30 hari' : ($daysOverdue <= 60 ? '31-60 hari' : ($daysOverdue <= 90 ? '61-90 hari' : '> 90 hari')));
            $agingData[] = ['invoice' => $inv, 'days_overdue' => $daysOverdue, 'bucket' => $bucket];
        }

        return view('client.reports.receivable_aging', compact('agingData'));
    }

    public function receivableSummary(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $customers = \App\Models\Customer::where('company_id', $companyId)
            ->withCount(['salesInvoices as total_piutang' => fn($q) => $q->where('status', '!=', 'paid')->selectRaw('COALESCE(SUM(grand_total), 0)')])
            ->get()
            ->filter(fn($c) => $c->total_piutang > 0)
            ->sortByDesc('total_piutang');

        $grandTotal = $customers->sum('total_piutang');
        return view('client.reports.receivable_summary', compact('customers', 'grandTotal'));
    }

    public function salesReport(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $invoices = \App\Models\SalesInvoice::where('company_id', $companyId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->with('customer')
            ->orderBy('invoice_date', 'desc')
            ->get();

        $totalAmount = $invoices->sum('grand_total');
        $totalPaid = $invoices->where('status', 'paid')->sum('grand_total');
        $totalUnpaid = $invoices->where('status', '!=', 'paid')->sum('grand_total');

        return view('client.reports.sales_report', compact('invoices', 'startDate', 'endDate', 'totalAmount', 'totalPaid', 'totalUnpaid'));
    }

    public function payableAging(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $invoices = \App\Models\PurchaseInvoice::where('company_id', $companyId)
            ->where('status', '!=', 'paid')
            ->with('supplier')
            ->orderBy('due_date')
            ->get();

        $agingData = [];
        foreach ($invoices as $inv) {
            $dueDate = Carbon::parse($inv->due_date);
            $daysOverdue = $dueDate->isPast() ? Carbon::now()->diffInDays($dueDate) : 0;
            $bucket = $daysOverdue == 0 ? 'Belum Jatuh Tempo' : ($daysOverdue <= 30 ? '1-30 hari' : ($daysOverdue <= 60 ? '31-60 hari' : ($daysOverdue <= 90 ? '61-90 hari' : '> 90 hari')));
            $agingData[] = ['invoice' => $inv, 'days_overdue' => $daysOverdue, 'bucket' => $bucket];
        }

        return view('client.reports.payable_aging', compact('agingData'));
    }

    public function payableSummary(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $suppliers = \App\Models\Supplier::where('company_id', $companyId)
            ->withCount(['purchaseInvoices as total_hutang' => fn($q) => $q->where('status', '!=', 'paid')->selectRaw('COALESCE(SUM(grand_total), 0)')])
            ->get()
            ->filter(fn($s) => $s->total_hutang > 0)
            ->sortByDesc('total_hutang');

        $grandTotal = $suppliers->sum('total_hutang');
        return view('client.reports.payable_summary', compact('suppliers', 'grandTotal'));
    }

    public function purchaseReport(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        $invoices = \App\Models\PurchaseInvoice::where('company_id', $companyId)
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->with('supplier')
            ->orderBy('invoice_date', 'desc')
            ->get();

        $totalAmount = $invoices->sum('grand_total');
        $totalPaid = $invoices->where('status', 'paid')->sum('grand_total');
        $totalUnpaid = $invoices->where('status', '!=', 'paid')->sum('grand_total');

        return view('client.reports.purchase_report', compact('invoices', 'startDate', 'endDate', 'totalAmount', 'totalPaid', 'totalUnpaid'));
    }

    public function inventoryStock(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $products = \App\Models\Product::where('company_id', $companyId)
            ->where('type', 'goods')
            ->with('category')
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $product->current_stock = $product->stock;
        }

        return view('client.reports.inventory_stock', compact('products'));
    }
}
