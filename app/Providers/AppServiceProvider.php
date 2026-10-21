<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Produksi: jangan pernah menampilkan halaman debug (membocorkan DB host, path, kode) meski .env keliru.
        if (! $this->app->runningInConsole()) {
            $host = request()->getHost();
            $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.test') || str_ends_with($host, '.localhost');
            if (! $isLocal) {
                config(['app.debug' => false]);
            }
            if (request()->isSecure()) {
                \Illuminate\Support\Facades\URL::forceScheme('https');
                config(['session.secure' => true]);
            }
            config(['session.http_only' => true, 'session.same_site' => config('session.same_site') ?: 'lax']);
        }

        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }
}
