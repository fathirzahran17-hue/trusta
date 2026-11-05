<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menerapkan fitur yang dijanjikan paket langganan: laporan, kas dan bank, serta ekspor.
 * Perusahaan tanpa langganan berjalan (mis. premium yang diatur manual) tidak dibatasi.
 */
class EnforcePlanFeatures
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;
        $plan = $company ? SubscriptionService::current($company)?->plan : null;
        if (! $plan) {
            return $next($request);
        }

        if ($request->filled('export') && $request->isMethod('get') && ! $plan->has_export) {
            return back()->with('error', "Paket {$plan->name} belum termasuk ekspor Excel, CSV, dan PDF.")->with('premium_denied', true);
        }
        $missing = match (true) {
            $request->routeIs('client.reports.*') && ! $plan->has_reports => 'laporan keuangan',
            $request->routeIs('client.cash-bank.*') && ! $plan->has_cash_bank => 'kas dan bank',
            default => null,
        };
        if ($missing) {
            return redirect()->route('client.dashboard')->with('error', "Paket {$plan->name} belum termasuk fitur {$missing}.")->with('premium_denied', true);
        }

        return $next($request);
    }
}
