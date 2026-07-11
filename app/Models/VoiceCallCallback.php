<?php

namespace App\Models;

use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCallCallback extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'voice_call_callbacks';

    protected $fillable = [
        'id',
        'hospital_id',
        'caller_number',
        'original_call_id',
        'status',
        'attempts',
        'max_attempts',
        'next_attempt_at',
        'completed_at',
    ];

    protected $casts = [
        'attempts'        => 'integer',
        'max_attempts'    => 'integer',
        'next_attempt_at' => 'datetime',
        'completed_at'    => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this callback.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the original voice call that triggered this callback.
     */
    public function originalCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class, 'original_call_id');
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    /**
     * Scope to pending callbacks ready for the next attempt.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('next_attempt_at', '<=', now());
    }
}
