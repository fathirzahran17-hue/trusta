<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $live = Subscription::with(['plan', 'company'])->whereIn('status', ['active', 'trial'])->where('end_date', '>=', $today)->get();
        $paid = $live->where('status', 'active');
        // MRR: nilai bulanan langganan berbayar yang sedang berjalan (jumlah dibayar / lama bulan)
        $mrr = $paid->sum(function ($s) {
            $months = max(1, (int) round($s->start_date->diffInDays($s->end_date) / 30.4));
            return (float) $s->amount_paid > 0 ? (float) $s->amount_paid / $months : (float) optional($s->plan)->price;
        });

        $stats = [
            'companies' => Company::count(),
            'new_month' => Company::where('created_at', '>=', $monthStart)->count(),
            'users' => User::whereNotNull('company_id')->count(),
            'paid' => $paid->unique('company_id')->count(),
            'trial' => $live->where('status', 'trial')->unique('company_id')->count(),
            'inactive' => Company::where('is_premium', false)->count(),
            'mrr' => round($mrr, 2),
            'revenue_month' => (float) SubscriptionPayment::where('status', 'approved')->where('reviewed_at', '>=', $monthStart)->sum('amount')
                + (float) Subscription::whereNull('payment_id')->where('status', 'active')->where('created_at', '>=', $monthStart)->sum('amount_paid'),
            'pending' => SubscriptionPayment::where('status', 'pending')->count(),
        ];

        $expiring = $live->filter(fn ($s) => $s->end_date->lte(now()->addDays(14)))->sortBy('end_date')->unique('company_id')->take(8)->values();
        $pendingPayments = SubscriptionPayment::with(['company', 'plan'])->where('status', 'pending')->orderBy('id')->take(6)->get();
        $recent = Company::withCount('users')->orderByDesc('created_at')->take(8)->get();
        $byType = Company::selectRaw("COALESCE(business_type, 'dagang_jasa') t, COUNT(*) n")->groupBy('t')->pluck('n', 't');
        $byPlan = $live->groupBy(fn ($s) => optional($s->plan)->name ?? '-')->map->count()->sortDesc();

        return view('superadmin.dashboard.index', compact('stats', 'expiring', 'pendingPayments', 'recent', 'byType', 'byPlan'));
    }
}
