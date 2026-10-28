<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\SecurityHeaders::class]);
        $middleware->alias([
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'client' => \App\Http\Middleware\ClientMiddleware::class,
            'client_admin' => \App\Http\Middleware\ClientAdminMiddleware::class,
            'premium' => \App\Http\Middleware\CheckPremium::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Hosting gratis (InfinityFree) kadang mencegat kiriman form (POST) untuk verifikasi keamanan dan mengalihkannya
        // ke alamat yang sama sebagai GET dengan "?i=1" -> muncul 405. Data memang belum tersimpan; arahkan kembali ke
        // halaman form dengan pesan yang jelas supaya pengguna tinggal mengirim ulang.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            if (! $request->isMethod('GET') || ! $request->query->has('i') || ! $request->user()) {
                return null;
            }
            $prev = url()->previous();
            $prevPath = parse_url($prev, PHP_URL_PATH) ?: '/';
            $unusable = $prevPath === '/' || $prevPath === '/' . ltrim($request->path(), '/');
            $target = (! $unusable && str_starts_with($prev, $request->getSchemeAndHttpHost()))
                ? $prev
                : $request->getSchemeAndHttpHost() . '/' . implode('/', array_slice(explode('/', trim($request->path(), '/')), 0, -1));

            return redirect($target)->with('error', 'Pengiriman terakhir sempat terputus oleh verifikasi keamanan hosting, sehingga data BELUM tersimpan. Silakan isi dan kirim lagi.');
        });

        // Transaksi di periode Tutup Buku: tampilkan pesan jelas dan kembali ke halaman sebelumnya.
        $exceptions->render(function (\App\Exceptions\PeriodClosedException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
