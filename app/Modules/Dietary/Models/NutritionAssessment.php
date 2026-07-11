<?php

namespace App\Modules\Dietary\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionAssessment extends Model
{
    use HasUuid;

    protected $table = 'nutrition_assessments';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'tool', 'score', 'risk',
        'weight_kg', 'height_cm', 'bmi', 'diagnosis', 'plan', 'follow_up_date', 'assessed_by_name',
    ];

    protected $casts = [
        'score'          => 'integer',
        'weight_kg'      => 'decimal:2',
        'height_cm'      => 'decimal:2',
        'bmi'            => 'decimal:2',
        'follow_up_date' => 'date',
    ];

    public const TOOLS = ['MUST' => 'MUST', 'NRS-2002' => 'NRS-2002', 'SGA' => 'SGA'];

    public const RISKS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
