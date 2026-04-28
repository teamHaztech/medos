<?php

namespace App\Modules\Core\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class MedOSEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The hospital this event belongs to.
     */
    public ?string $hospitalId;

    /**
     * Unix timestamp when the event was created.
     */
    public float $timestamp;

    /**
     * A short identifier for the event type (e.g. "patient.registered").
     */
    public string $eventType;

    /**
     * Optional metadata / payload.
     */
    public array $meta;

    public function __construct(
        string $eventType,
        ?string $hospitalId = null,
        array $meta = [],
    ) {
        $this->eventType = $eventType;
        $this->hospitalId = $hospitalId ?? config('medos.current_hospital_id');
        $this->timestamp = microtime(true);
        $this->meta = $meta;
    }

    /**
     * Get a loggable array representation of the event.
     */
    public function toLog(): array
    {
        return [
            'event_type' => $this->eventType,
            'hospital_id' => $this->hospitalId,
            'timestamp' => $this->timestamp,
            'meta' => $this->meta,
        ];
    }
}
