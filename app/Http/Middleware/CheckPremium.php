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
                return redirect()->back()->with('error', 'AKSES DITOLAK: Akun Anda belum berstatus Premium. <a href="https://wa.me/6285111244106" target="_blank" class="inline-flex items-center ml-2 px-3 py-1 bg-red-700 hover:bg-red-600 text-white text-xs font-semibold rounded-md transition-colors"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>Hubungi Admin</a>');
            }
        }

        return $next($request);
    }
}
