<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $role = is_object($user->role) ? $user->role->value : $user->role;

        if (!in_array($role, ['super_admin', 'hospital_admin'])) {
            abort(403, 'Access denied. Admin only.');
        }

        return $next($request);
    }
}
