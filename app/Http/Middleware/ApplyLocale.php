<?php

namespace App\Http\Middleware;

use App\Support\UiTranslator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Menentukan bahasa untuk permintaan ini dan menerjemahkan halaman bila bahasanya Inggris. */
class ApplyLocale
{
    public const SUPPORTED = ['id', 'en'];
    public const COOKIE = 'trusta_locale';

    public static function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user && in_array($user->locale, self::SUPPORTED, true)) {
            return $user->locale;
        }
        if (in_array($request->cookie(self::COOKIE), self::SUPPORTED, true)) {
            return $request->cookie(self::COOKIE);
        }

        return $user?->company?->default_language === 'English' ? 'en' : 'id';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::resolve($request);
        app()->setLocale($locale);
        \Carbon\Carbon::setLocale($locale);
        \Illuminate\Support\Carbon::setLocale($locale);

        $response = $next($request);

        if ($locale === 'en' && $response instanceof \Illuminate\Http\Response && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            $translator = UiTranslator::instance();
            $response->setContent($translator->html((string) $response->getContent()));
            if ($translator->misses) {
                @file_put_contents(storage_path('logs/i18n-miss.log'), implode("\n", array_keys($translator->misses)) . "\n", FILE_APPEND);
                $translator->misses = [];
            }
        }

        return $response;
    }
}
