<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;
use App\Models\User;
use App\Models\Subscription;

class DashboardController extends Controller
{
    public function index()
    {
        $totalCompanies = Company::count();
        $totalUsers = User::count();
        
        // Subscription revenue (assuming all successful)
        // Since we don't have a payments table yet, we can sum subscription prices (if any) or just leave it as an example.
        // For MVP, we can sum from the plan relation on active subscriptions.
        $totalRevenue = 0;
        $activeSubs = Subscription::with('plan')->where('status', 'active')->get();
        foreach($activeSubs as $sub) {
            $totalRevenue += $sub->plan->price ?? 0;
        }

        $expiredSubscriptions = Subscription::where('status', 'expired')->orWhere('end_date', '<', now())->count();
        
        $recentCompanies = Company::orderBy('created_at', 'desc')->take(5)->get();

        return view('superadmin.dashboard.index', compact(
            'totalCompanies', 
            'totalUsers', 
            'totalRevenue', 
            'expiredSubscriptions', 
            'recentCompanies'
        ));
    }
}
