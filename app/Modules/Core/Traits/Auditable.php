<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trait Auditable
 *
 * Logs changes to the audit_logs table on created, updated, and deleted events.
 * Captures old_values, new_values, and user information.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('created', [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $oldValues = [];
            $newValues = [];

            foreach ($model->getDirty() as $attribute => $newValue) {
                $oldValues[$attribute] = $model->getOriginal($attribute);
                $newValues[$attribute] = $newValue;
            }

            if (! empty($newValues)) {
                $model->logAudit('updated', $oldValues, $newValues);
            }
        });

        static::deleted(function (Model $model) {
            $model->logAudit('deleted', $model->getOriginal(), []);
        });
    }

    /**
     * Write an audit log entry for this model.
     */
    protected function logAudit(string $event, array $oldValues, array $newValues): void
    {
        $user = Auth::user();

        AuditLog::create([
            'hospital_id'  => $this->hospital_id ?? $this->getAttribute('hospital_id'),
            'entity_type'  => get_class($this),
            'entity_id'    => $this->getKey(),
            'action'       => $event,
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'user_id'      => $user?->id,
            'user_type'    => $user ? get_class($user) : null,
            'ip_address'   => request()?->ip(),
            'user_agent'   => request()?->userAgent(),
            'metadata'     => [],
            'created_at'   => now(),
        ]);
    }

    /**
     * Get all audit log entries for this model.
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'entity');
    }
}
