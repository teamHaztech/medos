<?php

namespace App\Modules\Core\Events;

use App\Modules\Patient\Models\Encounter;

class EncounterCompleted extends MedOSEvent
{
    public Encounter $encounter;

    public function __construct(Encounter $encounter, ?string $hospitalId = null)
    {
        parent::__construct(
            eventType: 'encounter.completed',
            hospitalId: $hospitalId ?? $encounter->hospital_id,
            meta: [
                'encounter_id' => $encounter->id,
                'patient_id'   => $encounter->patient_id,
                'doctor_id'    => $encounter->doctor_id,
            ],
        );

        $this->encounter = $encounter;
    }
}
