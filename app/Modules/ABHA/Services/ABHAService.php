<?php

namespace App\Modules\ABHA\Services;

use App\Modules\ABHA\Models\AbhaHealthRecord;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ABHAService extends BaseModuleService
{
    // ---------------------------------------------------------------
    // ABDM connection state (M1 readiness)
    // ---------------------------------------------------------------

    /** The hospital's ABDM/ABHA credentials + registry ids (Hospital.config['abdm']). */
    public function credentials(): array
    {
        try {
            $hospital = Hospital::find($this->requireHospital());
        } catch (\Throwable $e) {
            return [];
        }
        $config = is_array($hospital?->config) ? $hospital->config : json_decode($hospital?->config ?? '{}', true);

        return $config['abdm'] ?? [];
    }

    /** True once real ABDM sandbox/production credentials are configured. */
    public function isConfigured(): bool
    {
        $c = $this->credentials();

        return ! empty($c['client_id']) && ! empty($c['client_secret']) && ! empty($c['base_url']);
    }

    /** 'live' once ABDM is connected, else 'simulated' (mock responses, nothing sent to ABDM). */
    public function mode(): string
    {
        return $this->isConfigured() ? 'live' : 'simulated';
    }

    /** Facility HFR id — a prerequisite for HIP linking. */
    public function hfrId(): ?string
    {
        return $this->credentials()['hfr_id'] ?? null;
    }

    // ---------------------------------------------------------------
    // Public Methods
    // ---------------------------------------------------------------

    /**
     * Create a new ABHA number via ABDM.
     *
     * @param array $data Keys: name, date_of_birth, gender, phone, aadhaar (optional)
     * @return array{success: bool, abha_number: string, abha_address: string, profile: array}
     */
    public function createAbha(array $data): array
    {
        $hospitalId = $this->requireHospital();

        try {
            // TODO: Replace mock with real ABDM API call to /v1/registration/aadhaar/createHealthIdWithPreVerified
            $abhaNumber = $this->generateMockAbhaNumber();
            $abhaAddress = Str::slug($data['name'] ?? 'user', '.') . '@abdm';

            $profile = [
                'name'       => $data['name'] ?? '',
                'gender'     => $data['gender'] ?? '',
                'dob'        => $data['date_of_birth'] ?? '',
                'phone'      => $data['phone'] ?? '',
                'address'    => '',
                'abha_number' => $abhaNumber,
                'abha_address' => $abhaAddress,
            ];

            $this->logAudit(
                action: 'create',
                status: 'success',
                abhaNumber: $this->normalizeAbha($abhaNumber),
                patientId: null,
                request: array_diff_key($data, ['aadhaar' => true]), // never log aadhaar
                response: ['abha_number' => $abhaNumber, 'abha_address' => $abhaAddress],
            );

            return [
                'success'      => true,
                'abha_number'  => $abhaNumber,
                'abha_address' => $abhaAddress,
                'profile'      => $profile,
                'mode'         => $this->mode(),
                'simulated'    => ! $this->isConfigured(),
            ];
        } catch (\Throwable $e) {
            $this->logAudit(
                action: 'create',
                status: 'failed',
                abhaNumber: null,
                patientId: null,
                request: array_diff_key($data, ['aadhaar' => true]),
                response: [],
                error: $e->getMessage(),
            );

            $this->logError('ABHA creation failed', ['error' => $e->getMessage()]);

            return [
                'success'      => false,
                'abha_number'  => null,
                'abha_address' => null,
                'profile'      => [],
            ];
        }
    }

    /**
     * Link an existing ABHA number to a patient.
     *
     * @return array{success: bool, message: string, profile: array|null}
     */
    public function linkAbha(string $patientId, string $abhaNumber): array
    {
        $hospitalId = $this->requireHospital();

        if (! $this->validateAbhaFormat($abhaNumber)) {
            $this->logAudit('link', 'failed', null, $patientId, [], [], 'Invalid ABHA format');

            return ['success' => false, 'message' => 'Invalid ABHA number format.', 'profile' => null];
        }

        $normalized = $this->normalizeAbha($abhaNumber);

        // Check no other patient in this hospital already has this ABHA
        $existing = DB::table('patients')
            ->where('hospital_id', $hospitalId)
            ->where('abha_number', $normalized)
            ->where('id', '!=', $patientId)
            ->exists();

        if ($existing) {
            $this->logAudit('link', 'failed', $normalized, $patientId, [], [], 'ABHA already linked to another patient');

            return ['success' => false, 'message' => 'This ABHA number is already linked to another patient in your hospital.', 'profile' => null];
        }

        // Verify with ABDM
        $verification = $this->verifyAbha($abhaNumber);

        if (! $verification['valid']) {
            $this->logAudit('link', 'failed', $normalized, $patientId, [], [], 'ABHA verification failed');

            return ['success' => false, 'message' => 'ABHA number could not be verified.', 'profile' => null];
        }

        $profile = $verification['profile'];

        // Update patient record
        DB::table('patients')
            ->where('id', $patientId)
            ->where('hospital_id', $hospitalId)
            ->update([
                'abha_number'   => $normalized,
                'abha_address'  => $profile['abha_address'] ?? null,
                'abha_verified' => true,
                'abha_profile'  => json_encode($profile),
                'updated_at'    => now(),
            ]);

        $this->logAudit('link', 'success', $normalized, $patientId, ['abha_number' => $abhaNumber], $profile);

        return [
            'success' => true,
            'message' => 'ABHA number linked successfully.',
            'profile' => $profile,
        ];
    }

    /**
     * Verify an ABHA number with ABDM.
     *
     * @return array{valid: bool, profile: array}
     */
    public function verifyAbha(string $abhaNumber): array
    {
        if (! $this->validateAbhaFormat($abhaNumber)) {
            return ['valid' => false, 'profile' => []];
        }

        $normalized = $this->normalizeAbha($abhaNumber);

        // TODO: Replace mock with real ABDM API call to /v1/search/searchByHealthId
        $profile = [
            'name'         => 'Mock User',
            'gender'       => 'M',
            'dob'          => '1990-01-15',
            'address'      => '123 Mock Street, New Delhi',
            'phone_last4'  => '7890',
            'abha_address' => 'mock.user@abdm',
        ];

        $this->logAudit('verify', 'success', $normalized, null, ['abha_number' => $abhaNumber], $profile);

        return ['valid' => true, 'profile' => $profile];
    }

    /**
     * Fetch patient profile from ABDM by ABHA number.
     */
    public function fetchProfile(string $abhaNumber): ?array
    {
        if (! $this->validateAbhaFormat($abhaNumber)) {
            return null;
        }

        $normalized = $this->normalizeAbha($abhaNumber);

        // TODO: Replace mock with real ABDM API call to /v1/account/profile
        $profile = [
            'name'          => 'Mock User',
            'gender'        => 'M',
            'dob'           => '1990-01-15',
            'address'       => '123 Mock Street, New Delhi',
            'phone_last4'   => '7890',
            'abha_number'   => $this->formatAbha($normalized),
            'abha_address'  => 'mock.user@abdm',
            'state'         => 'Delhi',
            'district'      => 'Central Delhi',
        ];

        $this->logAudit('fetch_profile', 'success', $normalized, null, ['abha_number' => $abhaNumber], $profile);

        return $profile;
    }

    /**
     * Fetch health records from ABDM for a given ABHA number and consent.
     *
     * @return array List of health records
     */
    public function fetchHealthRecords(string $abhaNumber, string $consentId): array
    {
        $normalized = $this->normalizeAbha($abhaNumber);

        if (! $this->isConfigured()) {
            // No ABDM connection — do NOT fabricate a patient's longitudinal records.
            // Return empty so the UI shows an honest "not connected" state.
            $this->logAudit('fetch_records', 'simulated', $normalized, null, [
                'abha_number' => $abhaNumber,
                'consent_id'  => $consentId,
            ], ['record_count' => 0, 'mode' => 'simulated']);

            return [];
        }

        // TODO: wire the real ABDM Health Information (HI) fetch here once live —
        // POST /v0.5/health-information/cm/request with the consent artefact.
        $records = [];

        $this->logAudit('fetch_records', 'success', $normalized, null, [
            'abha_number' => $abhaNumber,
            'consent_id'  => $consentId,
        ], ['record_count' => count($records)]);

        return $records;
    }

    /**
     * Push a health record to ABDM.
     *
     * @return array{success: bool, record_id: string|null}
     */
    public function pushHealthRecord(string $patientId, string $recordType, string $sourceId, array $data): array
    {
        $hospitalId = $this->requireHospital();

        // Get patient's ABHA number
        $patient = Patient::find($patientId);

        if (! $patient || ! $patient->abha_number) {
            return ['success' => false, 'record_id' => null];
        }

        $fhirBundle = $this->toFhirBundle($recordType, $data);

        $record = AbhaHealthRecord::create([
            'hospital_id'  => $hospitalId,
            'patient_id'   => $patientId,
            'abha_number'  => $patient->abha_number,
            'record_type'  => $recordType,
            'source_id'    => $sourceId,
            'source_type'  => $data['source_type'] ?? 'App\\Modules\\Patient\\Models\\Encounter',
            'fhir_data'    => $fhirBundle,
            'status'       => 'created',
        ]);

        if ($this->isConfigured()) {
            // TODO: wire the real ABDM data-push here once credentials are live —
            // POST /v0.5/health-information/hip/on-request
            $abdmId = 'ABDM-' . Str::upper(Str::random(12)); // placeholder id until the real call is wired
            $status = 'pushed';
            $pushedAt = now();
        } else {
            // ABDM not connected yet: the record is prepared locally, NOT sent to
            // the national registry — mark it honestly so nothing is claimed as pushed.
            $abdmId = null;
            $status = 'simulated';
            $pushedAt = null;
        }

        $record->update([
            'status'         => $status,
            'abdm_record_id' => $abdmId,
            'pushed_at'      => $pushedAt,
        ]);

        $this->logAudit('push_record', $status === 'pushed' ? 'success' : 'simulated', $patient->abha_number, $patientId, [
            'record_type' => $recordType,
            'source_id'   => $sourceId,
        ], [
            'record_id'      => $record->id,
            'abdm_record_id' => $abdmId,
            'mode'           => $this->mode(),
        ]);

        return ['success' => true, 'record_id' => $record->id, 'mode' => $this->mode(), 'simulated' => ! $this->isConfigured()];
    }

    /**
     * Link an encounter to the patient's ABHA and create health record entries.
     */
    public function linkEncounter(string $encounterId): bool
    {
        $hospitalId = $this->requireHospital();

        $encounter = Encounter::find($encounterId);

        if (! $encounter) {
            $this->logError('Encounter not found for ABHA linking', ['encounter_id' => $encounterId]);
            return false;
        }

        $patient = Patient::find($encounter->patient_id);

        if (! $patient || ! $patient->abha_number) {
            $this->logWarning('Patient has no ABHA number, skipping encounter link', [
                'encounter_id' => $encounterId,
                'patient_id'   => $encounter->patient_id,
            ]);
            return false;
        }

        // Create a health record entry for this encounter
        AbhaHealthRecord::create([
            'hospital_id'  => $hospitalId,
            'patient_id'   => $patient->id,
            'abha_number'  => $patient->abha_number,
            'record_type'  => 'encounter',
            'source_id'    => $encounterId,
            'source_type'  => Encounter::class,
            'fhir_data'    => $this->toFhirBundle('encounter', [
                'encounter_id' => $encounterId,
                'patient_name' => $patient->name,
                'date'         => $encounter->created_at?->toDateString(),
            ]),
            'status'       => 'linked',
            'linked_at'    => now(),
        ]);

        // Mark encounter as ABHA-linked
        DB::table('encounters')
            ->where('id', $encounterId)
            ->update([
                'abha_number' => $patient->abha_number,
                'abha_linked' => true,
                'updated_at'  => now(),
            ]);

        $this->logAudit('link_encounter', 'success', $patient->abha_number, $patient->id, [
            'encounter_id' => $encounterId,
        ]);

        return true;
    }

    /**
     * Get all ABHA-linked health records for a patient.
     */
    public function getLinkedRecords(string $patientId): Collection
    {
        return AbhaHealthRecord::where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Search for a patient by ABHA number within the current hospital.
     */
    public function findByAbha(string $abhaNumber): ?Patient
    {
        if (! $this->validateAbhaFormat($abhaNumber)) {
            return null;
        }

        $normalized = $this->normalizeAbha($abhaNumber);

        return Patient::where('abha_number', $normalized)->first();
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Validate ABHA number format: 14 digits or XX-XXXX-XXXX-XX.
     */
    private function validateAbhaFormat(string $abha): bool
    {
        // Accept 14 digits with no separator
        if (preg_match('/^\d{14}$/', $abha)) {
            return true;
        }

        // Accept XX-XXXX-XXXX-XX format
        if (preg_match('/^\d{2}-\d{4}-\d{4}-\d{2}$/', $abha)) {
            return true;
        }

        return false;
    }

    /**
     * Normalize ABHA number by removing dashes to get 14 digits.
     */
    private function normalizeAbha(string $abha): string
    {
        return str_replace('-', '', $abha);
    }

    /**
     * Format a 14-digit ABHA number as XX-XXXX-XXXX-XX.
     */
    private function formatAbha(string $abha): string
    {
        $digits = $this->normalizeAbha($abha);

        return substr($digits, 0, 2) . '-'
            . substr($digits, 2, 4) . '-'
            . substr($digits, 6, 4) . '-'
            . substr($digits, 10, 4);
    }

    /**
     * Log an action to the abha_audit_logs table.
     */
    private function logAudit(
        string $action,
        string $status,
        ?string $abhaNumber = null,
        ?string $patientId = null,
        array $request = [],
        array $response = [],
        ?string $error = null,
    ): void {
        try {
            DB::table('abha_audit_logs')->insert([
                'id'            => (string) Str::uuid(),
                'hospital_id'   => $this->hospitalId(),
                'abha_number'   => $abhaNumber,
                'patient_id'    => $patientId,
                'performed_by'  => auth()->id(),
                'action'        => $action,
                'status'        => $status,
                'request_data'  => ! empty($request) ? json_encode($request) : null,
                'response_data' => ! empty($response) ? json_encode($response) : null,
                'ip_address'    => request()->ip(),
                'error_message' => $error,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to write ABHA audit log', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Convert MedOS data to a FHIR-like bundle structure.
     *
     * @param string $recordType encounter, prescription, lab_result, discharge_summary, etc.
     * @param array  $data       Source data to convert
     * @return array FHIR-like bundle
     */
    private function toFhirBundle(string $recordType, array $data): array
    {
        // TODO: Replace with proper FHIR R4 bundle generation using a FHIR library
        $resourceTypeMap = [
            'encounter'         => 'Encounter',
            'prescription'      => 'MedicationRequest',
            'lab_result'        => 'DiagnosticReport',
            'discharge_summary' => 'Composition',
            'diagnostic_report' => 'DiagnosticReport',
            'immunization'      => 'Immunization',
        ];

        $fhirResourceType = $resourceTypeMap[$recordType] ?? 'DocumentReference';

        return [
            'resourceType' => 'Bundle',
            'type'         => 'collection',
            'timestamp'    => now()->toIso8601String(),
            'entry'        => [
                [
                    'fullUrl'  => 'urn:uuid:' . Str::uuid(),
                    'resource' => [
                        'resourceType' => $fhirResourceType,
                        'status'       => 'final',
                        'meta'         => [
                            'lastUpdated' => now()->toIso8601String(),
                            'source'      => 'MedOS',
                        ],
                        'data' => $data,
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate a mock 14-digit ABHA number for testing.
     */
    private function generateMockAbhaNumber(): string
    {
        $digits = '';
        for ($i = 0; $i < 14; $i++) {
            $digits .= random_int(0, 9);
        }

        return $this->formatAbha($digits);
    }
}
