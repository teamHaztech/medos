<?php

namespace App\Modules\Core\Listeners;

use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Core\Models\Staff;
use App\Modules\Patient\Models\Patient;
use App\Modules\Queue\Events\PatientCalled;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientCalledNotifier implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'whatsapp';

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        private WhatsAppService $whatsAppService,
    ) {}

    public function handle(PatientCalled $event): void
    {
        try {
            $patient = Patient::find($event->patientId);

            if (! $patient || ! $patient->phone) {
                Log::warning('[MedOS] Patient called but no phone number found', [
                    'patient_id' => $event->patientId,
                ]);

                return;
            }

            $doctor = Staff::find($event->doctorId);
            $doctorName = $doctor?->full_name ?? 'Your doctor';

            // Try to get room info from the queue entry or doctor's settings
            $queueEntry = QueueEntry::find($event->queueEntryId);
            $room = $queueEntry?->notes ?? $doctor?->room ?? 'the consultation area';

            $message = "Dr. {$doctorName} is ready for you. Please proceed to Room {$room}.";

            $this->whatsAppService->sendTextMessage($patient->phone, $message);

            // Update notification log
            $this->logNotification($event, $patient, $message);

            Log::info('[MedOS] Patient called notification sent', [
                'patient_id' => $event->patientId,
                'doctor_id'  => $event->doctorId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Failed to send patient called notification', [
                'patient_id' => $event->patientId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function logNotification(PatientCalled $event, Patient $patient, string $message): void
    {
        try {
            DB::table('notification_logs')->insert([
                'id'          => \Illuminate\Support\Str::uuid()->toString(),
                'hospital_id' => $event->hospitalId,
                'patient_id'  => $event->patientId,
                'type'        => 'patient_called',
                'channel'     => 'whatsapp',
                'recipient'   => $patient->phone,
                'message'     => $message,
                'status'      => 'sent',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the main notification if logging fails
            Log::warning('[MedOS] Failed to log notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
