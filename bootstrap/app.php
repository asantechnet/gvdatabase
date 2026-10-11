<?php

use App\Http\Middleware\EnforceEditWindow;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\LocalizeApiSuccessResponse;
use App\Http\Middleware\NormalizeApiErrorResponse;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromRequest;
use App\Support\ApiMessage;
use App\Support\ValidationErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$apiErrorCode = static function (int $status, ?string $message = null): string {
    if ($message && (str_starts_with($message, 'auth.') || str_starts_with($message, 'business.') || str_starts_with($message, 'resource.'))) {
        return $message;
    }

    return match ($status) {
        401 => 'auth.unauthenticated',
        403 => 'auth.forbidden',
        404 => 'resource.not_found',
        409 => 'business.conflict',
        422 => 'business.request_rejected',
        429 => 'request.rate_limited',
        default => 'server.error',
    };
};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(HandleCors::class);
        $middleware->append(SetLocaleFromRequest::class);
        $middleware->append(LocalizeApiSuccessResponse::class);
        $middleware->append(NormalizeApiErrorResponse::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'permission' => EnsurePermission::class,
            'active' => EnsureActiveAccount::class,
            'edit-window' => EnforceEditWindow::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiErrorCode): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $originalErrors = $exception->errors();
                // Validator::failed() reports the concrete rule behind each
                // message; we use it to return the real failure (e.g.
                // validation.max, validation.regex) instead of one generic
                // validation.invalid for every rule.
                $failedRules = $exception->validator?->failed() ?? [];

                $errors = [];
                $messages = [];

                foreach ($originalErrors as $field => $fieldMessages) {
                    $rules = array_keys($failedRules[$field] ?? []);

                    foreach (array_values($fieldMessages) as $index => $message) {
                        // Form requests may supply a stable code (validation.* /
                        // business.*) as an explicit message override.
                        $isCode = str_starts_with($message, 'validation.') || str_starts_with($message, 'business.');

                        $errors[$field][] = $isCode ? $message : ValidationErrorCode::forRule($rules[$index] ?? null);
                        $messages[$field][] = $isCode ? ApiMessage::forCode($message) : $message;
                    }
                }

                return response()->json([
                    'success' => false,
                    'code' => 'validation_failed',
                    'errors' => $errors,
                    'messages' => $messages,
                    'message' => ApiMessage::forCode('validation_failed'),
                ], 422);
            }
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'code' => 'auth.unauthenticated'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['success' => false, 'code' => 'auth.forbidden'], 403);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($apiErrorCode) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = $exception->getStatusCode();

                return response()->json([
                    'success' => false,
                    'code' => $apiErrorCode($status, $exception->getMessage()),
                ], $status);
            }
        });
    })->create();
