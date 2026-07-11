<?php

namespace App\Modules\Pathway\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPathway extends Model
{
    use HasUuid;

    protected $table = 'patient_pathways';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'template_id', 'status',
        'completed_steps', 'notes', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'completed_steps' => 'array',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public const STATUSES = ['active' => 'Active', 'completed' => 'Completed', 'discontinued' => 'Discontinued'];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PathwayTemplate::class, 'template_id');
    }

    public function totalSteps(): int
    {
        return count($this->template?->steps ?? []);
    }

    public function doneCount(): int
    {
        return count($this->completed_steps ?? []);
    }

    public function progressPercent(): int
    {
        $total = $this->totalSteps();

        return $total ? (int) round($this->doneCount() / $total * 100) : 0;
    }
}
