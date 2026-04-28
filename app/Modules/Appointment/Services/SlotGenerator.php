<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Staff;
use Carbon\Carbon;

class SlotGenerator
{
    /**
     * Generate available time slots for a doctor on a given date.
     *
     * @param  Staff  $doctor       The doctor (staff member).
     * @param  Carbon $date         The date to generate slots for.
     * @param  int    $slotDuration Duration of each slot in minutes.
     * @return array  Array of ['start' => Carbon, 'end' => Carbon, 'available' => bool]
     */
    public function generate(Staff $doctor, Carbon $date, int $slotDuration): array
    {
        $schedule = $doctor->schedule ?? [];
        $dayOfWeek = strtolower($date->format('l'));

        if (! isset($schedule[$dayOfWeek])) {
            return [];
        }

        $daySchedule = $schedule[$dayOfWeek];

        if (empty($daySchedule['start']) || empty($daySchedule['end'])) {
            return [];
        }

        $workStart = Carbon::parse($date->format('Y-m-d') . ' ' . $daySchedule['start']);
        $workEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $daySchedule['end']);

        // Handle optional break periods (e.g., lunch)
        $breaks = $daySchedule['breaks'] ?? [];

        // Get existing appointments for this doctor on this date
        $bookedSlots = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('slot_start', $date)
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get(['slot_start', 'slot_end'])
            ->map(fn ($appt) => [
                'start' => Carbon::parse($appt->slot_start),
                'end'   => Carbon::parse($appt->slot_end),
            ])
            ->toArray();

        // Buffer configuration: add a buffer slot every N patients
        $bufferEvery = config('medos.scheduling.buffer_slots_every_n_patients', 5);

        $slots = [];
        $current = $workStart->copy();
        $slotIndex = 0;

        while ($current->copy()->addMinutes($slotDuration)->lte($workEnd)) {
            $slotEnd = $current->copy()->addMinutes($slotDuration);

            // Check if slot falls within a break
            $inBreak = false;
            foreach ($breaks as $break) {
                $breakStart = Carbon::parse($date->format('Y-m-d') . ' ' . $break['start']);
                $breakEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $break['end']);

                if ($current->lt($breakEnd) && $slotEnd->gt($breakStart)) {
                    $inBreak = true;
                    $current = $breakEnd->copy();
                    break;
                }
            }

            if ($inBreak) {
                continue;
            }

            // Check if this is a buffer slot
            $slotIndex++;
            if ($bufferEvery > 0 && $slotIndex > 0 && ($slotIndex % ($bufferEvery + 1)) === 0) {
                // This slot is reserved as a buffer — skip it but still advance time
                $slots[] = [
                    'start'     => $current->copy(),
                    'end'       => $slotEnd->copy(),
                    'available' => false,
                    'reason'    => 'buffer',
                ];
                $current->addMinutes($slotDuration);
                continue;
            }

            // Check if slot overlaps with any booked appointment
            $isBooked = false;
            foreach ($bookedSlots as $booked) {
                if ($current->lt($booked['end']) && $slotEnd->gt($booked['start'])) {
                    $isBooked = true;
                    break;
                }
            }

            $slots[] = [
                'start'     => $current->copy(),
                'end'       => $slotEnd->copy(),
                'available' => ! $isBooked,
            ];

            $current->addMinutes($slotDuration);
        }

        return $slots;
    }
}
