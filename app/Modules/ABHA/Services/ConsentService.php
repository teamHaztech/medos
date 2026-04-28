<?php

namespace App\Modules\ABHA\Services;

use App\Modules\ABHA\Models\AbhaConsent;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConsentService extends BaseModuleService
{
    /**
     * Request consent from a patient for data sharing.
     */
    public function requestConsent(
        string $patientId,
        string $grantedToId,
        string $grantedToType,
        string $grantedToName,
        string $purpose,
        string $dataScope,
        ?Carbon $expiresAt = null,
    ): AbhaConsent {
        $hospitalId = $this->requireHospital();

        $patient = Patient::findOrFail($patientId);

        $consent = AbhaConsent::create([
            'hospital_id'      => $hospitalId,
            'patient_id'       => $patientId,
            'abha_number'      => $patient->abha_number ?? '',
            'granted_to_type'  => $grantedToType,
            'granted_to_id'    => $grantedToId,
            'granted_to_name'  => $grantedToName,
            'purpose'          => $purpose,
            'data_scope'       => $dataScope,
            'status'           => 'requested',
            'expires_at'       => $expiresAt,
            'metadata'         => [
                'requested_at' => now()->toIso8601String(),
                'requested_by' => auth()->id(),
            ],
        ]);

        $this->logConsentAudit('consent_request', $consent);

        return $consent;
    }

    /**
     * Grant a previously requested consent.
     */
    public function grantConsent(string $consentId): AbhaConsent
    {
        $consent = AbhaConsent::findOrFail($consentId);

        $consent->update([
            'status'     => 'granted',
            'granted_at' => now(),
        ]);

        // TODO: Notify ABDM about consent grant via /v0.5/consents/hip/notify
        $this->logConsentAudit('consent_grant', $consent);

        return $consent->fresh();
    }

    /**
     * Deny a consent request.
     */
    public function denyConsent(string $consentId): AbhaConsent
    {
        $consent = AbhaConsent::findOrFail($consentId);

        $consent->update([
            'status' => 'denied',
        ]);

        $this->logConsentAudit('consent_deny', $consent);

        return $consent->fresh();
    }

    /**
     * Revoke a previously granted consent.
     */
    public function revokeConsent(string $consentId, string $reason): AbhaConsent
    {
        $consent = AbhaConsent::findOrFail($consentId);

        $consent->revoke($reason);

        // TODO: Notify ABDM about consent revocation via /v0.5/consents/hip/notify
        $this->logConsentAudit('consent_revoke', $consent, $reason);

        return $consent->fresh();
    }

    /**
     * Get all active (granted and not expired) consents for a patient.
     */
    public function getActiveConsents(string $patientId): Collection
    {
        return AbhaConsent::where('patient_id', $patientId)
            ->where('status', 'granted')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('granted_at')
            ->get();
    }

    /**
     * Check whether a specific consent exists and is active.
     */
    public function hasConsent(string $patientId, string $grantedToId, string $purpose): bool
    {
        return AbhaConsent::where('patient_id', $patientId)
            ->where('granted_to_id', $grantedToId)
            ->where('purpose', $purpose)
            ->where('status', 'granted')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Get the full consent history for a patient (all statuses).
     */
    public function getConsentHistory(string $patientId): Collection
    {
        return AbhaConsent::where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->get();
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Log a consent action to the abha_audit_logs table.
     */
    private function logConsentAudit(string $action, AbhaConsent $consent, ?string $error = null): void
    {
        try {
            DB::table('abha_audit_logs')->insert([
                'id'            => (string) Str::uuid(),
                'hospital_id'   => $consent->hospital_id,
                'abha_number'   => $consent->abha_number ?: null,
                'patient_id'    => $consent->patient_id,
                'performed_by'  => auth()->id(),
                'action'        => $action,
                'status'        => 'success',
                'request_data'  => json_encode([
                    'consent_id'      => $consent->id,
                    'granted_to_type' => $consent->granted_to_type,
                    'granted_to_name' => $consent->granted_to_name,
                    'purpose'         => $consent->purpose,
                    'data_scope'      => $consent->data_scope,
                ]),
                'response_data' => json_encode([
                    'consent_status' => $consent->status,
                ]),
                'ip_address'    => request()->ip(),
                'error_message' => $error,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to write ABHA consent audit log', [
                'action'     => $action,
                'consent_id' => $consent->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
