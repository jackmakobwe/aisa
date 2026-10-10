<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language from the session, falling back to a long-lived
 * language cookie (no account needed), then to the default.
 */
class SetLocale
{
    public const COOKIE = 'aisa_locale';

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('aisa.locales');
        $locale = $request->session()->get('locale') ?? $request->cookie(self::COOKIE);

        if (! in_array($locale, $supported, true)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
