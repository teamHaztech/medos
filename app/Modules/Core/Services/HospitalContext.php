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
     * Integrators can name the hospital whichever way is easiest for their
     * platform — a header, a URL query param, or a JSON body field — and as a
     * UUID or a readable slug. Resolution order:
     *   1. Explicit hospital ref: X-Hospital-ID header, or ?hospital_id= / ?hospital=
     *      query param, or a hospital_id / hospital body field (UUID or slug)
     *   2. Subdomain (first segment of host)
     *   3. Authenticated user's own hospital_id
     *
     * Returns the hospital ID or null if unresolvable.
     */
    public function resolveFromRequest(Request $request): ?string
    {
        $explicit = $this->explicitHospitalRef($request);
        $user = $request->user();

        // Authenticated non-super-admin users are pinned to their own hospital.
        // A mismatching explicit hospital is a cross-tenant attempt → unresolvable.
        if ($user && isset($user->hospital_id)) {
            $role = is_object($user->role) ? $user->role->value : $user->role;
            if ($role !== 'super_admin') {
                if ($explicit !== null && $explicit !== $user->hospital_id) {
                    return null;
                }

                return $user->hospital_id;
            }
        }

        // 1. Explicit hospital ref (super admin, or unauthenticated webhook context)
        if ($explicit !== null) {
            return $explicit;
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

        // 3. Authenticated user's hospital (super admin without an explicit ref)
        if ($user && isset($user->hospital_id)) {
            return $user->hospital_id;
        }

        return null;
    }

    /**
     * The hospital the caller named — from a header, query param, or body field —
     * normalised to a hospital id. Accepts a UUID or a slug. Returns null if the
     * caller didn't name one, or named one that doesn't exist.
     */
    private function explicitHospitalRef(Request $request): ?string
    {
        $ref = $request->header('X-Hospital-ID')
            ?? $request->input('hospital_id')   // query string OR JSON body
            ?? $request->input('hospital');

        if (! is_string($ref) || ($ref = trim($ref)) === '') {
            return null;
        }

        // A UUID is used as-is; anything else is treated as a slug and looked up.
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $ref)) {
            return $ref;
        }

        return Hospital::where('slug', $ref)->value('id');
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
