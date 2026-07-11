<?php

namespace App\Console\Commands;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Engagement\Jobs\SendAppointmentReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendReminders extends Command
{
    protected $signature = 'medos:send-reminders';

    protected $description = 'Send appointment reminders for upcoming appointments (24h and 2h windows)';

    public function handle(): int
    {
        try {
            $dispatched = 0;

            // Patient Engagement module gate — skip hospitals that have it disabled.
            $moduleMap = \App\Modules\Core\Models\Hospital::all(['id', 'modules_enabled'])->keyBy('id');
            $engagementOff = function ($hid) use ($moduleMap) {
                $h = $moduleMap[$hid] ?? null;
                return $h && ! empty($h->modules_enabled) && ! in_array('engagement', $h->modules_enabled, true);
            };

            // Find appointments in the next 24 hours that haven't been reminded (24h reminder)
            $twentyFourHourAppointments = Appointment::where('status', 'confirmed')
                ->whereBetween('slot_start', [now(), now()->addHours(24)])
                ->where(function ($query) {
                    $query->whereNull('notes')
                        ->orWhere('notes', 'not like', '%reminder_24h_sent%');
                })
                ->with('encounter')
                ->get();

            foreach ($twentyFourHourAppointments as $appointment) {
                if (! $appointment->patient_id || ! $appointment->encounter_id) {
                    continue;
                }
                if ($engagementOff($appointment->hospital_id)) {
                    continue;
                }

                SendAppointmentReminder::dispatch(
                    $appointment->patient_id,
                    $appointment->encounter_id,
                    'appointment',
                )->onQueue('notifications');

                // Mark as reminded
                $appointment->update([
                    'notes' => trim(($appointment->notes ?? '') . ' reminder_24h_sent'),
                ]);

                $dispatched++;
            }

            // Find appointments in the next 2 hours that haven't been reminded (2h reminder)
            $twoHourAppointments = Appointment::where('status', 'confirmed')
                ->whereBetween('slot_start', [now(), now()->addHours(2)])
                ->where(function ($query) {
                    $query->whereNull('notes')
                        ->orWhere('notes', 'not like', '%reminder_2h_sent%');
                })
                ->with('encounter')
                ->get();

            foreach ($twoHourAppointments as $appointment) {
                if (! $appointment->patient_id || ! $appointment->encounter_id) {
                    continue;
                }
                if ($engagementOff($appointment->hospital_id)) {
                    continue;
                }

                SendAppointmentReminder::dispatch(
                    $appointment->patient_id,
                    $appointment->encounter_id,
                    'appointment',
                )->onQueue('notifications');

                // Mark as reminded
                $appointment->update([
                    'notes' => trim(($appointment->notes ?? '') . ' reminder_2h_sent'),
                ]);

                $dispatched++;
            }

            $this->info("Dispatched {$dispatched} reminder(s)");

            Log::info('[MedOS] Appointment reminders dispatched', ['count' => $dispatched]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to send reminders: {$e->getMessage()}");
            Log::error('[MedOS] Send reminders command failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
