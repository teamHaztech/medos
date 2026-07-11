<?php

namespace App\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCall extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'voice_calls';

    protected $fillable = [
        'id',
        'hospital_id',
        'call_sid',
        'direction',
        'caller_number',
        'called_number',
        'patient_id',
        'status',
        'ai_handled',
        'transferred_to',
        'transfer_reason',
        'language_detected',
        'language_used',
        'call_purpose',
        'sentiment',
        'ai_confidence',
        'appointment_id',
        'duration_seconds',
        'ring_duration_seconds',
        'started_at',
        'answered_at',
        'ended_at',
        'recording_url',
        'cost_amount',
        'cost_currency',
        'metadata',
    ];

    protected $casts = [
        'ai_handled'           => 'boolean',
        'ai_confidence'        => 'decimal:2',
        'duration_seconds'     => 'integer',
        'ring_duration_seconds' => 'integer',
        'cost_amount'          => 'decimal:4',
        'started_at'           => 'datetime',
        'answered_at'          => 'datetime',
        'ended_at'             => 'datetime',
        'metadata'             => 'array',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this call.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the patient associated with this call.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the transcripts for this call.
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(VoiceCallTranscript::class);
    }

    /**
     * Get the appointment linked to this call.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    /**
     * Scope to active (in-progress) calls.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope to calls created today.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    /**
     * Scope to calls for a specific hospital.
     */
    public function scopeByHospital(Builder $query, string $hospitalId): Builder
    {
        return $query->withoutGlobalScope('hospital')
            ->where('hospital_id', $hospitalId);
    }
}
