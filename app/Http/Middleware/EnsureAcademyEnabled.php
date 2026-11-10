<?php

namespace App\Http\Middleware;

use App\Support\CompanyProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcademyEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(CompanyProfile::class)->academyEnabled(), 404);

        return $next($request);
    }
}
