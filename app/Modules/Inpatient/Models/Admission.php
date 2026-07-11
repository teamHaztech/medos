<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Models\Staff;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'admissions';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'admitting_doctor_id', 'attending_doctor_id',
        'ward_id', 'bed_id', 'room_daily_rate', 'nursing_daily_rate', 'bed_category',
        'admission_no', 'admitted_at', 'reason', 'provisional_diagnosis',
        'status', 'discharged_at', 'discharge_summary', 'discharge_type',
        'discharge_status', 'discharge_initiated_at',
        'billing_cleared_at', 'pharmacy_cleared_at', 'nursing_cleared_at',
    ];

    protected $casts = [
        'room_daily_rate'        => 'decimal:2',
        'nursing_daily_rate'     => 'decimal:2',
        'admitted_at'            => 'datetime',
        'discharged_at'          => 'datetime',
        'discharge_initiated_at' => 'datetime',
        'billing_cleared_at'     => 'datetime',
        'pharmacy_cleared_at'    => 'datetime',
        'nursing_cleared_at'     => 'datetime',
    ];

    /** Discharge clearance steps (front-office/ward workflow). */
    public const CLEARANCES = [
        'billing'  => 'Billing / Accounts',
        'pharmacy' => 'Pharmacy',
        'nursing'  => 'Nursing',
    ];

    public const DISCHARGE_TYPES = [
        'recovered' => 'Recovered / Cured',
        'referred'  => 'Referred out',
        'dama'      => 'Discharge Against Medical Advice',
        'lama'      => 'Left Against Medical Advice',
        'expired'   => 'Expired',
    ];

    // Relationships
    public function patient(): BelongsTo { return $this->belongsTo(Patient::class); }
    public function ward(): BelongsTo { return $this->belongsTo(Ward::class); }
    public function bed(): BelongsTo { return $this->belongsTo(Bed::class); }
    public function admittingDoctor(): BelongsTo { return $this->belongsTo(Staff::class, 'admitting_doctor_id'); }
    public function attendingDoctor(): BelongsTo { return $this->belongsTo(Staff::class, 'attending_doctor_id'); }

    public function vitals(): HasMany { return $this->hasMany(IpVital::class)->orderByDesc('recorded_at'); }
    public function intakeOutputs(): HasMany { return $this->hasMany(IpIntakeOutput::class)->orderByDesc('recorded_at'); }
    public function notes(): HasMany { return $this->hasMany(IpNote::class)->orderByDesc('created_at'); }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'admitted';
    }

    /** Length of stay in whole days (min 1). */
    public function lengthOfDays(): int
    {
        $end = $this->discharged_at ?? now();
        return max(1, (int) $this->admitted_at?->diffInDays($end) + ($this->discharged_at ? 0 : 1));
    }

    public function dischargeTypeLabel(): ?string
    {
        return $this->discharge_type ? (self::DISCHARGE_TYPES[$this->discharge_type] ?? ucfirst($this->discharge_type)) : null;
    }

    /** Net fluid balance (intake - output) in ml. */
    public function fluidBalance(): int
    {
        $in = (int) $this->intakeOutputs->where('direction', 'intake')->sum('volume_ml');
        $out = (int) $this->intakeOutputs->where('direction', 'output')->sum('volume_ml');
        return $in - $out;
    }

    // ---------------------------------------------------------------
    // Discharge workflow tracking
    // ---------------------------------------------------------------

    public function dischargeInitiated(): bool
    {
        return $this->discharge_status === 'initiated';
    }

    /** Whether a given clearance step (billing|pharmacy|nursing) is done. */
    public function isCleared(string $key): bool
    {
        return ! is_null($this->{$key . '_cleared_at'} ?? null);
    }

    public function clearedCount(): int
    {
        return collect(array_keys(self::CLEARANCES))->filter(fn ($k) => $this->isCleared($k))->count();
    }

    public function summaryReady(): bool
    {
        return ! empty($this->discharge_summary);
    }

    /** All discharge clearances completed (summary is captured at the final discharge step). */
    public function readyToDischarge(): bool
    {
        return $this->clearedCount() === count(self::CLEARANCES);
    }

    /** Current stage label for the ADT board. */
    public function dischargeStage(): string
    {
        if (! $this->isActive()) {
            return 'discharged';
        }
        if (! $this->dischargeInitiated()) {
            return 'in_care';
        }
        return $this->readyToDischarge() ? 'ready' : 'initiated';
    }

    public function dischargeStageLabel(): string
    {
        return [
            'in_care'    => 'In care',
            'initiated'  => 'Discharge in progress',
            'ready'      => 'Ready to leave',
            'discharged' => 'Discharged',
        ][$this->dischargeStage()];
    }
}
