<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $role = is_object($user->role) ? $user->role->value : $user->role;

        if ($role !== 'super_admin') {
            abort(403, 'Super Admin access only.');
        }

        return $next($request);
    }
}
