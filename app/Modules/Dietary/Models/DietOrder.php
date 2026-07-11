<?php

namespace App\Modules\Dietary\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietOrder extends Model
{
    use HasUuid;

    protected $table = 'diet_orders';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'diet_id', 'admission_id', 'ward',
        'texture', 'route', 'fluid_restriction_ml', 'kcal_target', 'protein_target_g',
        'restrictions', 'special_instructions', 'start_date', 'end_date', 'status', 'ordered_by_name',
    ];

    protected $casts = [
        'fluid_restriction_ml' => 'integer',
        'kcal_target'          => 'integer',
        'protein_target_g'     => 'integer',
        'start_date'           => 'date',
        'end_date'             => 'date',
    ];

    public const ROUTES = ['oral' => 'Oral', 'ng_tube' => 'NG Tube', 'peg' => 'PEG / Gastrostomy', 'npo' => 'NPO (nil by mouth)'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function diet(): BelongsTo
    {
        return $this->belongsTo(TherapeuticDiet::class, 'diet_id');
    }
}
