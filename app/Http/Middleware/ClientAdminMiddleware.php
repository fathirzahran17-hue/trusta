<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'client_staff') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Anda tidak memiliki akses ke fitur ini (Hanya Admin).'], 403);
            }
            return redirect()->route('client.dashboard')->with('error', 'Akses Ditolak: Fitur ini hanya dapat diakses oleh Admin Perusahaan.');
        }
        
        return $next($request);
    }
}
