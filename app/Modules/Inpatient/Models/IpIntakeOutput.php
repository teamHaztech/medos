<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpIntakeOutput extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'ip_intake_output';

    protected $fillable = [
        'id', 'hospital_id', 'admission_id', 'recorded_at', 'direction',
        'category', 'volume_ml', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'volume_ml'   => 'integer',
    ];

    public const INTAKE_CATEGORIES = ['Oral', 'IV Fluid', 'Tube Feed', 'Blood/Products', 'Other'];
    public const OUTPUT_CATEGORIES = ['Urine', 'Drain', 'Vomit', 'Stool', 'Blood Loss', 'Other'];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }
}
