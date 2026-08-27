<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Removes legacy controller-specific English confirmation strings from API
 * responses. Consumers receive one localized, stable success message instead
 * of having to translate prose returned by individual controllers.
 */
class LocalizeApiSuccessResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*') || $response->getStatusCode() >= 400 || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload) || ! is_string($payload['message'] ?? null)) {
            return $response;
        }

        $payload['message_code'] = 'success.default';
        $payload['message'] = __('api.messages.success.default');

        return response()->json($payload, $response->getStatusCode());
    }
}
