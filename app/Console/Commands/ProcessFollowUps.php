<?php

namespace App\Console\Commands;

use App\Modules\Engagement\Jobs\SendAppointmentReminder;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessFollowUps extends Command
{
    protected $signature = 'medos:process-follow-ups';

    protected $description = 'Process follow-up reminders for encounters with follow-up dates today';

    public function handle(): int
    {
        try {
            $processed = 0;

            // Find encounters with follow_up_date = today that haven't been reminded
            $encounters = Encounter::where('status', 'completed')
                ->whereNotNull('intake_data')
                ->get()
                ->filter(function ($encounter) {
                    $followUpDate = $encounter->intake_data['follow_up_date'] ?? null;
                    $followUpReminded = $encounter->intake_data['follow_up_reminded'] ?? false;

                    return $followUpDate
                        && $followUpDate === today()->toDateString()
                        && ! $followUpReminded;
                });

            foreach ($encounters as $encounter) {
                if (! $encounter->patient_id) {
                    continue;
                }

                SendAppointmentReminder::dispatch(
                    $encounter->patient_id,
                    $encounter->id,
                    'follow_up',
                )->onQueue('whatsapp');

                // Mark as reminded
                $intakeData = $encounter->intake_data ?? [];
                $intakeData['follow_up_reminded'] = true;
                $encounter->update(['intake_data' => $intakeData]);

                $processed++;
            }

            $this->info("Processed {$processed} follow-up reminders");

            Log::info('[MedOS] Follow-up reminders processed', ['count' => $processed]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to process follow-ups: {$e->getMessage()}");
            Log::error('[MedOS] Process follow-ups command failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
