<?php

namespace App\Modules\Auth\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    /**
     * Handle an incoming request.
     *
     * Usage: ->middleware('ability:patients:read')
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $user || ! $user->currentAccessToken()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = $user->currentAccessToken();

        // Wildcard grants everything
        if (in_array('*', $token->abilities ?? [])) {
            return $next($request);
        }

        // Check exact match
        if ($token->can($ability)) {
            return $next($request);
        }

        // Check namespace wildcard (e.g. "patients:*" covers "patients:read")
        $namespace = explode(':', $ability)[0] ?? '';
        if ($token->can("{$namespace}:*")) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'data'    => null,
            'message' => 'Insufficient permissions.',
        ], 403);
    }
}
