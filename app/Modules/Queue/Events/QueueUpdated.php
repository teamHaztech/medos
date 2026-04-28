<?php

namespace App\Modules\Queue\Events;

use App\Modules\Core\Events\MedOSEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class QueueUpdated extends MedOSEvent implements ShouldBroadcast
{
    public string $doctorId;
    public array $queueSnapshot;
    public array $changes;

    public function __construct(
        string $doctorId,
        array $queueSnapshot,
        array $changes,
        ?string $hospitalId = null,
    ) {
        parent::__construct('queue.updated', $hospitalId, [
            'doctor_id'      => $doctorId,
            'queue_snapshot'  => $queueSnapshot,
            'changes'         => $changes,
        ]);

        $this->doctorId = $doctorId;
        $this->queueSnapshot = $queueSnapshot;
        $this->changes = $changes;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("queue.{$this->hospitalId}.{$this->doctorId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }
}
