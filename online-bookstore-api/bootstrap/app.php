<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
        $middleware->throttleApi('api');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every response — success or failure — uses the same envelope, so the
        // Flutter client can unwrap it in one place.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // A failed validation carries one message per field so the client
            // can highlight the offending inputs.
            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    $e->getMessage(),
                    422,
                    $e->errors(),
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', 401);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return match ($status) {
                    401 => ApiResponse::error('Invalid email or password.', 401),
                    403 => ApiResponse::error(
                        'Your account does not have access to this action.',
                        403,
                    ),
                    404 => ApiResponse::error('The requested item was not found.', 404),
                    405 => ApiResponse::error('Method not allowed.', 405),
                    419 => ApiResponse::error('Your session has expired.', 419),
                    429 => ApiResponse::error(
                        'Too many attempts. Please wait a moment and try again.',
                        429,
                    ),
                    default => ApiResponse::error(
                        $e->getMessage() ?: 'Something went wrong. Please try again.',
                        $status,
                    ),
                };
            }

            report($e);

            return ApiResponse::error(
                'Something went wrong. Please try again.',
                500,
            );
        });
    })->create();
