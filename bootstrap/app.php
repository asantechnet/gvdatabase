<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);
        $middleware->append(\App\Http\Middleware\NormalizeApiErrorResponse::class);
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) use ($apiErrorCode): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $errors = collect($exception->errors())->map(fn (array $messages) => array_map(
                    fn (string $message) => str_starts_with($message, 'validation.') || str_starts_with($message, 'business.')
                        ? $message
                        : 'validation.invalid',
                    $messages,
                ));

                return response()->json([
                    'success' => false,
                    'code' => 'validation_failed',
                    'errors' => $errors,
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
