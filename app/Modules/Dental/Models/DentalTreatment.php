<?php

namespace App\Modules\Dental\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class DentalTreatment extends Model
{
    use HasUuid;

    protected $table = 'dental_treatments';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'procedure_id', 'tooth_number', 'surfaces',
        'procedure', 'status', 'performed_date', 'cost', 'bill_id', 'notes',
    ];

    protected $casts = [
        'cost'           => 'decimal:2',
        'performed_date' => 'date',
    ];

    public const STATUSES = ['planned' => 'Planned', 'in_progress' => 'In Progress', 'completed' => 'Completed'];

    public function catalogProcedure(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DentalProcedure::class, 'procedure_id');
    }
}
