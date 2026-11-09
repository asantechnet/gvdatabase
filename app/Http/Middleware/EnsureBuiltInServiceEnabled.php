<?php

namespace App\Http\Middleware;

use App\Support\BuiltInService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuiltInServiceEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $service = $request->route('service');

        abort_unless($service === null || BuiltInService::isEnabled($service), 404);

        return $next($request);
    }
}
