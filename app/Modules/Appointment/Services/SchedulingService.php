<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Enums\AppointmentStatus;
use App\Modules\Core\Enums\UserRole;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SchedulingService extends BaseModuleService
{
    protected SlotGenerator $slotGenerator;

    public function __construct(HospitalContext $hospitalContext, SlotGenerator $slotGenerator)
    {
        parent::__construct($hospitalContext);
        $this->slotGenerator = $slotGenerator;
    }

    /**
     * Find optimal appointment slots based on given criteria.
     *
     * @param  array $criteria Keys: specialty, urgency (1-4), patient_preferences (time_of_day, specific_doctor),
     *                         insurance_network, date_range (start, end)
     * @return array Array of slot options with: doctor, start_time, end_time, estimated_wait_minutes, cost_estimate, score
     */
    public function findOptimalSlots(array $criteria): array
    {
        $hospitalId = $this->requireHospital();

        $specialty = $criteria['specialty'] ?? null;
        $urgency = $criteria['urgency'] ?? 4; // 1=emergency, 4=routine
        $preferences = $criteria['patient_preferences'] ?? [];
        $insuranceNetwork = $criteria['insurance_network'] ?? null;
        $dateRange = $criteria['date_range'] ?? [
            'start' => now()->toDateString(),
            'end'   => now()->addDays(7)->toDateString(),
        ];

        // 1. Find available doctors matching specialty + insurance network
        $doctorsQuery = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->where('role', UserRole::Doctor);

        if ($specialty) {
            $doctorsQuery->where('specialty', $specialty);
        }

        if (! empty($preferences['specific_doctor'])) {
            $doctorsQuery->where('id', $preferences['specific_doctor']);
        }

        $doctors = $doctorsQuery->get();

        if ($doctors->isEmpty()) {
            return [];
        }

        $allOptions = [];

        // 2. For each doctor, find available slots in the date range
        $startDate = Carbon::parse($dateRange['start']);
        $endDate = Carbon::parse($dateRange['end']);

        foreach ($doctors as $doctor) {
            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                $slotDuration = $this->predictConsultationDuration(
                    $doctor->id,
                    $criteria['complaint_type'] ?? 'general',
                );

                $slots = $this->slotGenerator->generate($doctor, $currentDate, $slotDuration);

                foreach ($slots as $slot) {
                    if (! $slot['available']) {
                        continue;
                    }

                    // Skip past slots
                    if ($slot['start']->lt(now())) {
                        continue;
                    }

                    $score = $this->scoreSlot($slot, $doctor, $urgency, $preferences);

                    $allOptions[] = [
                        'doctor'                 => [
                            'id'        => $doctor->id,
                            'name'      => $doctor->full_name,
                            'specialty' => $doctor->specialty,
                        ],
                        'start_time'             => $slot['start']->toIso8601String(),
                        'end_time'               => $slot['end']->toIso8601String(),
                        'estimated_wait_minutes' => $this->estimateWaitMinutes($doctor, $slot['start']),
                        'cost_estimate'          => null, // To be filled by billing module
                        'score'                  => $score,
                    ];
                }

                $currentDate->addDay();
            }
        }

        // Sort by score descending, return top 5
        usort($allOptions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($allOptions, 0, 5);
    }

    /**
     * Book an appointment for a patient.
     */
    public function bookAppointment(
        string $patientId,
        string $doctorId,
        Carbon $slotStart,
        string $encounterId,
        string $source = 'ai',
    ): Appointment {
        $hospitalId = $this->requireHospital();

        $doctor = Staff::findOrFail($doctorId);
        $slotDuration = $doctor->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        $appointment = Appointment::create([
            'id'           => Str::uuid()->toString(),
            'hospital_id'  => $hospitalId,
            'patient_id'   => $patientId,
            'doctor_id'    => $doctorId,
            'encounter_id' => $encounterId,
            'status'       => AppointmentStatus::Scheduled,
            'slot_start'   => $slotStart,
            'slot_end'     => $slotStart->copy()->addMinutes($slotDuration),
            'channel'      => $source,
        ]);

        $this->logInfo('Appointment booked', [
            'appointment_id' => $appointment->id,
            'patient_id'     => $patientId,
            'doctor_id'      => $doctorId,
            'slot_start'     => $slotStart->toIso8601String(),
        ]);

        return $appointment;
    }

    /**
     * Cancel an appointment.
     */
    public function cancelAppointment(string $appointmentId, string $reason): bool
    {
        $appointment = Appointment::where('hospital_id', $this->requireHospital())
            ->findOrFail($appointmentId);

        $appointment->status = AppointmentStatus::Cancelled;
        $appointment->notes = $reason;
        $appointment->save();

        $this->logInfo('Appointment cancelled', [
            'appointment_id' => $appointmentId,
            'reason'         => $reason,
        ]);

        return true;
    }

    /**
     * Reschedule an appointment to a new time slot.
     */
    public function rescheduleAppointment(string $appointmentId, Carbon $newSlotStart): Appointment
    {
        $hospitalId = $this->requireHospital();

        $oldAppointment = Appointment::where('hospital_id', $hospitalId)
            ->findOrFail($appointmentId);

        // Mark old appointment as rescheduled
        $oldAppointment->status = AppointmentStatus::Rescheduled;
        $oldAppointment->save();

        $doctor = Staff::findOrFail($oldAppointment->doctor_id);
        $slotDuration = $doctor->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        // Create new appointment linked to the old one
        $newAppointment = Appointment::create([
            'id'                  => Str::uuid()->toString(),
            'hospital_id'         => $hospitalId,
            'patient_id'          => $oldAppointment->patient_id,
            'doctor_id'           => $oldAppointment->doctor_id,
            'encounter_id'        => $oldAppointment->encounter_id,
            'rescheduled_from_id' => $oldAppointment->id,
            'status'              => AppointmentStatus::Scheduled,
            'slot_start'          => $newSlotStart,
            'slot_end'            => $newSlotStart->copy()->addMinutes($slotDuration),
            'channel'             => $oldAppointment->channel,
        ]);

        $this->logInfo('Appointment rescheduled', [
            'old_appointment_id' => $appointmentId,
            'new_appointment_id' => $newAppointment->id,
            'new_slot_start'     => $newSlotStart->toIso8601String(),
        ]);

        return $newAppointment;
    }

    /**
     * Get available time blocks for a doctor on a specific date.
     */
    public function getDoctorAvailability(string $doctorId, Carbon $date): array
    {
        $doctor = Staff::where('hospital_id', $this->requireHospital())
            ->findOrFail($doctorId);

        $slotDuration = $doctor->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        $slots = $this->slotGenerator->generate($doctor, $date, $slotDuration);

        return array_filter($slots, fn ($slot) => $slot['available']);
    }

    /**
     * Predict consultation duration based on historical data.
     * Falls back to doctor's default or system default.
     */
    public function predictConsultationDuration(string $doctorId, string $complaintType): int
    {
        // Try to get historical average for this doctor
        $completedAppts = Appointment::where('doctor_id', $doctorId)
            ->whereNotNull('consultation_start_time')
            ->whereNotNull('consultation_end_time')
            ->get(['consultation_start_time', 'consultation_end_time']);

        if ($completedAppts->count() > 0) {
            $avg = $completedAppts->avg(fn ($a) =>
                Carbon::parse($a->consultation_end_time)->diffInMinutes(Carbon::parse($a->consultation_start_time))
            );
            if ($avg > 0) {
                return (int) ceil($avg);
            }
        }

        // Fall back to doctor's default duration
        $doctor = Staff::find($doctorId);
        if ($doctor && $doctor->consultation_duration_default) {
            return $doctor->consultation_duration_default;
        }

        // System default
        return config('medos.scheduling.default_slot_duration', 15);
    }

    // ------------------------------------------------------------------
    // Scoring Helpers
    // ------------------------------------------------------------------

    /**
     * Score a slot based on urgency, preference match, wait time, and doctor load.
     */
    protected function scoreSlot(array $slot, Staff $doctor, int $urgency, array $preferences): float
    {
        $score = 0.0;

        // Urgency match: earlier slots score higher for urgent cases
        $hoursFromNow = now()->diffInHours($slot['start'], false);
        if ($urgency <= 2) {
            // Urgent: prefer sooner slots (max 40 points for within 2 hours)
            $score += max(0, 40 - ($hoursFromNow * 2));
        } else {
            // Routine: prefer slots that are not too soon, not too far
            $score += max(0, 30 - abs($hoursFromNow - 24));
        }

        // Time of day preference
        $preferredTime = $preferences['time_of_day'] ?? null;
        if ($preferredTime) {
            $slotHour = $slot['start']->hour;
            $match = match ($preferredTime) {
                'morning'   => $slotHour >= 8 && $slotHour < 12,
                'afternoon' => $slotHour >= 12 && $slotHour < 17,
                'evening'   => $slotHour >= 17 && $slotHour < 21,
                default     => false,
            };
            if ($match) {
                $score += 20;
            }
        }

        // Specific doctor preference
        if (! empty($preferences['specific_doctor']) && $doctor->id === $preferences['specific_doctor']) {
            $score += 15;
        }

        // Doctor load balance — fewer appointments today = higher score
        $todayCount = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('slot_start', $slot['start']->toDateString())
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->count();
        $score += max(0, 10 - $todayCount);

        return round($score, 2);
    }

    /**
     * Estimate wait time in minutes for a given slot.
     */
    protected function estimateWaitMinutes(Staff $doctor, Carbon $slotStart): int
    {
        // Count appointments before this slot on the same day that are not yet completed
        $ahead = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('slot_start', $slotStart->toDateString())
            ->where('slot_start', '<', $slotStart)
            ->whereNotIn('status', ['completed', 'cancelled', 'rescheduled', 'no_show'])
            ->count();

        $avgDuration = $doctor->consultation_duration_minutes
            ?? config('medos.scheduling.default_slot_duration', 15);

        // Estimate: patients ahead * avg duration, with a 20% buffer for delays
        return (int) ceil($ahead * $avgDuration * 0.2);
    }
}
