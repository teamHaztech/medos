<?php

namespace App\Modules\Queue\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Enums\AppointmentStatus;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Queue\Events\PatientCalled;
use App\Modules\Queue\Events\QueueUpdated;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class QueueService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    /**
     * Add an appointment to the queue with a calculated priority score and token number.
     */
    public function addToQueue(Appointment $appointment): QueueEntry
    {
        $hospitalId = $this->requireHospital();

        $priorityScore = $this->calculatePriorityScore($appointment);
        $token = $this->generateToken($hospitalId, $appointment->doctor_id);

        // Determine position based on priority
        $currentQueue = $this->getQueue($appointment->doctor_id);
        $position = 1;

        foreach ($currentQueue as $entry) {
            if ($entry->priority >= $priorityScore) {
                break;
            }
            $position++;
        }

        $queueEntry = QueueEntry::create([
            'id'             => Str::uuid()->toString(),
            'hospital_id'    => $hospitalId,
            'appointment_id' => $appointment->id,
            'patient_id'     => $appointment->patient_id,
            'doctor_id'      => $appointment->doctor_id,
            'queue_number'   => $token,
            'position'       => $position,
            'status'         => 'waiting',
            'priority'       => (int) round($priorityScore * 100), // Store as integer (0-100)
        ]);

        // Re-sort the queue to accommodate the new entry
        $this->reshuffleQueue($appointment->doctor_id);

        $this->logInfo('Patient added to queue', [
            'queue_entry_id' => $queueEntry->id,
            'appointment_id' => $appointment->id,
            'token'          => $token,
            'priority'       => $priorityScore,
        ]);

        return $queueEntry;
    }

    /**
     * Get the ordered queue for a doctor (waiting and called entries).
     */
    public function getQueue(string $doctorId): Collection
    {
        return QueueEntry::where('doctor_id', $doctorId)
            ->where('hospital_id', $this->requireHospital())
            ->whereIn('status', ['waiting', 'called'])
            ->whereDate('created_at', today())
            ->orderBy('position', 'asc')
            ->get();
    }

    /**
     * Call the next patient in the queue.
     */
    public function callNext(string $doctorId): ?QueueEntry
    {
        $hospitalId = $this->requireHospital();

        // Mark any currently-called patient as 'seen' (they are being attended to)
        QueueEntry::where('doctor_id', $doctorId)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'called')
            ->whereDate('created_at', today())
            ->update([
                'status'  => 'seen',
                'seen_at' => now(),
            ]);

        // Get next waiting patient
        $next = QueueEntry::where('doctor_id', $doctorId)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'waiting')
            ->whereDate('created_at', today())
            ->orderBy('position', 'asc')
            ->first();

        if (! $next) {
            return null;
        }

        $next->call();

        // Dispatch event
        $this->dispatchEvent(new PatientCalled(
            patientId: $next->patient_id,
            doctorId: $doctorId,
            appointmentId: $next->appointment_id,
            queueEntryId: $next->id,
            hospitalId: $hospitalId,
        ));

        // Update wait estimates for remaining patients
        $this->updateEstimatedWaits($doctorId);

        $this->logInfo('Next patient called', [
            'queue_entry_id' => $next->id,
            'patient_id'     => $next->patient_id,
            'doctor_id'      => $doctorId,
        ]);

        return $next;
    }

    /**
     * Recalculate estimated wait times for all waiting patients of a doctor.
     */
    public function updateEstimatedWaits(string $doctorId): void
    {
        $queue = $this->getQueue($doctorId);
        $doctor = Staff::find($doctorId);
        $avgDuration = $doctor?->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        $waitingPosition = 0;

        foreach ($queue as $entry) {
            if ($entry->status === 'called') {
                continue; // Currently being seen, skip
            }

            $estimatedWait = $waitingPosition * $avgDuration;
            $entry->notes = json_encode(array_merge(
                json_decode($entry->notes ?? '{}', true) ?: [],
                ['estimated_wait_minutes' => $estimatedWait],
            ));
            $entry->save();

            $waitingPosition++;
        }
    }

    /**
     * Calculate the priority score for an appointment using config weights.
     *
     * Higher score = higher priority (seen sooner).
     */
    public function calculatePriorityScore(Appointment $appointment): float
    {
        $weights = [
            'urgency'     => config('medos.queue.urgency_weight', 0.40),
            'wait'        => config('medos.queue.wait_weight', 0.25),
            'appointment' => config('medos.queue.appointment_weight', 0.20),
            'age'         => config('medos.queue.age_weight', 0.10),
            'vip'         => config('medos.queue.vip_weight', 0.05),
        ];

        // Urgency score (0-1): based on triage classification or appointment priority
        $urgencyScore = $this->getUrgencyScore($appointment);

        // Wait score (0-1): how long the patient has been waiting (increases over time)
        $waitScore = $this->getWaitScore($appointment);

        // Appointment score (0-1): having a scheduled appointment vs walk-in
        $appointmentScore = $appointment->slot_start ? 1.0 : 0.5;
        if ($appointment->status === AppointmentStatus::CheckedIn) {
            $appointmentScore = 1.0;
        }

        // Age score (0-1): very young or very old get priority
        $ageScore = $this->getAgeScore($appointment);

        // VIP score (0-1): based on patient metadata
        $vipScore = $this->getVipScore($appointment);

        $totalScore = ($urgencyScore * $weights['urgency'])
            + ($waitScore * $weights['wait'])
            + ($appointmentScore * $weights['appointment'])
            + ($ageScore * $weights['age'])
            + ($vipScore * $weights['vip']);

        return round(min(1.0, $totalScore), 4);
    }

    /**
     * Reshuffle the queue: re-evaluate priorities and reorder.
     * Returns list of patients whose position changed.
     */
    public function reshuffleQueue(string $doctorId): array
    {
        $hospitalId = $this->requireHospital();

        $entries = QueueEntry::where('doctor_id', $doctorId)
            ->where('hospital_id', $hospitalId)
            ->where('status', 'waiting')
            ->whereDate('created_at', today())
            ->with('appointment')
            ->get();

        // Recalculate priorities
        $oldPositions = [];
        foreach ($entries as $entry) {
            $oldPositions[$entry->id] = $entry->position;

            if ($entry->appointment) {
                $newPriority = $this->calculatePriorityScore($entry->appointment);
                $entry->priority = (int) round($newPriority * 100);
            }
        }

        // Sort by priority descending (higher priority = lower position number)
        $sorted = $entries->sortByDesc('priority')->values();

        $changes = [];

        foreach ($sorted as $index => $entry) {
            $newPosition = $index + 1;

            if (($oldPositions[$entry->id] ?? 0) !== $newPosition) {
                $changes[] = [
                    'patient_id'   => $entry->patient_id,
                    'old_position' => $oldPositions[$entry->id] ?? 0,
                    'new_position' => $newPosition,
                ];
            }

            $entry->position = $newPosition;
            $entry->save();
        }

        // Update estimated waits
        $this->updateEstimatedWaits($doctorId);

        // Dispatch event if there were changes
        if (! empty($changes)) {
            $queueSnapshot = $sorted->pluck('position', 'patient_id')->toArray();

            $this->dispatchEvent(new QueueUpdated(
                doctorId: $doctorId,
                queueSnapshot: $queueSnapshot,
                changes: $changes,
                hospitalId: $hospitalId,
            ));
        }

        return $changes;
    }

    /**
     * Generate a token like "GEN-001", "PED-015" based on department + daily sequential.
     */
    public function generateToken(string $hospitalId, string $doctorId): string
    {
        $doctor = Staff::find($doctorId);
        $department = strtoupper(substr($doctor?->department ?? $doctor?->specialty ?? 'GEN', 0, 3));

        // Count today's tokens for this department at this hospital
        $todayCount = QueueEntry::where('hospital_id', $hospitalId)
            ->where('queue_number', 'like', "{$department}-%")
            ->whereDate('created_at', today())
            ->count();

        $nextNumber = $todayCount + 1;

        return sprintf('%s-%03d', $department, $nextNumber);
    }

    /**
     * Calculate estimated wait in minutes based on avg consultation time * patients ahead.
     */
    public function getEstimatedWait(string $doctorId, int $position): int
    {
        $doctor = Staff::find($doctorId);
        $avgDuration = $doctor?->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        // Patients ahead = position - 1 (position 1 is next)
        $patientsAhead = max(0, $position - 1);

        return $patientsAhead * $avgDuration;
    }

    /**
     * Get a patient's current position and estimated wait for a specific doctor's queue.
     */
    public function getPatientPosition(string $patientId, string $doctorId): ?array
    {
        $entry = QueueEntry::where('patient_id', $patientId)
            ->where('doctor_id', $doctorId)
            ->where('hospital_id', $this->requireHospital())
            ->whereIn('status', ['waiting', 'called'])
            ->whereDate('created_at', today())
            ->first();

        if (! $entry) {
            return null;
        }

        return [
            'queue_entry_id'       => $entry->id,
            'token'                => $entry->queue_number,
            'position'             => $entry->position,
            'status'               => $entry->status,
            'estimated_wait_minutes' => $entry->status === 'called'
                ? 0
                : $this->getEstimatedWait($doctorId, $entry->position),
        ];
    }

    // ------------------------------------------------------------------
    // Score Helpers
    // ------------------------------------------------------------------

    protected function getUrgencyScore(Appointment $appointment): float
    {
        $encounter = $appointment->encounter;
        if ($encounter && ! empty($encounter->triage_score)) {
            return (float) $encounter->triage_score;
        }

        // Default based on appointment status
        return match ($appointment->status) {
            AppointmentStatus::CheckedIn  => 0.5,
            AppointmentStatus::Confirmed  => 0.4,
            AppointmentStatus::Scheduled  => 0.3,
            default                       => 0.2,
        };
    }

    protected function getWaitScore(Appointment $appointment): float
    {
        if (! $appointment->check_in_time) {
            return 0.0;
        }

        $waitMinutes = $appointment->check_in_time->diffInMinutes(now());
        $maxEscalation = config('medos.queue.max_wait_escalation_minutes', 45);
        $increment = config('medos.queue.starvation_increment_per_minute', 0.01);

        return min(1.0, $waitMinutes * $increment);
    }

    protected function getAgeScore(Appointment $appointment): float
    {
        $patient = $appointment->patient;
        if (! $patient || ! $patient->date_of_birth) {
            return 0.0;
        }

        $age = $patient->date_of_birth->age;

        if ($age < 2) {
            return 1.0;
        }
        if ($age < 5) {
            return 0.8;
        }
        if ($age > 80) {
            return 0.9;
        }
        if ($age > 65) {
            return 0.7;
        }

        return 0.0;
    }

    protected function getVipScore(Appointment $appointment): float
    {
        $patient = $appointment->patient;
        if (! $patient) {
            return 0.0;
        }

        $metadata = $patient->metadata ?? [];
        return ! empty($metadata['vip']) ? 1.0 : 0.0;
    }
}
