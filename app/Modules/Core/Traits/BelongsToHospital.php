<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\Hospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait BelongsToHospital
 *
 * Adds a hospital() relationship, auto-scopes queries to the current hospital
 * via a global scope, and auto-sets hospital_id on model creation.
 */
trait BelongsToHospital
{
    protected static function bootBelongsToHospital(): void
    {
        static::addGlobalScope('hospital', function (Builder $builder) {
            $hospitalId = static::resolveCurrentHospitalId();

            if ($hospitalId) {
                $builder->where($builder->getModel()->getTable() . '.hospital_id', $hospitalId);
            }
        });

        static::creating(function (Model $model) {
            if (empty($model->hospital_id)) {
                $hospitalId = static::resolveCurrentHospitalId();

                if ($hospitalId) {
                    $model->hospital_id = $hospitalId;
                }
            }
        });
    }

    /**
     * Resolve the current hospital ID from config or the application container.
     */
    protected static function resolveCurrentHospitalId(): ?string
    {
        // First try the config value
        $hospitalId = config('medos.current_hospital_id');

        if ($hospitalId) {
            return $hospitalId;
        }

        // Fall back to a singleton binding if available
        if (app()->bound('medos.current_hospital_id')) {
            return app('medos.current_hospital_id');
        }

        return null;
    }

    /**
     * Get the hospital that owns this model.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    /**
     * Scope a query to ignore the hospital global scope.
     */
    public function scopeWithoutHospitalScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('hospital');
    }

    /**
     * Scope a query to a specific hospital.
     */
    public function scopeForHospital(Builder $query, string $hospitalId): Builder
    {
        return $query->withoutGlobalScope('hospital')
            ->where($this->getTable() . '.hospital_id', $hospitalId);
    }
}
