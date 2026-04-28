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
        'hospital_id',
        'patient_id',
        'encounter_id',
        'escalated_to_staff_id',
        'channel',
        'state',
        'language',
        'messages',
        'ai_reasoning_trace',
        'external_chat_id',
        'started_at',
        'ended_at',
        'escalated_at',
    ];

    protected $casts = [
        'channel'            => Channel::class,
        'messages'           => 'array',
        'ai_reasoning_trace' => 'array',
        'started_at'         => 'datetime',
        'ended_at'           => 'datetime',
        'escalated_at'       => 'datetime',
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
        $this->state = $newState;
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
