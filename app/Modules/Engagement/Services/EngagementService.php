<?php

namespace App\Modules\Engagement\Services;

use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Engagement\Jobs\SendAppointmentReminder;
use App\Modules\Engagement\Jobs\SendFeedbackRequest;
use App\Modules\Multilingual\Services\TranslationService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EngagementService extends BaseModuleService
{
    private WhatsAppService $whatsAppService;
    private TranslationService $translationService;

    public function __construct(
        HospitalContext $hospitalContext,
        WhatsAppService $whatsAppService,
        TranslationService $translationService,
    ) {
        parent::__construct($hospitalContext);
        $this->whatsAppService = $whatsAppService;
        $this->translationService = $translationService;
    }

    // ---------------------------------------------------------------
    // Follow-Up Reminders
    // ---------------------------------------------------------------

    /**
     * Schedule follow-up reminders based on follow_up_date in encounter intake data.
     */
    public function scheduleFollowUpReminder(Encounter $encounter): void
    {
        $encounter->loadMissing('patient');
        $patient = $encounter->patient;

        $followUpDate = $encounter->intake_data['follow_up_date'] ?? null;

        if (! $followUpDate) {
            $this->logInfo('No follow-up date set, skipping reminder', ['encounter_id' => $encounter->id]);
            return;
        }

        $followUp = Carbon::parse($followUpDate);
        $reminderDate = $followUp->copy()->subDay(); // 1 day before

        // Only schedule if reminder date is in the future
        if ($reminderDate->isPast()) {
            $this->logWarning('Follow-up reminder date is in the past, skipping', [
                'encounter_id' => $encounter->id,
                'follow_up_date' => $followUpDate,
            ]);
            return;
        }

        SendAppointmentReminder::dispatch(
            $patient->id,
            $encounter->id,
            'follow_up',
        )->delay($reminderDate);

        $this->logInfo('Follow-up reminder scheduled', [
            'patient_id'    => $patient->id,
            'encounter_id'  => $encounter->id,
            'reminder_date' => $reminderDate->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------
    // Feedback
    // ---------------------------------------------------------------

    /**
     * Send post-visit feedback request via WhatsApp (delayed by configured hours).
     */
    public function sendFeedbackRequest(Encounter $encounter): void
    {
        $encounter->loadMissing('patient');
        $patient = $encounter->patient;

        $delayHours = config('medos.notifications.feedback_delay_hours', 2);

        SendFeedbackRequest::dispatch(
            $patient->id,
            $encounter->id,
        )->delay(now()->addHours($delayHours));

        $this->logInfo('Feedback request scheduled', [
            'patient_id'   => $patient->id,
            'encounter_id' => $encounter->id,
            'delay_hours'  => $delayHours,
        ]);
    }

    /**
     * Process patient feedback: store rating and alert admin if low.
     */
    public function processFeedback(Patient $patient, int $rating, ?string $comment = null): void
    {
        // Store feedback in patient metadata
        $metadata = $patient->metadata ?? [];
        $feedback = $metadata['feedback'] ?? [];

        $feedback[] = [
            'rating'    => $rating,
            'comment'   => $comment,
            'date'      => now()->toIso8601String(),
        ];

        $metadata['feedback'] = $feedback;
        $patient->update(['metadata' => $metadata]);

        $this->logInfo('Feedback recorded', [
            'patient_id' => $patient->id,
            'rating'     => $rating,
        ]);

        // Alert admin if rating is low
        if ($rating < 3) {
            $this->logWarning('Low patient satisfaction rating', [
                'patient_id' => $patient->id,
                'rating'     => $rating,
                'comment'    => $comment,
            ]);

            // TODO: Dispatch notification to admin
        }

        // Send thank-you message
        $language = $patient->preferredLanguage();
        $message = $this->translationService->getLocalizedMessage('thank_you', $language);

        try {
            $this->whatsAppService->sendTextMessage($patient->phone, $message);
        } catch (\Throwable $e) {
            $this->logError('Failed to send feedback thank-you', ['error' => $e->getMessage()]);
        }
    }

    // ---------------------------------------------------------------
    // Re-Engagement
    // ---------------------------------------------------------------

    /**
     * Find chronic patients overdue for visits.
     *
     * Returns patients who have had encounters with chronic conditions
     * and have not visited in the configured overdue period.
     */
    public function checkReEngagement(): Collection
    {
        $hospitalId = $this->requireHospital();
        $overdueDays = 90; // 3 months default for chronic patients

        $overduePatients = Patient::where('hospital_id', $hospitalId)
            ->whereNotNull('medical_history')
            ->whereHas('encounters', function ($q) {
                $q->where('status', 'completed');
            })
            ->whereDoesntHave('encounters', function ($q) use ($overdueDays) {
                $q->where('created_at', '>=', now()->subDays($overdueDays));
            })
            ->get();

        $this->logInfo('Re-engagement check completed', [
            'overdue_count' => $overduePatients->count(),
        ]);

        return $overduePatients;
    }

    // ---------------------------------------------------------------
    // Medication Reminders
    // ---------------------------------------------------------------

    /**
     * Send medication reminder via WhatsApp.
     */
    public function sendMedicationReminder(Patient $patient, array $medications): void
    {
        $language = $patient->preferredLanguage();
        $medList = implode(', ', $medications);

        $message = $this->translationService->getLocalizedMessage(
            'medication_reminder',
            $language,
            ['medication' => $medList],
        );

        try {
            $this->whatsAppService->sendTextMessage($patient->phone, $message);

            $this->logInfo('Medication reminder sent', [
                'patient_id'  => $patient->id,
                'medications' => $medications,
            ]);
        } catch (\Throwable $e) {
            $this->logError('Failed to send medication reminder', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
