<?php

namespace App\Http\Middleware;

use App\Support\ApiMessage;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides stable API error codes together with localized text.
 */
class NormalizeApiErrorResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*') || $response->getStatusCode() < 400 || ! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $messageCode = is_string($payload['message'] ?? null) ? $payload['message'] : null;
        $payload['success'] = false;
        $payload['code'] ??= $this->codeFor($response->getStatusCode(), $payload, $messageCode);
        $payload['message'] = ApiMessage::forCode($payload['code']);

        // Preserve cookies and throttling/CORS headers added by other middleware.
        $response->setData($payload);

        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function codeFor(int $status, array $payload, ?string $messageCode): string
    {
        if ($messageCode && (str_starts_with($messageCode, 'auth.') || str_starts_with($messageCode, 'business.') || str_starts_with($messageCode, 'resource.'))) {
            return $messageCode;
        }

        if (! empty($payload['email_verification_required'])) {
            return 'auth.email_verification_required';
        }
        if (! empty($payload['two_factor_required'])) {
            return 'auth.two_factor_required';
        }

        return match ($status) {
            401 => 'auth.unauthenticated',
            403 => 'auth.forbidden',
            404 => 'resource.not_found',
            409 => 'business.conflict',
            422 => isset($payload['errors']) ? 'validation_failed' : 'business.request_rejected',
            429 => 'request.rate_limited',
            default => 'server.error',
        };
    }
}
