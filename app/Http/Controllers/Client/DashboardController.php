<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\CashTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $today = now()->format('Y-m-d');
        $thisYear = now()->year;
        
        // 1. Aktifitas Terakhir
        $recentActivities = Journal::where('company_id', $companyId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 2. Kegiatan Mendatang
        $upcomingEvents = PurchaseInvoice::with('supplier')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['paid', 'void'])
            ->whereBetween('due_date', [$today, now()->addDays(7)->format('Y-m-d')])
            ->orderBy('due_date', 'asc')
            ->get();

        // 3. Tren Penjualan (Last 7 Days)
        $salesTrend = [];
        $salesTrendLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $salesTrendLabels[] = $date->translatedFormat('D');
            
            $totalSales = SalesInvoice::where('company_id', $companyId)
                ->whereDate('invoice_date', $date->format('Y-m-d'))
                ->where('status', '!=', 'void')
                ->sum('total');
            $salesTrend[] = $totalSales;
        }

        // 4. Laba / Rugi Tahun Ini
        // Using Journal details
        $revenue = JournalDetail::whereHas('account', function($q) use ($companyId) {
                $q->where('company_id', $companyId)->where('type', 'revenue');
            })
            ->whereHas('journal', function($q) use ($companyId, $thisYear) {
                $q->where('company_id', $companyId)->where('status', 'posted')
                  ->whereYear('transaction_date', $thisYear);
            })
            ->selectRaw('COALESCE(SUM(credit) - SUM(debit), 0) as total')->value('total');

        $cogs = JournalDetail::whereHas('account', function($q) use ($companyId) {
                $q->where('company_id', $companyId)->where('type', 'cost_of_sales');
            })
            ->whereHas('journal', function($q) use ($companyId, $thisYear) {
                $q->where('company_id', $companyId)->where('status', 'posted')
                  ->whereYear('transaction_date', $thisYear);
            })
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as total')->value('total');

        $expense = JournalDetail::whereHas('account', function($q) use ($companyId) {
                $q->where('company_id', $companyId)->whereIn('type', ['expense', 'other_expense']);
            })
            ->whereHas('journal', function($q) use ($companyId, $thisYear) {
                $q->where('company_id', $companyId)->where('status', 'posted')
                  ->whereYear('transaction_date', $thisYear);
            })
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as total')->value('total');
            
        $netProfit = $revenue - $cogs - $expense;

        // 5. Arus Kas (Last 7 Days)
        $cashFlowIn = [];
        $cashFlowOut = [];
        $cashFlowLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateStr = now()->subDays($i)->format('Y-m-d');
            $cashFlowLabels[] = now()->subDays($i)->format('d M');
            
            $in = CashTransaction::where('company_id', $companyId)
                ->where('transaction_date', $dateStr)
                ->where('type', 'in')
                ->sum('amount');
            $cashFlowIn[] = $in;
            
            $out = CashTransaction::where('company_id', $companyId)
                ->where('transaction_date', $dateStr)
                ->where('type', 'out')
                ->sum('amount');
            $cashFlowOut[] = -$out; // Negative for chart
        }

        // 6. Beban Perusahaan
        $topExpenses = JournalDetail::join('accounts', 'journal_details.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->where('accounts.company_id', $companyId)
            ->where('journals.company_id', $companyId)
            ->whereIn('accounts.type', ['expense', 'other_expense'])
            ->where('journals.status', 'posted')
            ->whereYear('journals.transaction_date', $thisYear)
            ->select('accounts.name', DB::raw('SUM(journal_details.debit) - SUM(journal_details.credit) as total'))
            ->groupBy('accounts.name', 'accounts.id')
            ->having('total', '>', 0)
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get();

        // 7. Penjualan Overview
        $salesOverview = [
            'total' => SalesInvoice::where('company_id', $companyId)->where('status', '!=', 'void')->sum('total'),
            'unpaid' => SalesInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->sum('amount_due'),
            'paid' => SalesInvoice::where('company_id', $companyId)->where('status', '!=', 'void')->sum('amount_paid'),
            'not_due' => SalesInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '>=', $today)->sum('amount_due'),
            'overdue' => SalesInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '<', $today)->sum('amount_due'),
        ];

        // 8. Pembelian Overview
        $purchaseOverview = [
            'total' => PurchaseInvoice::where('company_id', $companyId)->where('status', '!=', 'void')->sum('total'),
            'unpaid' => PurchaseInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->sum('amount_due'),
            'paid' => PurchaseInvoice::where('company_id', $companyId)->where('status', '!=', 'void')->sum('amount_paid'),
            'not_due' => PurchaseInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '>=', $today)->sum('amount_due'),
            'overdue' => PurchaseInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '<', $today)->sum('amount_due'),
        ];

        return view('client.dashboard.index', compact(
            'recentActivities', 'upcomingEvents', 
            'salesTrend', 'salesTrendLabels',
            'revenue', 'cogs', 'expense', 'netProfit',
            'cashFlowIn', 'cashFlowOut', 'cashFlowLabels',
            'topExpenses', 'salesOverview', 'purchaseOverview'
        ));
    }
}
