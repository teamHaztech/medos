<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give dentist/dietitian staff a working weekly schedule so they are bookable via
 * the chatbot/kiosk slot engine (offerSlots iterates staff.schedule). Idempotent —
 * only fills a null/empty schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('staff')) {
            return;
        }

        $schedule = json_encode([
            'monday'    => [['start' => '09:00', 'end' => '17:00']],
            'tuesday'   => [['start' => '09:00', 'end' => '17:00']],
            'wednesday' => [['start' => '09:00', 'end' => '17:00']],
            'thursday'  => [['start' => '09:00', 'end' => '17:00']],
            'friday'    => [['start' => '09:00', 'end' => '17:00']],
            'saturday'  => [['start' => '09:00', 'end' => '13:00']],
        ]);

        DB::table('staff')
            ->whereIn('role', ['dentist', 'dietitian'])
            ->where(function ($q) {
                $q->whereNull('schedule')->orWhere('schedule', '')->orWhere('schedule', '[]')->orWhere('schedule', '{}');
            })
            ->update([
                'schedule'                     => $schedule,
                'consultation_duration_default' => 20,
                'updated_at'                   => now(),
            ]);
    }

    public function down(): void
    {
        // Non-destructive: leave schedules in place.
    }
};
