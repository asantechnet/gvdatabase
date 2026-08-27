<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $locales = config('localization.accepted_locales', []);
        app()->setLocale(config('app.locale'));

        if ($request->headers->has('Accept-Language') && $locales) {
            $preferredLocale = $request->getPreferredLanguage(array_keys($locales));
            $frontendLocale = str_replace('_', '-', (string) $preferredLocale);

            if (isset($locales[$frontendLocale])) {
                app()->setLocale($locales[$frontendLocale]);
            }
        }

        $response = $next($request);

        // The same endpoint may render different text for different locales.
        // Declare that variance so clients and shared caches handle it safely.
        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('Vary', 'Accept-Language', false);

        return $response;
    }
}
