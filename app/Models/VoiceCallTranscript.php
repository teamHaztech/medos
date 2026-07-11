<?php

namespace App\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceCallTranscript extends Model
{
    use HasUuid;

    /**
     * This table has no updated_at column.
     */
    const UPDATED_AT = null;

    protected $table = 'voice_call_transcripts';

    protected $fillable = [
        'id',
        'voice_call_id',
        'speaker',
        'content',
        'language',
        'confidence',
        'timestamp_ms',
    ];

    protected $casts = [
        'confidence'   => 'decimal:2',
        'timestamp_ms' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the voice call this transcript belongs to.
     */
    public function voiceCall(): BelongsTo
    {
        return $this->belongsTo(VoiceCall::class);
    }
}
