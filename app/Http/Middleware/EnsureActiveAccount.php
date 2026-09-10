<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->acc_status !== 'active') {
            $user?->currentAccessToken()?->delete();

            return response()->json(['success' => false, 'code' => 'auth.account_inactive'], 403);
        }

        return $next($request);
    }
}
