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
                'message' => 'Tell us which hospital this request is for. Call GET /api/v1/hospitals to list the hospitals your token may act for, then name one — by header, URL, or body — using its id or slug. (Hospital-bound tokens resolve automatically and need none of this.)',
                'how_to_fix' => [
                    'list_hospitals'   => 'GET /api/v1/hospitals',
                    'option_1_header'  => 'X-Hospital-ID: <hospital_id_or_slug>',
                    'option_2_query'   => '?hospital=<slug>   (e.g. ...?phone=99...&hospital=city-care)',
                    'option_3_body'    => '{ "hospital_id": "<id_or_slug>", ... }   (for POST calls)',
                    'easiest'          => 'Use a hospital-specific API token (from that hospital admin → API Keys) — then no header/param is needed at all.',
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
