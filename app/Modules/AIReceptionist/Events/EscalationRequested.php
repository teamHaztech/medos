<?php

namespace App\Modules\AIReceptionist\Events;

use App\Modules\Core\Events\MedOSEvent;

class EscalationRequested extends MedOSEvent
{
    public string $conversationId;
    public string $patientId;
    public string $reason;

    public function __construct(
        string $conversationId,
        string $patientId,
        string $reason,
        ?string $hospitalId = null,
    ) {
        parent::__construct(
            eventType: 'conversation.escalation_requested',
            hospitalId: $hospitalId,
            meta: [
                'conversation_id' => $conversationId,
                'patient_id'      => $patientId,
                'reason'          => $reason,
            ],
        );

        $this->conversationId = $conversationId;
        $this->patientId      = $patientId;
        $this->reason         = $reason;
    }
}
