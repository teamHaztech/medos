<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force admin-provisioned / reset accounts to set their own password before
 * using the app. Only redirects authenticated users flagged
 * must_change_password; the change-password and logout routes are exempt so
 * the user can actually complete the change.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ($user->must_change_password ?? false)
            && ! $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
