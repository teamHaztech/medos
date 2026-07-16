<?php

namespace App\Modules\Admin\Middleware;

use App\Modules\Core\Services\HospitalContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveHospital
{
    public function __construct(
        protected HospitalContext $hospitalContext,
    ) {}

    /**
     * Resolve the current hospital from the request and bind it into HospitalContext.
     *
     * Resolution order:
     *   1. X-Hospital-ID header
     *   2. Subdomain
     *   3. Authenticated user's hospital_id
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hospitalId = $this->hospitalContext->resolveFromRequest($request);

        if ($hospitalId === null) {
            return response()->json([
                'error' => 'Hospital context could not be resolved.',
                'message' => 'Send an X-Hospital-ID header naming the target hospital. Call GET /api/v1/hospitals to list the hospitals your token may act for. Platform / super-admin tokens must specify the hospital; hospital-bound tokens resolve automatically.',
                'how_to_fix' => [
                    'list_hospitals' => 'GET /api/v1/hospitals',
                    'then_send_header' => 'X-Hospital-ID: <hospital_id>',
                ],
            ], 422);
        }

        $this->hospitalContext->setHospital($hospitalId);

        // Verify the hospital actually exists
        if ($this->hospitalContext->getHospital() === null) {
            return response()->json([
                'error' => 'Hospital not found.',
                'message' => "No hospital exists with ID: {$hospitalId}",
            ], 404);
        }

        return $next($request);
    }
}
