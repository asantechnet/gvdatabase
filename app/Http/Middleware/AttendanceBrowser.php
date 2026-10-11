<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttendanceBrowser
{
    public const COOKIE = 'attendance_browser';

    public function handle(Request $request, Closure $next): Response
    {
        $browser = null;
        if ($cookie = $request->cookie(self::COOKIE)) {
            try {
                $browser = Crypt::decryptString($cookie);
            } catch (DecryptException) {
                // Replace an expired or invalid browser identifier.
            }
        }
        $validBrowser = $browser && Str::isUuid($browser);
        abort_if(! $validBrowser && ! $request->isMethod('GET'), 422, 'business.attendance_browser_required');
        $browser = $validBrowser ? $browser : (string) Str::uuid();
        $request->attributes->set('attendance_browser', $browser);

        $response = $next($request);
        $response->headers->setCookie(cookie(
            self::COOKIE, Crypt::encryptString($browser), 525600,
            '/', null, $request->isSecure(), true, false, 'lax',
        ));

        return $response;
    }
}
