<?php

namespace App\Models;

use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceCallSetting extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'voice_call_settings';

    protected $fillable = [
        'id',
        'hospital_id',
        'is_enabled',
        'provider',
        'provider_config',
        'phone_numbers',
        'greeting_message',
        'default_language',
        'supported_languages',
        'voice_name',
        'max_concurrent_calls',
        'auto_callback_enabled',
        'auto_callback_delay_minutes',
        'business_hours',
        'after_hours_message',
        'emergency_transfer_number',
        'ai_model',
        'ai_temperature',
    ];

    protected $casts = [
        'is_enabled'                  => 'boolean',
        'provider_config'             => 'array',
        'phone_numbers'               => 'array',
        'supported_languages'         => 'array',
        'business_hours'              => 'array',
        'auto_callback_enabled'       => 'boolean',
        'max_concurrent_calls'        => 'integer',
        'auto_callback_delay_minutes' => 'integer',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get the hospital that owns this setting.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Get all voice calls for the hospital via this setting's hospital.
     */
    public function voiceCalls(): HasMany
    {
        return $this->hasMany(VoiceCall::class, 'hospital_id', 'hospital_id');
    }
}
