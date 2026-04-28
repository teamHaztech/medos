<?php

namespace App\Modules\AIReceptionist\Events;

use App\Modules\Core\Events\MedOSEvent;

class EmergencyDetected extends MedOSEvent
{
    public string $patientId;
    public string $conversationId;
    public string $phone;
    public string $message;
    public array $detectionResult;

    public function __construct(
        string $patientId,
        string $conversationId,
        string $phone,
        string $message,
        array $detectionResult,
        ?string $hospitalId = null,
    ) {
        parent::__construct(
            eventType: 'emergency.detected',
            hospitalId: $hospitalId,
            meta: [
                'patient_id'       => $patientId,
                'conversation_id'  => $conversationId,
                'phone'            => $phone,
                'matched_keywords' => $detectionResult['matched_keywords'] ?? [],
                'confidence'       => $detectionResult['confidence'] ?? 0,
            ],
        );

        $this->patientId       = $patientId;
        $this->conversationId  = $conversationId;
        $this->phone           = $phone;
        $this->message         = $message;
        $this->detectionResult = $detectionResult;
    }
}
