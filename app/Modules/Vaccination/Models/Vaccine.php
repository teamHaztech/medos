<?php

namespace App\Modules\Vaccination\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Vaccine extends Model
{
    use HasUuid;

    protected $table = 'vaccines';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'code', 'category',
        'total_doses', 'dose_interval_days', 'age_schedule', 'route', 'is_active',
    ];

    protected $casts = [
        'total_doses'        => 'integer',
        'dose_interval_days' => 'integer',
        'age_schedule'       => 'array',
        'is_active'          => 'boolean',
    ];

    public const CATEGORIES = [
        'routine' => 'Routine',
        'covid'   => 'COVID-19',
        'travel'  => 'Travel',
        'other'   => 'Other',
    ];

    public const ROUTES = [
        'oral' => 'Oral',
        'im'   => 'Intramuscular (IM)',
        'sc'   => 'Subcutaneous (SC)',
        'id'   => 'Intradermal (ID)',
    ];

    /** True when this vaccine follows an age-based national schedule (vs on-demand). */
    public function isScheduled(): bool
    {
        return ! empty($this->age_schedule);
    }
}
