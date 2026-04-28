<?php

namespace App\Modules\Engagement\Jobs;

use App\Modules\Core\Models\Hospital;
use App\Modules\Multilingual\Services\TranslationService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendFeedbackRequest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $patientId,
        public string $encounterId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WhatsAppService $whatsAppService,
        TranslationService $translationService,
    ): void {
        $patient = Patient::find($this->patientId);

        if (! $patient) {
            Log::warning('[MedOS] SendFeedbackRequest: Patient not found', [
                'patient_id' => $this->patientId,
            ]);
            return;
        }

        $encounter = Encounter::find($this->encounterId);

        if (! $encounter) {
            Log::warning('[MedOS] SendFeedbackRequest: Encounter not found', [
                'encounter_id' => $this->encounterId,
            ]);
            return;
        }

        // Only send feedback for completed encounters
        if ($encounter->status->value !== 'completed') {
            Log::info('[MedOS] SendFeedbackRequest: Encounter not completed, skipping', [
                'encounter_id' => $this->encounterId,
                'status'       => $encounter->status->value,
            ]);
            return;
        }

        $language = $patient->preferredLanguage();
        $hospitalName = Hospital::find($patient->hospital_id)?->name ?? 'our hospital';

        $message = $translationService->getLocalizedMessage(
            'feedback_request',
            $language,
            ['hospital' => $hospitalName],
        );

        try {
            $whatsAppService->sendTextMessage($patient->phone, $message);

            Log::info('[MedOS] Feedback request sent', [
                'patient_id'   => $patient->id,
                'encounter_id' => $this->encounterId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Failed to send feedback request', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
