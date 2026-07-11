<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to a route when the current hospital has the given module disabled.
 * Usage: ->middleware('module:billing')
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $hospital = $request->user()?->hospital;

        if ($hospital && ! $hospital->isModuleEnabled($module)) {
            abort(404);
        }

        return $next($request);
    }
}
