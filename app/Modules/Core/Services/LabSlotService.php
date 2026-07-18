<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\LabAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        // Slot generation depends on the lab_availabilities table and the
        // orders.scheduled_for column. On a Hostinger DB where the schema lags
        // the code (migrations run manually via deploy.php), or if a hospital
        // saved a malformed schedule, any of the queries/date parsing below can
        // throw. Never let that surface as a chat crash — degrade to "no slots"
        // so the caller falls back to a same-day walk-in booking.
        try {
            return self::buildSlots($hospitalId, $days, $limit);
        } catch (\Throwable $e) {
            \Log::warning('[LabSlotService] availableSlots failed, falling back to walk-in: ' . $e->getMessage(), [
                'hospital_id' => $hospitalId,
            ]);

            return [];
        }
    }

    /** @return array<int, array{start:string, label:string, remaining:int}> */
    private static function buildSlots(string $hospitalId, int $days, int $limit): array
    {
        $avail = Schema::hasTable('lab_availabilities')
            ? LabAvailability::where('hospital_id', $hospitalId)->first()
            : null;

        $schedule = $avail && ! empty($avail->schedule) ? $avail->schedule : LabAvailability::defaultSchedule();
        $duration = $avail->slot_duration ?? 15;
        $capacity = max(1, $avail->capacity ?? 4);

        if ($avail && ! $avail->is_active) {
            return [];
        }

        // Count existing lab bookings per slot start time (scheduled_for).
        // Guard the column too — an un-migrated orders table has no scheduled_for.
        $booked = Schema::hasColumn('orders', 'scheduled_for')
            ? DB::table('orders')
                ->where('hospital_id', $hospitalId)
                ->whereIn('type', ['lab', 'imaging', 'procedure'])
                ->whereNotNull('scheduled_for')
                ->whereNotIn('status', ['cancelled'])
                ->where('scheduled_for', '>=', now()->startOfDay())
                ->selectRaw('scheduled_for, count(*) as c')
                ->groupBy('scheduled_for')
                ->pluck('c', 'scheduled_for') // keyed by datetime string
            : collect();

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

    /**
     * Check whether a specific datetime is a bookable lab slot: lab active, time
     * in the future, within an open block that day, and under capacity.
     * Returns the validated Carbon time, or null if not bookable.
     */
    public static function validateTime(string $hospitalId, Carbon $dt): ?Carbon
    {
        if ($dt->lte(now())) {
            return null;
        }

        $avail = LabAvailability::where('hospital_id', $hospitalId)->first();
        if ($avail && ! $avail->is_active) {
            return null;
        }

        $schedule = $avail && ! empty($avail->schedule) ? $avail->schedule : LabAvailability::defaultSchedule();
        $capacity = max(1, $avail->capacity ?? 4);

        $blocks = $schedule[strtolower($dt->format('l'))] ?? [];
        $inOpenBlock = false;
        foreach ($blocks as $b) {
            if (empty($b['start']) || empty($b['end'])) {
                continue;
            }
            $start = Carbon::parse($dt->toDateString() . ' ' . $b['start']);
            $end = Carbon::parse($dt->toDateString() . ' ' . $b['end']);
            if ($dt->gte($start) && $dt->lt($end)) {
                $inOpenBlock = true;
                break;
            }
        }
        if (! $inOpenBlock) {
            return null;
        }

        $used = DB::table('orders')
            ->where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging', 'procedure'])
            ->whereNotIn('status', ['cancelled'])
            ->where('scheduled_for', $dt->toDateTimeString())
            ->count();

        return $used < $capacity ? $dt : null;
    }

    /** Human-readable summary of the lab's open days/hours, for prompts. */
    public static function hoursSummary(string $hospitalId): string
    {
        $avail = LabAvailability::where('hospital_id', $hospitalId)->first();
        $schedule = $avail && ! empty($avail->schedule) ? $avail->schedule : LabAvailability::defaultSchedule();
        $open = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            if (! empty($schedule[$day])) {
                $ranges = collect($schedule[$day])->map(fn ($b) => $b['start'] . '-' . $b['end'])->implode(', ');
                $open[] = ucfirst(substr($day, 0, 3)) . ' ' . $ranges;
            }
        }
        return implode(' · ', $open);
    }
}
