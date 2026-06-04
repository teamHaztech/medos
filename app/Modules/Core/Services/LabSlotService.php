<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\LabAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LabSlotService
{
    /**
     * Build the lab's available time slots for the next $days days, capacity-aware.
     * A slot is offered until the number of booked lab orders for that exact start
     * time reaches the configured capacity.
     *
     * @return array<int, array{start:string, label:string, remaining:int}>
     */
    public static function availableSlots(string $hospitalId, int $days = 7, int $limit = 12): array
    {
        $avail = LabAvailability::where('hospital_id', $hospitalId)->first();

        $schedule = $avail && ! empty($avail->schedule) ? $avail->schedule : LabAvailability::defaultSchedule();
        $duration = $avail->slot_duration ?? 15;
        $capacity = max(1, $avail->capacity ?? 4);

        if ($avail && ! $avail->is_active) {
            return [];
        }

        // Count existing lab bookings per slot start time (scheduled_for).
        $booked = DB::table('orders')
            ->where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging', 'procedure'])
            ->whereNotNull('scheduled_for')
            ->whereNotIn('status', ['cancelled'])
            ->where('scheduled_for', '>=', now()->startOfDay())
            ->selectRaw('scheduled_for, count(*) as c')
            ->groupBy('scheduled_for')
            ->pluck('c', 'scheduled_for'); // keyed by datetime string

        $slots = [];
        for ($d = 0; $d < $days && count($slots) < $limit; $d++) {
            $date = now()->addDays($d);
            $dayName = strtolower($date->format('l'));
            $blocks = $schedule[$dayName] ?? [];
            if (empty($blocks)) {
                continue;
            }

            foreach ($blocks as $block) {
                if (empty($block['start']) || empty($block['end'])) {
                    continue;
                }
                $start = Carbon::parse($date->toDateString() . ' ' . $block['start']);
                $end = Carbon::parse($date->toDateString() . ' ' . $block['end']);

                while ($start->copy()->addMinutes($duration)->lte($end)) {
                    $isPast = $d === 0 && $start->lt(now());
                    if (! $isPast) {
                        $key = $start->toDateTimeString();
                        $used = (int) ($booked[$key] ?? 0);
                        if ($used < $capacity) {
                            $slots[] = [
                                'start'     => $key,
                                'label'     => ($d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : $date->format('D, M d'))) . ' at ' . $start->format('g:i A'),
                                'remaining' => $capacity - $used,
                            ];
                            if (count($slots) >= $limit) {
                                break 2;
                            }
                        }
                    }
                    $start->addMinutes($duration);
                }
            }
        }

        return $slots;
    }
}
