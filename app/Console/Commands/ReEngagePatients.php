<?php

namespace App\Console\Commands;

use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Engagement\Jobs\SendAppointmentReminder;
use App\Modules\Engagement\Services\EngagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReEngagePatients extends Command
{
    protected $signature = 'medos:re-engage';

    protected $description = 'Find and re-engage patients who are overdue for chronic care visits';

    public function handle(
        EngagementService $engagementService,
        HospitalContext $hospitalContext,
    ): int {
        try {
            $totalPatients = 0;
            $hospitalCount = 0;

            $hospitals = Hospital::where('is_active', true)->get();

            foreach ($hospitals as $hospital) {
                try {
                    $hospitalContext->setHospitalId($hospital->id);

                    $overduePatients = $engagementService->checkReEngagement();

                    if ($overduePatients->isEmpty()) {
                        continue;
                    }

                    $hospitalCount++;

                    foreach ($overduePatients as $patient) {
                        // Get the patient's most recent completed encounter for context
                        $lastEncounter = $patient->encounters()
                            ->where('status', 'completed')
                            ->latest()
                            ->first();

                        if ($lastEncounter) {
                            SendAppointmentReminder::dispatch(
                                $patient->id,
                                $lastEncounter->id,
                                'follow_up',
                            )->onQueue('notifications');

                            $totalPatients++;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('[MedOS] Re-engagement failed for hospital', [
                        'hospital_id' => $hospital->id,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            $this->info("Found {$totalPatients} patients for re-engagement across {$hospitalCount} hospitals");

            Log::info('[MedOS] Re-engagement completed', [
                'patients'  => $totalPatients,
                'hospitals' => $hospitalCount,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to process re-engagement: {$e->getMessage()}");
            Log::error('[MedOS] Re-engage command failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }
}
