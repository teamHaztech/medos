<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class LabAvailability extends Model
{
    use HasUuid;

    protected $table = 'lab_availabilities';

    protected $fillable = [
        'id',
        'hospital_id',
        'schedule',
        'slot_duration',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'schedule'      => 'array',
        'slot_duration' => 'integer',
        'capacity'      => 'integer',
        'is_active'     => 'boolean',
    ];

    /** A sensible default weekly schedule used until the lab configures its own. */
    public static function defaultSchedule(): array
    {
        $weekday = [['start' => '08:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']];

        return [
            'monday'    => $weekday,
            'tuesday'   => $weekday,
            'wednesday' => $weekday,
            'thursday'  => $weekday,
            'friday'    => $weekday,
            'saturday'  => [['start' => '09:00', 'end' => '13:00']],
        ];
    }
}
