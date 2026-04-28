<?php

namespace App\Modules\Queue\Events;

use App\Modules\Core\Events\MedOSEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class PatientCalled extends MedOSEvent implements ShouldBroadcast
{
    public string $patientId;
    public string $doctorId;
    public string $appointmentId;
    public string $queueEntryId;

    public function __construct(
        string $patientId,
        string $doctorId,
        string $appointmentId,
        string $queueEntryId,
        ?string $hospitalId = null,
    ) {
        parent::__construct('queue.patient_called', $hospitalId, [
            'patient_id'     => $patientId,
            'doctor_id'      => $doctorId,
            'appointment_id' => $appointmentId,
            'queue_entry_id' => $queueEntryId,
        ]);

        $this->patientId = $patientId;
        $this->doctorId = $doctorId;
        $this->appointmentId = $appointmentId;
        $this->queueEntryId = $queueEntryId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("queue.{$this->hospitalId}.{$this->doctorId}"),
            new Channel("patient.{$this->hospitalId}.{$this->patientId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'patient.called';
    }
}
