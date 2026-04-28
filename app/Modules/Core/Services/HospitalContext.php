<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Hospital;
use Illuminate\Http\Request;

class HospitalContext
{
    protected ?string $hospitalId = null;
    protected ?Hospital $hospital = null;

    /**
     * Set the current hospital by ID.
     */
    public function setHospital(string $hospitalId): void
    {
        $this->hospitalId = $hospitalId;
        $this->hospital = null; // Reset cached model so it reloads
        config(['medos.current_hospital_id' => $hospitalId]);
    }

    /**
     * Get the current Hospital model (lazy-loaded).
     */
    public function getHospital(): ?Hospital
    {
        if ($this->hospital === null && $this->hospitalId !== null) {
            $this->hospital = Hospital::find($this->hospitalId);
        }

        return $this->hospital;
    }

    /**
     * Get the current hospital ID.
     */
    public function getHospitalId(): ?string
    {
        return $this->hospitalId;
    }

    /**
     * Resolve hospital from the incoming request.
     *
     * Resolution order:
     *   1. X-Hospital-ID header
     *   2. Subdomain (first segment of host)
     *   3. Authenticated user's hospital_id
     *
     * Returns the hospital ID or null if unresolvable.
     */
    public function resolveFromRequest(Request $request): ?string
    {
        // 1. Explicit header
        if ($headerValue = $request->header('X-Hospital-ID')) {
            return $headerValue;
        }

        // 2. Subdomain resolution
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            $hospital = Hospital::where('slug', $subdomain)->first();
            if ($hospital) {
                return $hospital->id;
            }
        }

        // 3. Authenticated user's hospital
        $user = $request->user();
        if ($user && isset($user->hospital_id)) {
            return $user->hospital_id;
        }

        return null;
    }

    /**
     * Check whether a hospital context has been established.
     */
    public function isResolved(): bool
    {
        return $this->hospitalId !== null;
    }

    /**
     * Clear the current hospital context.
     */
    public function clear(): void
    {
        $this->hospitalId = null;
        $this->hospital = null;
        config(['medos.current_hospital_id' => null]);
    }
}
