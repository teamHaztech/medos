<?php

namespace App\Modules\Engagement\Jobs;

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

class SendAppointmentReminder implements ShouldQueue
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
        public string $reminderType = 'appointment', // 'appointment' or 'follow_up'
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
            Log::warning('[MedOS] SendAppointmentReminder: Patient not found', [
                'patient_id' => $this->patientId,
            ]);
            return;
        }

        $language = $patient->preferredLanguage();

        if ($this->reminderType === 'follow_up') {
            $this->sendFollowUpReminder($patient, $language, $whatsAppService, $translationService);
        } else {
            $this->sendAppointmentReminder($patient, $language, $whatsAppService, $translationService);
        }
    }

    /**
     * Send an appointment reminder.
     */
    private function sendAppointmentReminder(
        Patient $patient,
        string $language,
        WhatsAppService $whatsAppService,
        TranslationService $translationService,
    ): void {
        $encounter = Encounter::with(['doctor', 'appointment'])->find($this->encounterId);

        if (! $encounter || ! $encounter->appointment) {
            Log::warning('[MedOS] SendAppointmentReminder: Encounter or appointment not found', [
                'encounter_id' => $this->encounterId,
            ]);
            return;
        }

        $appointment = $encounter->appointment;
        $doctorName = $encounter->doctor?->full_name ?? 'your doctor';
        $time = $appointment->slot_start?->format('h:i A') ?? '';

        $message = $translationService->getLocalizedMessage(
            'appointment_reminder',
            $language,
            [
                'doctor' => $doctorName,
                'time'   => $time,
            ],
        );

        try {
            $whatsAppService->sendTextMessage($patient->phone, $message);

            Log::info('[MedOS] Appointment reminder sent', [
                'patient_id'    => $patient->id,
                'appointment_id' => $appointment->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Failed to send appointment reminder', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Send a follow-up reminder.
     */
    private function sendFollowUpReminder(
        Patient $patient,
        string $language,
        WhatsAppService $whatsAppService,
        TranslationService $translationService,
    ): void {
        $encounter = Encounter::find($this->encounterId);

        if (! $encounter) {
            return;
        }

        $followUpDate = $encounter->intake_data['follow_up_date'] ?? null;

        $message = $translationService->getLocalizedMessage(
            'follow_up_reminder',
            $language,
            [
                'name' => $patient->name,
                'date' => $followUpDate ?? 'soon',
            ],
        );

        try {
            $whatsAppService->sendTextMessage($patient->phone, $message);

            Log::info('[MedOS] Follow-up reminder sent', [
                'patient_id'   => $patient->id,
                'encounter_id' => $this->encounterId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Failed to send follow-up reminder', [
                'patient_id' => $patient->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
