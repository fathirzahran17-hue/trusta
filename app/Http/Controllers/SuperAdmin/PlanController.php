<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

/** Paket langganan: nama, harga bulanan & tahunan, batas, fitur, tampil ke klien atau tidak. */
class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount(['subscriptions as active_count' => fn ($q) => $q->whereIn('status', ['active', 'trial'])->where('end_date', '>=', now()->toDateString())])
            ->orderBy('sort_order')->orderBy('price')->get();

        return view('superadmin.plans.index', compact('plans'));
    }

    private function data(Request $request): array
    {
        \App\Support\Money::normalize($request, ['price', 'price_yearly']);
        $v = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'trial_days' => 'nullable|integer|min:0|max:365',
            'max_users' => 'required|integer|min:1|max:1000',
            'features' => 'nullable|string|max:2000',
            'sort_order' => 'nullable|integer',
        ]);
        foreach (['has_reports', 'has_cash_bank', 'has_export', 'has_auto_backup', 'is_active', 'is_public'] as $b) {
            $v[$b] = $request->boolean($b);
        }
        $v['trial_days'] = (int) ($v['trial_days'] ?? 0);
        $v['sort_order'] = (int) ($v['sort_order'] ?? 0);
        $v['price_yearly'] = ($v['price_yearly'] ?? null) ?: null;

        return $v;
    }

    public function store(Request $request)
    {
        $v = $this->data($request);
        $v += ['max_accounts' => 100000, 'max_transactions' => 1000000];
        Plan::create($v);

        return back()->with('success', 'Paket "' . $v['name'] . '" dibuat.');
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->data($request));

        return back()->with('success', 'Paket "' . $plan->name . '" diperbarui. Harga baru berlaku untuk pembayaran berikutnya.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->subscriptions()->exists() || \App\Models\SubscriptionPayment::where('plan_id', $plan->id)->exists()) {
            $plan->update(['is_active' => false, 'is_public' => false]);

            return back()->with('success', 'Paket "' . $plan->name . '" sudah pernah dipakai, jadi dinonaktifkan (riwayat tetap utuh).');
        }
        $plan->delete();

        return back()->with('success', 'Paket dihapus.');
    }
}
