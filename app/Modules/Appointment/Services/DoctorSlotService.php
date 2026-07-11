<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use Carbon\Carbon;

/**
 * Builds a per-doctor availability calendar (N days of individual time slots with
 * available/booked/past flags) from the doctor's weekly `schedule`. Shared by the
 * public Book-Online page; mirrors the /ajax/doctor-slots staff endpoint.
 */
class DoctorSlotService
{
    /**
     * @param  object  $doctor  a Staff model or DB row (id, schedule, department, consultation_duration_default)
     * @return array  { doctor, duration, days:[{date,day,dayFull,dateFmt,is_today,slots:[{time,display,available,booked,past}],available,total}] }
     */
    public function calendar(object $doctor, int $days = 14): array
    {
        $schedule = is_array($doctor->schedule ?? null)
            ? $doctor->schedule
            : (json_decode($doctor->schedule ?? '{}', true) ?: []);
        $duration = $doctor->consultation_duration_default ?? 15;

        $out = [];
        for ($d = 0; $d < $days; $d++) {
            $date = now()->addDays($d);
            $blocks = $schedule[strtolower($date->format('l'))] ?? [];
            if (empty($blocks)) {
                continue;
            }

            $booked = Appointment::where('doctor_id', $doctor->id)
                ->whereDate('slot_start', $date->toDateString())
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->pluck('slot_start')
                ->map(fn ($s) => Carbon::parse($s)->format('H:i'))
                ->all();

            $slots = [];
            foreach ($blocks as $block) {
                $start = Carbon::parse($date->toDateString() . ' ' . ($block['start'] ?? '00:00'));
                $end = Carbon::parse($date->toDateString() . ' ' . ($block['end'] ?? '00:00'));
                while ($start->copy()->addMinutes($duration)->lte($end)) {
                    $timeStr = $start->format('H:i');
                    $isBooked = in_array($timeStr, $booked, true);
                    $isPast = $d === 0 && $start->lt(now());
                    $slots[] = [
                        'time'      => $timeStr,
                        'display'   => $start->format('g:i A'),
                        'available' => ! $isBooked && ! $isPast,
                        'booked'    => $isBooked,
                        'past'      => $isPast,
                    ];
                    $start->addMinutes($duration);
                }
            }

            if (! empty($slots)) {
                $out[] = [
                    'date'      => $date->toDateString(),
                    'day'       => $date->format('D'),
                    'dayFull'   => $date->format('l'),
                    'dateFmt'   => $date->format('M d'),
                    'is_today'  => $d === 0,
                    'slots'     => $slots,
                    'available' => collect($slots)->where('available', true)->count(),
                    'total'     => count($slots),
                ];
            }
        }

        return [
            'doctor'   => ['id' => $doctor->id, 'name' => $doctor->name, 'department' => $doctor->department],
            'duration' => $duration,
            'days'     => $out,
        ];
    }

    /** True if the doctor is working at $slotStart (used to validate a booking). */
    public function isWorkingAt(object $doctor, Carbon $slotStart): bool
    {
        $schedule = is_array($doctor->schedule ?? null)
            ? $doctor->schedule
            : (json_decode($doctor->schedule ?? '{}', true) ?: []);
        foreach ($schedule[strtolower($slotStart->format('l'))] ?? [] as $block) {
            $bs = Carbon::parse($slotStart->toDateString() . ' ' . ($block['start'] ?? '00:00'));
            $be = Carbon::parse($slotStart->toDateString() . ' ' . ($block['end'] ?? '00:00'));
            if ($slotStart->gte($bs) && $slotStart->lt($be)) {
                return true;
            }
        }

        return false;
    }
}
