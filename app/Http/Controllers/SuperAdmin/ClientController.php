<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['all', 'active', 'trial', 'expiring', 'inactive'], true) ? $request->query('tab') : 'all';
        $q = trim((string) $request->query('q', ''));
        $today = now()->toDateString();
        $soon = now()->addDays(14)->toDateString();

        $all = Company::with(['users', 'subscriptions' => fn ($s) => $s->orderByDesc('end_date')->with('plan')])
            ->withCount(['users'])->orderByDesc('created_at')->get()
            ->map(function ($c) use ($today) {
                $c->cur = $c->subscriptions->first(fn ($s) => in_array($s->status, ['active', 'trial'], true));
                $c->state = ! $c->is_premium ? 'inactive' : ($c->cur && $c->cur->status === 'trial' ? 'trial' : 'active');
                $c->days_left = $c->cur ? (int) now()->startOfDay()->diffInDays($c->cur->end_date, false) : null;
                $c->owner = $c->users->sortBy('id')->first();

                return $c;
            });
        $pendingByCompany = \App\Models\SubscriptionPayment::where('status', 'pending')->pluck('company_id')->flip();
        $counts = [
            'all' => $all->count(),
            'active' => $all->where('state', 'active')->count(),
            'trial' => $all->where('state', 'trial')->count(),
            'expiring' => $all->filter(fn ($c) => $c->state !== 'inactive' && $c->days_left !== null && $c->days_left <= 14)->count(),
            'inactive' => $all->where('state', 'inactive')->count(),
        ];
        $clients = $all->filter(function ($c) use ($tab, $q) {
            if ($tab === 'expiring' && ! ($c->state !== 'inactive' && $c->days_left !== null && $c->days_left <= 14)) return false;
            if (in_array($tab, ['active', 'trial', 'inactive'], true) && $c->state !== $tab) return false;
            if ($q !== '') {
                $hay = mb_strtolower($c->name . ' ' . $c->email . ' ' . optional($c->owner)->email . ' ' . optional($c->owner)->name);
                if (! str_contains($hay, mb_strtolower($q))) return false;
            }

            return true;
        })->values();
        $plans = \App\Models\Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price')->get();

        return view('superadmin.clients.index', compact('clients', 'plans', 'tab', 'q', 'counts', 'pendingByCompany'));
    }

    public function updateSubscription(Request $request, Company $company)
    {
        $request->validate([
            'status' => 'required|in:trial,active,expired,cancelled',
            'end_date' => 'required|date',
        ]);
        $subscription = $company->subscriptions()->orderBy('end_date', 'desc')->first();
        if (! $subscription) {
            return back()->with('error', 'Perusahaan ini belum punya langganan. Gunakan "Aktifkan / Perpanjang".');
        }
        $subscription->update(['status' => $request->status, 'end_date' => $request->end_date]);
        $active = in_array($request->status, ['active', 'trial'], true) && $request->end_date >= now()->subDays(\App\Services\SubscriptionService::GRACE_DAYS)->toDateString();
        $company->forceFill(['is_premium' => $active])->save();

        return back()->with('success', 'Langganan ' . $company->name . ' diperbarui.');
    }

    /** Aktifkan / perpanjang langganan manual (mis. pembayaran di luar aplikasi atau trial khusus). */
    public function grantPremium(Request $request, Company $company)
    {
        \App\Support\Money::normalize($request, ['amount_paid']);
        $v = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'status' => 'required|in:trial,active',
            'months' => 'required|integer|min:1|max:36',
            'amount_paid' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);
        $plan = \App\Models\Plan::findOrFail($v['plan_id']);
        $sub = \App\Services\SubscriptionService::activate($company, $plan, (int) $v['months'], (float) ($v['amount_paid'] ?? 0), $v['status'],
            trim('Diaktifkan manual oleh ' . auth()->user()->name . (! empty($v['notes']) ? ' — ' . $v['notes'] : '')));

        return back()->with('success', "{$company->name}: paket {$plan->name} aktif s/d " . $sub->end_date->format('d M Y') . '.');
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
