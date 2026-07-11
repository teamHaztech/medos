<?php

namespace App\Modules\Dental\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DentalProcedure extends Model
{
    use HasUuid;

    protected $table = 'dental_procedures';

    protected $fillable = [
        'id', 'hospital_id', 'code', 'name', 'category', 'default_fee', 'is_active',
    ];

    protected $casts = [
        'default_fee' => 'decimal:2',
        'is_active'   => 'boolean',
    ];

    public const CATEGORIES = [
        'diagnostic'  => 'Diagnostic',
        'preventive'  => 'Preventive',
        'restorative' => 'Restorative',
        'endodontic'  => 'Endodontic',
        'prosthetic'  => 'Prosthetic',
        'surgical'    => 'Surgical / Oral Surgery',
        'cosmetic'    => 'Cosmetic',
        'pediatric'   => 'Pediatric',
        'general'     => 'General',
    ];
}
