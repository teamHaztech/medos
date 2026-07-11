<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDeposit extends Model
{
    use HasUuid;

    protected $table = 'patient_deposits';

    protected $fillable = [
        'id', 'hospital_id', 'patient_id', 'amount', 'type', 'method', 'reference', 'received_by_name', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPES = [
        'collect' => 'Deposit collected',
        'adjust'  => 'Adjusted to bill',
        'refund'  => 'Refunded',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Current available deposit balance for a patient (signed sum). */
    public static function balanceFor(string $hospitalId, string $patientId): float
    {
        return round((float) static::where('hospital_id', $hospitalId)->where('patient_id', $patientId)->sum('amount'), 2);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
