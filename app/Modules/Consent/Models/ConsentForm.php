<?php

namespace App\Modules\Consent\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ConsentForm extends Model
{
    use HasUuid;

    protected $table = 'consent_forms';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'category', 'content', 'requires_witness', 'is_active',
    ];

    protected $casts = [
        'requires_witness' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public const CATEGORIES = [
        'general'    => 'General',
        'surgical'   => 'Surgical',
        'anesthesia' => 'Anesthesia',
        'procedure'  => 'Procedure',
        'admission'  => 'Admission',
        'blood'      => 'Blood / Transfusion',
        'research'   => 'Research',
    ];
}
