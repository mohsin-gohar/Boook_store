<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to administrators.
 *
 * The Flutter client maps HTTP 403 to "your account does not have access to
 * this action", so the response keeps the standard envelope.
 */
class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return ApiResponse::error(
                'Your account does not have access to this action.',
                403,
            );
        }

        return $next($request);
    }
}
