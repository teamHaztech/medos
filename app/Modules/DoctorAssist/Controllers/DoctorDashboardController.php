<?php

namespace App\Modules\DoctorAssist\Controllers;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Enums\AppointmentStatus;
use App\Modules\Core\Enums\EncounterStatus;
use App\Modules\Core\Enums\OrderType;
use App\Modules\DoctorAssist\Services\BriefingService;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class DoctorDashboardController extends Controller
{
    public function __construct(
        private BriefingService $briefingService,
    ) {}

    // ---------------------------------------------------------------
    // Dashboard Overview
    // ---------------------------------------------------------------

    /**
     * Today's summary for the logged-in doctor.
     */
    public function overview(Request $request): JsonResponse
    {
        $doctor = $request->user()->staff;
        $today = now()->toDateString();

        // Patient count today
        $patientCount = Encounter::where('doctor_id', $doctor->id)
            ->whereDate('created_at', $today)
            ->count();

        // Completed consultations
        $completedCount = Encounter::where('doctor_id', $doctor->id)
            ->whereDate('created_at', $today)
            ->where('status', EncounterStatus::Completed)
            ->count();

        // Current queue depth
        $queueDepth = QueueEntry::whereHas('appointment', function ($q) use ($doctor) {
                $q->where('doctor_id', $doctor->id);
            })
            ->whereNull('called_at')
            ->count();

        // Next patient
        $nextQueueEntry = QueueEntry::whereHas('appointment', function ($q) use ($doctor) {
                $q->where('doctor_id', $doctor->id);
            })
            ->whereNull('called_at')
            ->orderBy('priority_score', 'desc')
            ->orderBy('created_at', 'asc')
            ->with(['appointment.patient'])
            ->first();

        $nextPatient = null;
        if ($nextQueueEntry && $nextQueueEntry->appointment) {
            $patient = $nextQueueEntry->appointment->patient;
            $nextPatient = [
                'id'              => $patient->id,
                'name'            => $patient->full_name,
                'reason'          => $nextQueueEntry->appointment->reason,
                'queue_position'  => $nextQueueEntry->appointment->queue_position,
                'appointment_id'  => $nextQueueEntry->appointment->id,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'patients_today'  => $patientCount,
                'completed'       => $completedCount,
                'queue_depth'     => $queueDepth,
                'next_patient'    => $nextPatient,
                'date'            => $today,
            ],
            'message' => 'Dashboard overview retrieved successfully.',
        ]);
    }

    /**
     * Active queue with patient briefings.
     */
    public function currentQueue(Request $request): JsonResponse
    {
        $doctor = $request->user()->staff;

        $queueEntries = QueueEntry::whereHas('appointment', function ($q) use ($doctor) {
                $q->where('doctor_id', $doctor->id)
                    ->whereIn('status', [
                        AppointmentStatus::CheckedIn,
                        AppointmentStatus::Confirmed,
                    ]);
            })
            ->whereNull('called_at')
            ->orderBy('priority_score', 'desc')
            ->orderBy('created_at', 'asc')
            ->with(['appointment.patient', 'appointment.encounter'])
            ->get();

        $queue = $queueEntries->map(function ($entry) {
            $appointment = $entry->appointment;
            $patient = $appointment->patient;

            return [
                'queue_entry_id'  => $entry->id,
                'appointment_id'  => $appointment->id,
                'patient'         => [
                    'id'       => $patient->id,
                    'name'     => $patient->full_name,
                    'phone'    => $patient->phone,
                    'age'      => $patient->date_of_birth ? $patient->date_of_birth->age : null,
                    'gender'   => $patient->gender,
                    'language' => $patient->preferred_language,
                ],
                'reason'          => $appointment->reason,
                'slot_start'      => $appointment->slot_start?->toIso8601String(),
                'check_in_time'   => $appointment->check_in_time?->toIso8601String(),
                'wait_minutes'    => $appointment->check_in_time ? $appointment->check_in_time->diffInMinutes(now()) : null,
                'priority_score'  => $entry->priority_score,
                'encounter_id'    => $appointment->encounter?->id,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $queue,
            'message' => 'Current queue retrieved successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Patient Briefing
    // ---------------------------------------------------------------

    /**
     * AI-generated pre-consultation summary for an encounter.
     */
    public function patientBriefing(string $encounterId): JsonResponse
    {
        $encounter = Encounter::with(['patient', 'doctor', 'appointment'])->findOrFail($encounterId);

        $briefing = $this->briefingService->generateBriefing($encounter);

        return response()->json([
            'success' => true,
            'data'    => $briefing,
            'message' => 'Patient briefing generated successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Consultation Actions
    // ---------------------------------------------------------------

    /**
     * Save SOAP notes, mark encounter complete, trigger post-consultation workflows.
     */
    public function completeConsultation(Request $request, string $encounterId): JsonResponse
    {
        $encounter = Encounter::findOrFail($encounterId);

        $validated = $request->validate([
            'soap_notes'      => 'required|array',
            'soap_notes.subjective'  => 'required|string',
            'soap_notes.objective'   => 'required|string',
            'soap_notes.assessment'  => 'required|string',
            'soap_notes.plan'        => 'required|string',
            'diagnosis_codes'  => 'nullable|array',
            'diagnosis_codes.*' => 'string',
            'summary'          => 'nullable|string|max:1000',
            'follow_up_date'   => 'nullable|date|after:today',
            'orders'           => 'nullable|array',
            'orders.*.type'    => ['required_with:orders', Rule::in(['lab', 'pharmacy', 'imaging', 'procedure'])],
            'orders.*.details' => 'required_with:orders|array',
        ]);

        // Save SOAP notes and diagnosis
        $encounter->soap_notes = $validated['soap_notes'];
        $encounter->diagnosis_codes = $validated['diagnosis_codes'] ?? $encounter->diagnosis_codes;
        $encounter->summary = $validated['summary'] ?? $encounter->summary;
        $encounter->markAs(EncounterStatus::Completed);

        // Create orders if provided
        if (! empty($validated['orders'])) {
            foreach ($validated['orders'] as $orderData) {
                $encounter->orders()->create([
                    'hospital_id' => $encounter->hospital_id,
                    'type'        => $orderData['type'],
                    'details'     => $orderData['details'],
                    'status'      => 'pending',
                ]);
            }
        }

        // Complete the associated appointment
        if ($encounter->appointment) {
            $encounter->appointment->complete();
        }

        // Store follow-up date in metadata for engagement module
        if (! empty($validated['follow_up_date'])) {
            $encounter->update([
                'intake_data' => array_merge($encounter->intake_data ?? [], [
                    'follow_up_date' => $validated['follow_up_date'],
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $encounter->fresh()->load(['orders', 'patient']),
            'message' => 'Consultation completed successfully.',
        ]);
    }

    /**
     * Add lab/pharmacy/imaging orders to an encounter.
     */
    public function addOrders(Request $request, string $encounterId): JsonResponse
    {
        $encounter = Encounter::findOrFail($encounterId);

        $validated = $request->validate([
            'orders'           => 'required|array|min:1',
            'orders.*.type'    => ['required', Rule::in(['lab', 'pharmacy', 'imaging', 'procedure'])],
            'orders.*.details' => 'required|array',
            'orders.*.details.name'         => 'required|string',
            'orders.*.details.instructions' => 'nullable|string',
            'orders.*.details.priority'     => ['nullable', Rule::in(['routine', 'urgent', 'stat'])],
        ]);

        $createdOrders = [];

        foreach ($validated['orders'] as $orderData) {
            $order = $encounter->orders()->create([
                'hospital_id' => $encounter->hospital_id,
                'type'        => $orderData['type'],
                'details'     => $orderData['details'],
                'status'      => 'pending',
            ]);

            $createdOrders[] = $order;
        }

        return response()->json([
            'success' => true,
            'data'    => $createdOrders,
            'message' => count($createdOrders) . ' order(s) added successfully.',
        ]);
    }
}
