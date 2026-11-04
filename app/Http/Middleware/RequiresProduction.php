<?php

namespace App\Http\Middleware;

use App\Support\BusinessType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menu Produksi hanya untuk bidang usaha Manufaktur, termasuk bila dibuka langsung lewat alamat. */
class RequiresProduction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! BusinessType::hasProduction(BusinessType::current())) {
            return redirect()->route('client.dashboard')->with('error', 'Menu Produksi hanya tersedia untuk bidang usaha Manufaktur. Bidang usaha bisa diubah di Preferensi.');
        }

        return $next($request);
    }
}
