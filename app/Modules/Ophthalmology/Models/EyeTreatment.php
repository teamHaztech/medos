<?php

namespace App\Modules\Ophthalmology\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EyeTreatment extends Model
{
    use HasUuid;

    protected $table = 'eye_treatments';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'procedure_id', 'eye',
        'procedure', 'status', 'performed_date', 'cost', 'bill_id', 'notes',
    ];

    protected $casts = [
        'cost'           => 'decimal:2',
        'performed_date' => 'date',
    ];

    public const STATUSES = ['planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Completed'];

    /** od = right eye, os = left eye, ou = both eyes. */
    public const EYES = ['od' => 'Right (OD)', 'os' => 'Left (OS)', 'ou' => 'Both (OU)'];

    public function catalogProcedure(): BelongsTo
    {
        return $this->belongsTo(EyeProcedure::class, 'procedure_id');
    }
}
