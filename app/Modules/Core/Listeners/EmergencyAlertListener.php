<?php

namespace App\Modules\Core\Listeners;

use App\Modules\AIReceptionist\Events\EmergencyDetected;
use App\Modules\Core\Models\Staff;
use App\Modules\WhatsApp\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmergencyAlertListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * High priority -- process on default queue immediately.
     */
    public string $queue = 'default';

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        private WhatsAppService $whatsAppService,
    ) {}

    public function handle(EmergencyDetected $event): void
    {
        try {
            Log::critical('[MedOS] EMERGENCY DETECTED', [
                'hospital_id'     => $event->hospitalId,
                'patient_id'      => $event->patientId,
                'conversation_id' => $event->conversationId,
                'phone'           => $event->phone,
                'message'         => $event->message,
                'detection'       => $event->detectionResult,
            ]);

            // 1. Send SMS/WhatsApp alert to on-duty ER staff
            $this->alertERStaff($event);

            // 2. Create a high-priority notification in the dashboard
            $this->createDashboardNotification($event);

        } catch (\Throwable $e) {
            Log::error('[MedOS] Emergency alert processing failed', [
                'patient_id' => $event->patientId,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function alertERStaff(EmergencyDetected $event): void
    {
        // Find on-duty ER staff for this hospital
        $erStaff = Staff::where('hospital_id', $event->hospitalId)
            ->where('department', 'emergency')
            ->where('is_active', true)
            ->get();

        $confidence = $event->detectionResult['confidence'] ?? 0;
        $keywords = implode(', ', $event->detectionResult['matched_keywords'] ?? []);

        $alertMessage = "EMERGENCY ALERT\n\n"
            . "Patient phone: {$event->phone}\n"
            . "Message: {$event->message}\n"
            . "Confidence: " . round($confidence * 100) . "%\n"
            . "Keywords: {$keywords}\n\n"
            . "Please respond immediately.";

        foreach ($erStaff as $staff) {
            if (! $staff->phone) {
                continue;
            }

            try {
                $this->whatsAppService->sendTextMessage($staff->phone, $alertMessage);

                Log::info('[MedOS] Emergency alert sent to staff', [
                    'staff_id' => $staff->id,
                    'phone'    => $staff->phone,
                ]);
            } catch (\Throwable $e) {
                Log::error('[MedOS] Failed to send emergency alert to staff', [
                    'staff_id' => $staff->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    private function createDashboardNotification(EmergencyDetected $event): void
    {
        try {
            DB::table('notifications')->insert([
                'id'              => \Illuminate\Support\Str::uuid()->toString(),
                'type'            => 'emergency_alert',
                'notifiable_type' => 'hospital',
                'notifiable_id'   => $event->hospitalId,
                'data'            => json_encode([
                    'title'           => 'Emergency Detected',
                    'message'         => "Emergency detected from patient phone {$event->phone}",
                    'patient_id'      => $event->patientId,
                    'conversation_id' => $event->conversationId,
                    'severity'        => 'critical',
                    'detection'       => $event->detectionResult,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            Log::info('[MedOS] Emergency dashboard notification created', [
                'hospital_id' => $event->hospitalId,
                'patient_id'  => $event->patientId,
            ]);
        } catch (\Throwable $e) {
            Log::error('[MedOS] Failed to create emergency dashboard notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
