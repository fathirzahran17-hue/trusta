<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPremium
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // If user is super_admin, let them pass
        if ($user && $user->role === 'super_admin') {
            return $next($request);
        }

        // If user is client and company is not premium
        if ($user && $user->company && !$user->company->is_premium) {
            // Block all actions (POST, PUT, DELETE) except logout
            if (!$request->isMethod('get') && !$request->is('logout')) {
                return redirect()->back()->with('error', 'AKSES DITOLAK: Akun Anda belum berstatus Premium.')->with('premium_denied', true);
            }
        }

        return $next($request);
    }
}
