<?php

namespace App\Modules\AIReceptionist\Models;

use App\Modules\Core\Enums\Channel;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'conversations';

    protected $fillable = [
        'id',
        'hospital_id',
        'patient_id',
        'encounter_id',
        'escalated_to_staff_id',
        'channel',
        'status',
        'current_state',
        'language_detected',
        'messages',
        'ai_reasoning_trace',
        'intent_detected',
        'escalation_reason',
        'handled_by_ai',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'channel'            => Channel::class,
        'messages'           => 'array',
        'ai_reasoning_trace' => 'array',
        'handled_by_ai'      => 'boolean',
        'started_at'         => 'datetime',
        'ended_at'           => 'datetime',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital for this conversation.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get the patient for this conversation.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the encounter spawned from this conversation.
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * Get the staff member this conversation was escalated to.
     */
    public function escalatedToStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'escalated_to_staff_id');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Append a message to the conversation.
     */
    public function addMessage(string $role, string $content, string $language, array $metadata = []): self
    {
        $messages = $this->messages ?? [];

        $messages[] = [
            'role'      => $role,
            'content'   => $content,
            'language'  => $language,
            'metadata'  => $metadata,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->messages = $messages;
        $this->save();

        return $this;
    }

    /**
     * Transition the conversation to a new state.
     */
    public function transition(string $newState): self
    {
        $this->current_state = $newState;
        $this->save();

        return $this;
    }

    /**
     * Determine if the conversation is still active.
     */
    public function isActive(): bool
    {
        return is_null($this->ended_at);
    }
}
