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
        $header = $request->header('X-Hospital-ID');
        $user = $request->user();

        // Authenticated non-super-admin users are pinned to their own hospital.
        // A mismatching X-Hospital-ID header is a cross-tenant attempt → unresolvable.
        if ($user && isset($user->hospital_id)) {
            $role = is_object($user->role) ? $user->role->value : $user->role;
            if ($role !== 'super_admin') {
                if ($header && $header !== $user->hospital_id) {
                    return null;
                }

                return $user->hospital_id;
            }
        }

        // 1. Explicit header (super admin, or unauthenticated webhook context)
        if ($header) {
            return $header;
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

        // 3. Authenticated user's hospital (super admin without header)
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
