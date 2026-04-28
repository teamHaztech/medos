<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Events\MedOSEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

abstract class BaseModuleService
{
    protected HospitalContext $hospitalContext;

    public function __construct(HospitalContext $hospitalContext)
    {
        $this->hospitalContext = $hospitalContext;
    }

    // -------------------------------------------------------
    // Hospital Context Helpers
    // -------------------------------------------------------

    /**
     * Get the current hospital ID.
     */
    protected function hospitalId(): ?string
    {
        return $this->hospitalContext->getHospitalId();
    }

    /**
     * Require a hospital context or throw.
     */
    protected function requireHospital(): string
    {
        $id = $this->hospitalId();

        if ($id === null) {
            throw new \RuntimeException('No hospital context has been set.');
        }

        return $id;
    }

    // -------------------------------------------------------
    // Query Scoping
    // -------------------------------------------------------

    /**
     * Scope an Eloquent query to the current hospital.
     */
    protected function scopeToHospital(Builder $query, string $column = 'hospital_id'): Builder
    {
        return $query->where($column, $this->requireHospital());
    }

    // -------------------------------------------------------
    // Logging
    // -------------------------------------------------------

    /**
     * Log a message with automatic hospital context.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'hospital_id' => $this->hospitalId(),
            'module' => static::class,
        ], $context);

        Log::log($level, "[MedOS] {$message}", $context);
    }

    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    // -------------------------------------------------------
    // Event Dispatching
    // -------------------------------------------------------

    /**
     * Dispatch a MedOS event, automatically injecting the hospital ID.
     */
    protected function dispatchEvent(MedOSEvent $event): void
    {
        if ($event->hospitalId === null) {
            $event->hospitalId = $this->hospitalId();
        }

        event($event);
    }
}
