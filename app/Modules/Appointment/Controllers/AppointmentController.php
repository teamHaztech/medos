<?php

namespace App\Modules\Appointment\Controllers;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\SchedulingService;
use App\Modules\Core\Enums\AppointmentStatus;
use App\Modules\Queue\Services\QueueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    public function __construct(
        protected SchedulingService $schedulingService,
        protected QueueService $queueService,
    ) {}

    /**
     * List appointments (filterable by date, doctor, status, patient).
     *
     * GET /api/appointments
     */
    public function index(Request $request): JsonResponse
    {
        $query = Appointment::query();

        if ($request->filled('date')) {
            $query->whereDate('slot_start', $request->input('date'));
        }

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->input('doctor_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        $appointments = $query->with(['patient', 'doctor'])
            ->orderBy('slot_start', 'asc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $appointments,
            'message' => 'Appointments retrieved successfully.',
        ]);
    }

    /**
     * Create an appointment manually.
     *
     * POST /api/appointments
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id'   => 'required|uuid|exists:patients,id',
            'doctor_id'    => 'required|uuid|exists:staff,id',
            'slot_start'   => 'required|date|after:now',
            'encounter_id' => 'nullable|uuid|exists:encounters,id',
            'reason'       => 'nullable|string|max:500',
            'channel'      => 'nullable|string|in:walk_in,phone,whatsapp,web,ai',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        $appointment = $this->schedulingService->bookAppointment(
            patientId: $request->input('patient_id'),
            doctorId: $request->input('doctor_id'),
            slotStart: Carbon::parse($request->input('slot_start')),
            encounterId: $request->input('encounter_id', ''),
            source: $request->input('channel', 'web'),
        );

        if ($request->filled('reason')) {
            $appointment->reason = $request->input('reason');
            $appointment->save();
        }

        return response()->json([
            'success' => true,
            'data'    => $appointment->load(['patient', 'doctor']),
            'message' => 'Appointment created successfully.',
        ], 201);
    }

    /**
     * Get appointment details.
     *
     * GET /api/appointments/{id}
     */
    public function show(string $id): JsonResponse
    {
        $appointment = Appointment::with(['patient', 'doctor', 'encounter', 'queueEntry'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $appointment,
            'message' => 'Appointment retrieved successfully.',
        ]);
    }

    /**
     * Update appointment.
     *
     * PUT /api/appointments/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'slot_start' => 'nullable|date|after:now',
            'reason'     => 'nullable|string|max:500',
            'notes'      => 'nullable|string|max:1000',
            'status'     => 'nullable|string|in:' . implode(',', array_column(AppointmentStatus::cases(), 'value')),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        if ($request->filled('slot_start')) {
            $appointment = $this->schedulingService->rescheduleAppointment(
                $id,
                Carbon::parse($request->input('slot_start')),
            );
        }

        $appointment->fill($request->only(['reason', 'notes', 'status']));
        $appointment->save();

        return response()->json([
            'success' => true,
            'data'    => $appointment->fresh(['patient', 'doctor']),
            'message' => 'Appointment updated successfully.',
        ]);
    }

    /**
     * Check in a patient for their appointment.
     *
     * POST /api/appointments/{id}/check-in
     */
    public function checkIn(string $id): JsonResponse
    {
        $appointment = Appointment::findOrFail($id);

        if (! $appointment->status->isActive()) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Cannot check in — appointment is not in an active state.',
            ], 422);
        }

        $appointment->checkIn();

        // Add patient to the queue
        $queueEntry = $this->queueService->addToQueue($appointment);

        return response()->json([
            'success' => true,
            'data'    => [
                'appointment'  => $appointment,
                'queue_entry'  => $queueEntry,
                'token'        => $queueEntry->queue_number,
                'position'     => $queueEntry->position,
            ],
            'message' => 'Patient checked in and added to queue.',
        ]);
    }

    /**
     * Cancel an appointment.
     *
     * POST /api/appointments/{id}/cancel
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        $this->schedulingService->cancelAppointment($id, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Appointment cancelled successfully.',
        ]);
    }

    /**
     * Get today's appointments for the authenticated doctor.
     *
     * GET /api/doctor/appointments/today
     */
    public function todayForDoctor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Resolve doctor (staff) from the authenticated user
        $staff = $user?->staff ?? null;

        if (! $staff) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Authenticated user is not linked to a staff record.',
            ], 403);
        }

        $appointments = Appointment::where('doctor_id', $staff->id)
            ->whereDate('slot_start', today())
            ->whereNotIn('status', [
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::Rescheduled->value,
            ])
            ->with(['patient', 'queueEntry'])
            ->orderBy('slot_start', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $appointments,
            'message' => "Today's appointments retrieved successfully.",
        ]);
    }
}
