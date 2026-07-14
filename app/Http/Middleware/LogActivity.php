<?php

namespace App\Http\Middleware;

use App\Modules\Core\Models\AccountActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every state-changing action (create / update / delete) an authenticated
 * user performs, into the append-only account_activity log. Combined with the
 * login / logout / failed-login rows written by WebAuthController, this gives
 * Super Admins and Hospital Admins a full "who did what, when" incident-response
 * trail. Runs in terminate() so it never adds latency to the response.
 */
class LogActivity
{
    /** Verb per HTTP method — GET/HEAD are reads and never logged. */
    private const VERBS = [
        'POST'   => 'create',
        'PUT'    => 'update',
        'PATCH'  => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * Route names already recorded elsewhere, or too noisy to keep — skipped so
     * the trail doesn't double up or fill with heartbeats.
     */
    private const SKIP = [
        'login', 'logout',                       // recorded by WebAuthController
    ];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $method = $request->getMethod();
            if (! isset(self::VERBS[$method]) || ! Auth::check()) {
                return;
            }
            // Only log actions that actually succeeded.
            if ($response->getStatusCode() >= 400) {
                return;
            }

            $route = $request->route();
            $name  = $route?->getName();
            if ($name && in_array($name, self::SKIP, true)) {
                return;
            }

            AccountActivity::record(
                Auth::user(),
                self::VERBS[$method],
                $request,
                null,
                $this->describe($request, $name)
            );
        } catch (\Throwable $e) {
            // Never let logging break a request.
        }
    }

    /** A short, human-readable summary of what was done. */
    private function describe(Request $request, ?string $routeName): string
    {
        $summary = strtoupper($request->getMethod()) . ' /' . ltrim($request->path(), '/');

        if ($routeName) {
            // e.g. "web.admin.patients.store" → "admin › patients › store"
            $pretty = str_replace(['web.', '.'], ['', ' › '], $routeName);
            $summary .= '  (' . $pretty . ')';
        }

        return \Illuminate\Support\Str::limit($summary, 240, '');
    }
}
