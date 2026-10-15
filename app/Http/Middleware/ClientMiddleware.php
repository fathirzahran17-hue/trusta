<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Cek apakah user login dan merupakan client
        if (!auth()->check() || !auth()->user()->isClient()) {
            abort(403, 'Akses ditolak. Anda bukan pengguna Client.');
        }

        $user = auth()->user();

        // 2. Cek apakah user punya company (tenant)
        if (!$user->company_id || !$user->company) {
            abort(403, 'Akun Anda tidak terikat dengan perusahaan manapun.');
        }

        // 3. Cek status aktif perusahaan
        if (!$user->company->is_active) {
            abort(403, 'Akses perusahaan Anda sedang dinonaktifkan.');
        }

        return $next($request);
    }
}
