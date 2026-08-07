<?php

namespace App\Modules\Ophthalmology\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class EyeProcedure extends Model
{
    use HasUuid;

    protected $table = 'eye_procedures';

    protected $fillable = [
        'id', 'hospital_id', 'code', 'name', 'category', 'default_fee', 'is_active',
    ];

    protected $casts = [
        'default_fee' => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    public const CATEGORIES = [
        'consultation' => 'Consultation',
        'diagnostic'   => 'Diagnostic / Imaging',
        'refraction'   => 'Refraction / Optical',
        'laser'        => 'Laser',
        'injection'    => 'Injection',
        'surgical'     => 'Surgical',
        'optical'      => 'Optical / Dispensing',
        'general'      => 'General',
    ];
}
