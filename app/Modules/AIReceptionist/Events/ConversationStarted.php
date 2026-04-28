<?php

namespace App\Modules\AIReceptionist\Events;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Events\MedOSEvent;

class ConversationStarted extends MedOSEvent
{
    public string $patientId;
    public Channel $channel;
    public string $conversationId;

    public function __construct(
        string $patientId,
        Channel $channel,
        string $conversationId,
        ?string $hospitalId = null,
    ) {
        parent::__construct(
            eventType: 'conversation.started',
            hospitalId: $hospitalId,
            meta: [
                'patient_id'      => $patientId,
                'channel'         => $channel->value,
                'conversation_id' => $conversationId,
            ],
        );

        $this->patientId      = $patientId;
        $this->channel         = $channel;
        $this->conversationId  = $conversationId;
    }
}
