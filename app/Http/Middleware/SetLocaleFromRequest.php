<?php

namespace App\Http\Middleware;

use App\Support\AcceptedLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        // The tag ⇒ language mapping lives in one place so the printable report
        // exports resolve the request language exactly like the API does.
        AcceptedLocale::fromRequest($request);

        $response = $next($request);

        // The same endpoint may render different text for different locales.
        // Declare that variance so clients and shared caches handle it safely.
        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('Vary', 'Accept-Language', false);

        return $response;
    }
}
