<?php

namespace App\Modules\Core\Listeners;

use App\Modules\Billing\Services\BillingService;
use App\Modules\Core\Events\EncounterCompleted;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Engagement\Jobs\SendFeedbackRequest;
use App\Modules\Engagement\Services\EngagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class EncounterCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue this listener should run on.
     */
    public string $queue = 'default';

    public function __construct(
        private BillingService $billingService,
        private EngagementService $engagementService,
        private HospitalContext $hospitalContext,
    ) {}

    public function handle(EncounterCompleted $event): void
    {
        $encounter = $event->encounter;

        try {
            $this->hospitalContext->setHospitalId($encounter->hospital_id);

            // 1. Generate bill for the encounter
            $this->generateBill($encounter);

            // 2. Dispatch feedback request (delayed by configured hours)
            $this->scheduleFeedbackRequest($encounter);

            // 3. Schedule follow-up reminder if follow_up_date is set
            $this->scheduleFollowUp($encounter);

            Log::info('[MedOS] Encounter completed processing finished', [
                'encounter_id' => $encounter->id,
                'patient_id'   => $encounter->patient_id,
                'doctor_id'    => $encounter->doctor_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Encounter completed processing failed', [
                'encounter_id' => $encounter->id,
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function generateBill($encounter): void
    {
        try {
            $bill = $this->billingService->generateBill($encounter);

            Log::info('[MedOS] Bill generated for encounter', [
                'encounter_id' => $encounter->id,
                'bill_id'      => $bill->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Bill generation failed', [
                'encounter_id' => $encounter->id,
                'error'        => $e->getMessage(),
            ]);
            // Don't re-throw; continue with other post-completion tasks
        }
    }

    private function scheduleFeedbackRequest($encounter): void
    {
        $delayHours = config('medos.notifications.feedback_delay_hours', 2);

        SendFeedbackRequest::dispatch(
            $encounter->patient_id,
            $encounter->id,
        )
            ->onQueue('notifications')
            ->delay(now()->addHours($delayHours));

        Log::info('[MedOS] Feedback request scheduled', [
            'encounter_id' => $encounter->id,
            'delay_hours'  => $delayHours,
        ]);
    }

    private function scheduleFollowUp($encounter): void
    {
        $followUpDate = $encounter->intake_data['follow_up_date'] ?? null;

        if (! $followUpDate) {
            return;
        }

        $this->engagementService->scheduleFollowUpReminder($encounter);
    }
}
