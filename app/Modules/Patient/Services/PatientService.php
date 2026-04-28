<?php

namespace App\Modules\Patient\Services;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PatientService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------

    /**
     * Find a patient by phone number or create a new one.
     *
     * @param  string       $phone    The patient's phone number.
     * @param  Channel      $channel  The channel through which the patient was registered.
     * @param  array        $data     Additional patient data.
     * @return Patient
     */
    public function findOrCreate(string $phone, Channel $channel, array $data = []): Patient
    {
        $hospitalId = $this->requireHospital();

        $patient = Patient::where('hospital_id', $hospitalId)
            ->where('phone', $phone)
            ->first();

        if ($patient) {
            $this->logInfo('Returning patient found', ['patient_id' => $patient->id, 'phone' => $phone]);
            return $patient;
        }

        // Create new patient
        $patient = Patient::create(array_merge([
            'hospital_id'        => $hospitalId,
            'phone'              => $phone,
            'first_name'         => $data['first_name'] ?? 'Unknown',
            'last_name'          => $data['last_name'] ?? '',
            'preferred_language' => $data['preferred_language'] ?? config('medos.languages.default', 'en'),
            'metadata'           => [
                'registration_channel' => $channel->value,
                'registered_at'        => now()->toIso8601String(),
            ],
        ], $data));

        $this->logInfo('New patient created', ['patient_id' => $patient->id, 'phone' => $phone, 'channel' => $channel->value]);

        return $patient;
    }

    /**
     * Search patients by name or phone (fuzzy match).
     *
     * @param  string      $query  The search query.
     * @return Collection
     */
    public function search(string $query): Collection
    {
        $hospitalId = $this->requireHospital();

        return Patient::where('hospital_id', $hospitalId)
            ->where(function ($q) use ($query) {
                $q->where('phone', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"]);
            })
            ->orderBy('first_name')
            ->limit(50)
            ->get();
    }

    /**
     * Get a merged timeline of all patient activity (encounters, appointments, bills, conversations).
     *
     * @param  string  $patientId  The patient's UUID.
     * @return array               Sorted timeline entries.
     */
    public function getTimeline(string $patientId): array
    {
        $patient = Patient::findOrFail($patientId);

        $timeline = collect();

        // Encounters
        $patient->encounters()->with(['doctor'])->get()->each(function (Encounter $encounter) use ($timeline) {
            $timeline->push([
                'type'        => 'encounter',
                'id'          => $encounter->id,
                'date'        => $encounter->created_at->toIso8601String(),
                'title'       => 'Consultation' . ($encounter->doctor ? ' with Dr. ' . $encounter->doctor->full_name : ''),
                'status'      => $encounter->status->value,
                'details'     => [
                    'chief_complaint'       => $encounter->chief_complaint,
                    'triage_classification' => $encounter->triage_classification?->value,
                    'diagnosis_codes'       => $encounter->diagnosis_codes,
                    'type'                  => $encounter->type?->value,
                ],
            ]);
        });

        // Appointments
        $patient->appointments()->with(['doctor'])->get()->each(function ($appointment) use ($timeline) {
            $timeline->push([
                'type'    => 'appointment',
                'id'      => $appointment->id,
                'date'    => $appointment->slot_start?->toIso8601String() ?? $appointment->created_at->toIso8601String(),
                'title'   => 'Appointment' . ($appointment->doctor ? ' with Dr. ' . $appointment->doctor->full_name : ''),
                'status'  => $appointment->status->value,
                'details' => [
                    'reason'   => $appointment->reason,
                    'slot_end' => $appointment->slot_end?->toIso8601String(),
                    'channel'  => $appointment->channel,
                ],
            ]);
        });

        // Bills
        $patient->load('encounters.bills');
        $patient->encounters->each(function ($encounter) use ($timeline) {
            $encounter->bills->each(function ($bill) use ($timeline) {
                $timeline->push([
                    'type'    => 'bill',
                    'id'      => $bill->id,
                    'date'    => $bill->issued_at?->toIso8601String() ?? $bill->created_at->toIso8601String(),
                    'title'   => "Bill #{$bill->bill_number}",
                    'status'  => $bill->payment_status?->value,
                    'details' => [
                        'total_amount' => $bill->total_amount,
                        'balance_due'  => $bill->balance_due,
                        'currency'     => $bill->currency,
                    ],
                ]);
            });
        });

        // Conversations
        $patient->conversations()->get()->each(function ($conversation) use ($timeline) {
            $timeline->push([
                'type'    => 'conversation',
                'id'      => $conversation->id,
                'date'    => $conversation->started_at?->toIso8601String() ?? $conversation->created_at->toIso8601String(),
                'title'   => 'AI Conversation (' . ($conversation->channel?->label() ?? 'Unknown') . ')',
                'status'  => $conversation->state,
                'details' => [
                    'language'      => $conversation->language,
                    'message_count' => count($conversation->messages ?? []),
                ],
            ]);
        });

        // Sort by date descending
        return $timeline
            ->sortByDesc('date')
            ->values()
            ->toArray();
    }

    /**
     * Update patient record with data extracted during AI intake.
     *
     * @param  Patient  $patient    The patient to update.
     * @param  array    $intakeData The extracted intake data.
     * @return Patient
     */
    public function updateFromIntake(Patient $patient, array $intakeData): Patient
    {
        $updatable = [];

        if (! empty($intakeData['name']) && $patient->first_name === 'Unknown') {
            $nameParts = explode(' ', $intakeData['name'], 2);
            $updatable['first_name'] = $nameParts[0];
            $updatable['last_name'] = $nameParts[1] ?? '';
        }

        if (! empty($intakeData['first_name']) && $patient->first_name === 'Unknown') {
            $updatable['first_name'] = $intakeData['first_name'];
        }

        if (! empty($intakeData['last_name']) && empty($patient->last_name)) {
            $updatable['last_name'] = $intakeData['last_name'];
        }

        if (! empty($intakeData['date_of_birth']) && empty($patient->date_of_birth)) {
            $updatable['date_of_birth'] = $intakeData['date_of_birth'];
        }

        if (! empty($intakeData['gender']) && empty($patient->gender)) {
            $updatable['gender'] = $intakeData['gender'];
        }

        if (! empty($intakeData['allergies'])) {
            $existing = $patient->allergies ?? [];
            $updatable['allergies'] = array_unique(array_merge($existing, (array) $intakeData['allergies']));
        }

        if (! empty($intakeData['current_medications'])) {
            $existing = $patient->current_medications ?? [];
            $updatable['current_medications'] = array_unique(array_merge($existing, (array) $intakeData['current_medications']));
        }

        if (! empty($intakeData['preferred_language'])) {
            $updatable['preferred_language'] = $intakeData['preferred_language'];
        }

        if (! empty($updatable)) {
            $patient->update($updatable);
            $this->logInfo('Patient updated from intake', ['patient_id' => $patient->id, 'fields' => array_keys($updatable)]);
        }

        return $patient->fresh();
    }

    /**
     * Merge duplicate patient records.
     *
     * Keeps the primary record and migrates all relationships from the duplicate.
     *
     * @param  string  $primaryId    The primary patient ID to keep.
     * @param  string  $duplicateId  The duplicate patient ID to merge and soft-delete.
     * @return Patient
     */
    public function mergePatients(string $primaryId, string $duplicateId): Patient
    {
        $primary = Patient::findOrFail($primaryId);
        $duplicate = Patient::findOrFail($duplicateId);

        DB::transaction(function () use ($primary, $duplicate) {
            // Migrate encounters
            Encounter::where('patient_id', $duplicate->id)
                ->update(['patient_id' => $primary->id]);

            // Migrate appointments
            $duplicate->appointments()->update(['patient_id' => $primary->id]);

            // Migrate conversations
            $duplicate->conversations()->update(['patient_id' => $primary->id]);

            // Merge medical history
            $primaryHistory = $primary->medical_history ?? [];
            $duplicateHistory = $duplicate->medical_history ?? [];
            $primary->medical_history = array_merge($primaryHistory, $duplicateHistory);

            // Merge allergies
            $primaryAllergies = $primary->allergies ?? [];
            $duplicateAllergies = $duplicate->allergies ?? [];
            $primary->allergies = array_unique(array_merge($primaryAllergies, $duplicateAllergies));

            // Merge medications
            $primaryMeds = $primary->current_medications ?? [];
            $duplicateMeds = $duplicate->current_medications ?? [];
            $primary->current_medications = array_unique(array_merge($primaryMeds, $duplicateMeds));

            // Fill in any missing fields from duplicate
            if (empty($primary->email) && ! empty($duplicate->email)) {
                $primary->email = $duplicate->email;
            }
            if (empty($primary->date_of_birth) && ! empty($duplicate->date_of_birth)) {
                $primary->date_of_birth = $duplicate->date_of_birth;
            }
            if (empty($primary->national_id) && ! empty($duplicate->national_id)) {
                $primary->national_id = $duplicate->national_id;
            }

            // Store merge metadata
            $metadata = $primary->metadata ?? [];
            $metadata['merged_records'] = $metadata['merged_records'] ?? [];
            $metadata['merged_records'][] = [
                'merged_id' => $duplicate->id,
                'merged_at' => now()->toIso8601String(),
            ];
            $primary->metadata = $metadata;

            $primary->save();

            // Soft-delete the duplicate
            $duplicate->delete();
        });

        $this->logInfo('Patients merged', ['primary_id' => $primaryId, 'duplicate_id' => $duplicateId]);

        return $primary->fresh();
    }
}
