<?php

namespace App\Modules\Core\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Core\Traits\HasUuid;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hospital extends Model
{
    use HasUuid;

    protected $table = 'hospitals';

    protected $fillable = [
        'name',
        'slug',
        'address',
        'city',
        'state',
        'country',
        'phone',
        'email',
        'logo_url',
        'config',
        'modules_enabled',
        'timezone',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'config'          => 'array',
        'modules_enabled' => 'array',
        'is_active'       => 'boolean',
    ];

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    /**
     * Get all staff members belonging to this hospital.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class)->withoutGlobalScope('hospital');
    }

    /**
     * Get all patients belonging to this hospital.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class)->withoutGlobalScope('hospital');
    }

    /**
     * Get all encounters belonging to this hospital.
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class)->withoutGlobalScope('hospital');
    }

    /**
     * Get all appointments belonging to this hospital.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->withoutGlobalScope('hospital');
    }

    // ---------------------------------------------------------------
    // Methods
    // ---------------------------------------------------------------

    /**
     * Check if a given module is enabled for this hospital.
     */
    public function isModuleEnabled(string $module): bool
    {
        $modules = $this->modules_enabled ?? [];

        return in_array($module, $modules, true);
    }

    /**
     * Get a configuration setting by dot-notation key.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
