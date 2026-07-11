<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpVital extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'ip_vitals';

    protected $fillable = [
        'id', 'hospital_id', 'admission_id', 'recorded_by', 'recorded_at',
        'bp_systolic', 'bp_diastolic', 'pulse', 'spo2', 'resp_rate',
        'temperature', 'weight', 'height', 'bmi', 'notes',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'temperature' => 'decimal:1',
        'weight'      => 'decimal:1',
        'height'      => 'decimal:1',
        'bmi'         => 'decimal:1',
    ];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Which readings fall outside the normal reference range — used to flag rows.
     * @return array<string> keys of out-of-range fields
     */
    public function abnormalFlags(): array
    {
        $flags = [];
        if ($this->bp_systolic && ($this->bp_systolic < 90 || $this->bp_systolic > 140)) $flags[] = 'bp';
        if ($this->bp_diastolic && ($this->bp_diastolic < 60 || $this->bp_diastolic > 90)) $flags[] = 'bp';
        if ($this->pulse && ($this->pulse < 60 || $this->pulse > 100)) $flags[] = 'pulse';
        if ($this->spo2 && $this->spo2 < 94) $flags[] = 'spo2';
        if ($this->resp_rate && ($this->resp_rate < 12 || $this->resp_rate > 20)) $flags[] = 'resp_rate';
        if ($this->temperature && ((float) $this->temperature < 97 || (float) $this->temperature > 99.5)) $flags[] = 'temperature';
        return array_values(array_unique($flags));
    }

    public function isAbnormal(): bool
    {
        return count($this->abnormalFlags()) > 0;
    }
}
