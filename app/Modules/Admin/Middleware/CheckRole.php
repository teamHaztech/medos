<?php

namespace App\Modules\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Check if the authenticated user has one of the required roles.
     *
     * Usage in route definition:
     *   ->middleware('role:doctor,admin')
     *
     * The user model is expected to have a `role` attribute (string)
     * or a `hasRole(string $role): bool` method.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthenticated.',
                'message' => 'You must be logged in to access this resource.',
            ], 401);
        }

        // Support a hasRole() method on the User model
        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return $next($request);
                }
            }

            return $this->forbidden($roles);
        }

        // Fallback: check the role attribute directly
        if (isset($user->role) && in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return $this->forbidden($roles);
    }

    /**
     * Return a 403 Forbidden response.
     */
    protected function forbidden(array $roles): Response
    {
        return response()->json([
            'error' => 'Forbidden.',
            'message' => 'You do not have the required role. Required: ' . implode(', ', $roles),
        ], 403);
    }
}
