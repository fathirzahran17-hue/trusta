<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Company::with(['users', 'subscriptions' => function($q) {
            $q->orderBy('end_date', 'desc')->with('plan');
        }])->orderBy('created_at', 'desc')->get();

        $plans = \App\Models\Plan::where('is_active', true)->get();

        return view('superadmin.clients.index', compact('clients', 'plans'));
    }

    public function updateSubscription(Request $request, Company $company)
    {
        $request->validate([
            'status' => 'required|in:trial,active,expired,cancelled',
            'end_date' => 'required|date',
        ]);

        // Cari subscription terakhir perusahaan ini
        $subscription = $company->subscriptions()->orderBy('end_date', 'desc')->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => $request->status,
                'end_date' => $request->end_date,
            ]);
            
            if ($request->status === 'expired' || $request->status === 'cancelled') {
                $company->update(['is_premium' => false]);
            } else {
                $company->update(['is_premium' => true]);
            }
        }

        return redirect()->route('superadmin.clients.index')->with('success', 'Status langganan berhasil diperbarui.');
    }

    public function grantPremium(Request $request, Company $company)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active',
            'end_date' => 'required|date',
        ]);

        // Nonaktifkan subscription lama jika ada
        $company->subscriptions()->update(['status' => 'cancelled']);

        // Buat subscription baru
        $company->subscriptions()->create([
            'plan_id' => $request->plan_id,
            'start_date' => now()->toDateString(),
            'end_date' => $request->end_date,
            'status' => $request->status,
        ]);

        $company->update(['is_premium' => true]);

        return redirect()->back()->with('success', "Premium berhasil diaktifkan untuk perusahaan {$company->name}.");
    }

    public function revokePremium(Company $company)
    {
        $company->update(['is_premium' => false]);
        $company->subscriptions()->update(['status' => 'cancelled']);

        return redirect()->back()->with('success', "Premium berhasil dicabut untuk perusahaan {$company->name}.");
    }

    public function resetPassword(Request $request, Company $company)
    {
        $request->validate([
            'new_password' => 'required|string|min:8',
        ]);

        $mainUser = $company->users()->orderBy('id')->first();
        if ($mainUser) {
            $mainUser->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password)
            ]);
            return redirect()->back()->with('success', "Password untuk akun utama {$company->name} ({$mainUser->email}) berhasil direset.");
        }

        return redirect()->back()->with('error', "Gagal: Perusahaan ini belum memiliki akun pengguna yang terdaftar.");
    }

    public function destroy(Company $company)
    {
        $companyName = $company->name;

        try {
            \App\Services\CompanyPurger::purge((int) $company->id);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('superadmin.clients.index')->with('error', "Gagal menghapus \"{$companyName}\": " . $e->getMessage());
        }

        return redirect()->route('superadmin.clients.index')->with('success', "Perusahaan \"{$companyName}\" beserta seluruh datanya telah berhasil dihapus.");
    }
}
