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
    /** Periode dashboard: pilihan cepat atau rentang tanggal; diingat selama sesi. */
    private function period(Request $request): array
    {
        $presets = ['today' => 'Hari ini', '7d' => '7 hari terakhir', '30d' => '30 hari terakhir', 'month' => 'Bulan ini', 'last_month' => 'Bulan lalu', 'quarter' => 'Kuartal ini', 'year' => 'Tahun ini', 'custom' => 'Rentang tanggal'];
        $in = $request->only(['period', 'from', 'to']);
        if (! empty($in['period'])) {
            session(['dash_period' => $in]);
        } else {
            $in = session('dash_period', ['period' => 'month']);
        }
        $key = array_key_exists($in['period'] ?? '', $presets) ? $in['period'] : 'month';
        $t = Carbon::today();
        [$from, $to] = match ($key) {
            'today' => [$t->copy(), $t->copy()],
            '7d' => [$t->copy()->subDays(6), $t->copy()],
            '30d' => [$t->copy()->subDays(29), $t->copy()],
            'last_month' => [$t->copy()->subMonthNoOverflow()->startOfMonth(), $t->copy()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$t->copy()->firstOfQuarter(), $t->copy()->lastOfQuarter()],
            'year' => [$t->copy()->startOfYear(), $t->copy()->endOfYear()],
            'custom' => [rescue(fn () => Carbon::parse($in['from'] ?? ''), $t->copy()->startOfMonth(), false), rescue(fn () => Carbon::parse($in['to'] ?? ''), $t->copy(), false)],
            default => [$t->copy()->startOfMonth(), $t->copy()->endOfMonth()],
        };
        if ($from->gt($to)) [$from, $to] = [$to, $from];
        $label = $key === 'custom' || in_array($key, ['month', 'last_month', 'quarter', 'year'], true)
            ? $presets[$key] . ' (' . $from->translatedFormat('d M') . ' – ' . $to->translatedFormat('d M Y') . ')'
            : $presets[$key];

        return ['key' => $key, 'from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'label' => $label, 'presets' => $presets];
    }

    /** Kelompok waktu grafik: harian untuk ≤ 45 hari, bulanan untuk lebih panjang. */
    private function buckets(Carbon $from, Carbon $to): array
    {
        $daily = $from->diffInDays($to) <= 45;
        $out = [];
        $c = $from->copy()->startOfDay();
        while ($c->lte($to)) {
            $k = $daily ? $c->format('Y-m-d') : $c->format('Y-m');
            $out[$k] = $daily ? ($from->diffInDays($to) <= 7 ? $c->translatedFormat('D d') : $c->format('d M')) : $c->translatedFormat('M Y');
            $daily ? $c->addDay() : $c->addMonthNoOverflow()->startOfMonth();
        }

        return [$daily, $out];
    }

    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $today = now()->format('Y-m-d');
        $period = $this->period($request);
        $from = $period['from']->toDateString();
        $to = $period['to']->toDateString();
        [$daily, $buckets] = $this->buckets($period['from'], $period['to']);
        $bucketOf = fn ($date) => $daily ? Carbon::parse($date)->format('Y-m-d') : Carbon::parse($date)->format('Y-m');

        // 1. Aktivitas terakhir (dengan nama dokumen asalnya)
        $recentActivities = Journal::where('company_id', $companyId)->orderBy('created_at', 'desc')->take(6)->get()
            ->map(function ($j) {
                $src = \App\Services\DocumentLinker::sourceOfJournal($j);
                $j->act_label = $src['label'] ?? (str_starts_with((string) $j->ref_number, 'VOID-') ? 'Pembatalan' : 'Jurnal');
                $j->act_url = $src['url'] ?? route('client.journals.show', $j);

                return $j;
            });

        // 2. Kegiatan mendatang (14 hari): piutang yang perlu ditagih, hutang yang perlu dibayar, langganan berakhir
        $until = now()->addDays(14)->format('Y-m-d');
        $upcomingEvents = collect();
        foreach (SalesInvoice::with('customer')->where('company_id', $companyId)->whereIn('status', ['unpaid', 'partial'])->where('amount_due', '>', 0)
                     ->whereBetween('due_date', [$today, $until])->get() as $i) {
            $upcomingEvents->push(['date' => $i->due_date, 'kind' => 'ar', 'title' => 'Tagih piutang · ' . optional($i->customer)->name, 'number' => $i->invoice_number,
                'amount' => (float) $i->amount_due, 'url' => route('client.sales-invoices.show', $i)]);
        }
        foreach (PurchaseInvoice::with('supplier')->where('company_id', $companyId)->whereIn('status', ['unpaid', 'partial'])->where('amount_due', '>', 0)
                     ->whereBetween('due_date', [$today, $until])->get() as $i) {
            $upcomingEvents->push(['date' => $i->due_date, 'kind' => 'ap', 'title' => 'Bayar hutang · ' . optional($i->supplier)->name, 'number' => $i->invoice_number,
                'amount' => (float) $i->amount_due, 'url' => route('client.purchase-invoices.show', $i)]);
        }
        if (auth()->user()->role === 'client_admin' && ($sub = \App\Services\SubscriptionService::current(auth()->user()->company)) && $sub->end_date->format('Y-m-d') <= $until) {
            $upcomingEvents->push(['date' => $sub->end_date, 'kind' => 'sub', 'title' => 'Langganan TRUSTA berakhir', 'number' => optional($sub->plan)->name, 'amount' => null, 'url' => route('client.billing')]);
        }
        $upcomingEvents = $upcomingEvents->sortBy(fn ($e) => Carbon::parse($e['date'])->format('Y-m-d'))->values();

        // 3. Tren penjualan (per periode)
        $salesTrend = array_fill_keys(array_keys($buckets), 0.0);
        foreach (SalesInvoice::where('company_id', $companyId)->where('status', '!=', 'void')->whereBetween('invoice_date', [$from, $to . ' 23:59:59'])->get(['invoice_date', 'total']) as $inv) {
            $k = $bucketOf($inv->invoice_date);
            if (isset($salesTrend[$k])) $salesTrend[$k] += (float) $inv->total;
        }
        $salesTotal = array_sum($salesTrend);
        $salesTrendLabels = array_values($buckets);
        $salesTrend = array_values($salesTrend);

        // 4. Laba / rugi (per periode)
        $sumType = function (array $types, string $sign) use ($companyId, $from, $to) {
            return (float) JournalDetail::join('accounts', 'accounts.id', '=', 'journal_details.account_id')
                ->join('journals', 'journals.id', '=', 'journal_details.journal_id')
                ->where('journals.company_id', $companyId)->where('journals.status', 'posted')
                ->whereBetween('journals.transaction_date', [$from, $to . ' 23:59:59'])
                ->whereIn('accounts.type', $types)
                ->selectRaw($sign === 'credit' ? 'COALESCE(SUM(credit) - SUM(debit), 0) t' : 'COALESCE(SUM(debit) - SUM(credit), 0) t')->value('t');
        };
        $revenue = $sumType(['revenue', 'other_income'], 'credit');
        $cogs = $sumType(['cost_of_sales'], 'debit');
        $expense = $sumType(['expense', 'other_expense'], 'debit');
        $netProfit = $revenue - $cogs - $expense;

        // 5. Arus kas: semua mutasi akun kas/bank (penerimaan faktur, pembayaran, kas & bank, dll.); transfer antar rekening tidak dihitung
        $cashIds = \App\Models\Account::where('company_id', $companyId)->where('is_cash_bank', true)->pluck('id');
        $flows = JournalDetail::join('journals', 'journals.id', '=', 'journal_details.journal_id')
            ->where('journals.company_id', $companyId)->where('journals.status', 'posted')
            ->whereBetween('journals.transaction_date', [$from, $to . ' 23:59:59'])
            ->whereIn('journal_details.account_id', $cashIds)
            ->groupBy('journals.id', 'journals.transaction_date')
            ->selectRaw('journals.transaction_date d, SUM(journal_details.debit) - SUM(journal_details.credit) net')->get();
        $cashFlowIn = array_fill_keys(array_keys($buckets), 0.0);
        $cashFlowOut = $cashFlowIn;
        foreach ($flows as $f) {
            $k = $bucketOf($f->d);
            if (! isset($cashFlowIn[$k])) continue;
            if ((float) $f->net > 0) $cashFlowIn[$k] += (float) $f->net; else $cashFlowOut[$k] += (float) $f->net;
        }
        $cashTotals = ['in' => array_sum($cashFlowIn), 'out' => -array_sum($cashFlowOut)];
        $cashFlowLabels = array_values($buckets);
        $cashFlowIn = array_values($cashFlowIn);
        $cashFlowOut = array_values($cashFlowOut);

        // 6. Beban Perusahaan
        $topExpenses = JournalDetail::join('accounts', 'journal_details.account_id', '=', 'accounts.id')
            ->join('journals', 'journal_details.journal_id', '=', 'journals.id')
            ->where('accounts.company_id', $companyId)
            ->where('journals.company_id', $companyId)
            ->whereIn('accounts.type', ['expense', 'other_expense'])
            ->where('journals.status', 'posted')
            ->whereBetween('journals.transaction_date', [$from, $to . ' 23:59:59'])
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

        // Peringatan: piutang/hutang jatuh tempo dan stok menipis
        $overdueAr = SalesInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '<', $today);
        $overdueAp = PurchaseInvoice::where('company_id', $companyId)->whereNotIn('status', ['paid', 'void'])->where('due_date', '<', $today);
        $lowStockCount = \App\Models\Product::where('company_id', $companyId)->where('type', 'goods')->where('is_active', true)->get()
            ->filter(fn ($p) => $p->stock <= 5)->count();
        $alerts = [
            'overdue_ar_count' => (clone $overdueAr)->count(),
            'overdue_ar_amount' => (float) (clone $overdueAr)->sum('amount_due'),
            'overdue_ap_count' => (clone $overdueAp)->count(),
            'overdue_ap_amount' => (float) (clone $overdueAp)->sum('amount_due'),
            'low_stock' => $lowStockCount,
        ];

        return view('client.dashboard.index', compact(
            'alerts',
            'recentActivities', 'upcomingEvents', 
            'salesTrend', 'salesTrendLabels',
            'revenue', 'cogs', 'expense', 'netProfit',
            'cashFlowIn', 'cashFlowOut', 'cashFlowLabels', 'cashTotals', 'salesTotal', 'period',
            'topExpenses', 'salesOverview', 'purchaseOverview'
        ));
    }
}
