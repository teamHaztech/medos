<?php

namespace App\Modules\Queue\Controllers;

use App\Modules\Queue\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QueueController extends Controller
{
    public function __construct(protected QueueService $queueService)
    {}

    /**
     * Get current queue for a doctor.
     *
     * GET /api/queue/doctor/{doctorId}
     */
    public function doctorQueue(string $doctorId): JsonResponse
    {
        $queue = $this->queueService->getQueue($doctorId);

        $data = $queue->map(function ($entry) use ($doctorId) {
            return [
                'id'              => $entry->id,
                'patient_id'      => $entry->patient_id,
                'patient_name'    => $entry->patient?->full_name ?? $entry->patient_id,
                'appointment_id'  => $entry->appointment_id,
                'token'           => $entry->queue_number,
                'position'        => $entry->position,
                'status'          => $entry->status,
                'priority'        => $entry->priority,
                'estimated_wait'  => $entry->status === 'called'
                    ? 0
                    : $this->queueService->getEstimatedWait($doctorId, $entry->position),
                'checked_in_at'   => $entry->appointment?->check_in_time?->toIso8601String(),
                'called_at'       => $entry->called_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'doctor_id'    => $doctorId,
                'total'        => $data->count(),
                'queue'        => $data->values(),
            ],
            'message' => 'Queue retrieved successfully.',
        ]);
    }

    /**
     * Call the next patient in the queue.
     *
     * POST /api/queue/doctor/{doctorId}/call-next
     */
    public function callNext(string $doctorId): JsonResponse
    {
        $entry = $this->queueService->callNext($doctorId);

        if (! $entry) {
            return response()->json([
                'success' => true,
                'data'    => null,
                'message' => 'No patients waiting in the queue.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'queue_entry_id' => $entry->id,
                'patient_id'     => $entry->patient_id,
                'patient_name'   => $entry->patient?->full_name ?? $entry->patient_id,
                'appointment_id' => $entry->appointment_id,
                'token'          => $entry->queue_number,
                'called_at'      => $entry->called_at?->toIso8601String(),
            ],
            'message' => 'Next patient has been called.',
        ]);
    }

    /**
     * Get a patient's queue position and estimated wait time.
     *
     * GET /api/queue/patient/{patientId}
     */
    public function patientStatus(string $patientId, Request $request): JsonResponse
    {
        $doctorId = $request->query('doctor_id');

        if (! $doctorId) {
            // Try to find the patient's active queue entry for any doctor
            $entry = \App\Modules\Appointment\Models\QueueEntry::where('patient_id', $patientId)
                ->whereIn('status', ['waiting', 'called'])
                ->whereDate('created_at', today())
                ->first();

            if (! $entry) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'message' => 'Patient is not in any active queue.',
                ], 404);
            }

            $doctorId = $entry->doctor_id;
        }

        $position = $this->queueService->getPatientPosition($patientId, $doctorId);

        if (! $position) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Patient is not in the queue for this doctor.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $position,
            'message' => 'Patient queue status retrieved successfully.',
        ]);
    }
}
