<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetWarranty extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'asset_warranties';

    protected $fillable = [
        'id', 'hospital_id', 'asset_id', 'warranty_type', 'start_date', 'end_date',
        'vendor_contact', 'terms', 'document_path', 'reminder_days_before_expiry',
        'is_active',
    ];

    protected $casts = [
        'start_date'                  => 'date',
        'end_date'                    => 'date',
        'reminder_days_before_expiry' => 'integer',
        'is_active'                   => 'boolean',
    ];

    public const TYPES = [
        'manufacturer' => 'Manufacturer',
        'amc'          => 'AMC',
        'cmc'          => 'CMC',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function typeLabel(): string
    {
        return self::TYPES[$this->warranty_type] ?? ucfirst((string) $this->warranty_type);
    }

    /** Whole days from today until expiry (negative if already expired). */
    public function daysToExpiry(): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false));
    }

    public function isExpired(): bool
    {
        $d = $this->daysToExpiry();

        return $d !== null && $d < 0;
    }

    public function isActiveNow(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    /** Expiring within the given window (and not yet expired). */
    public function isExpiringWithin(int $days): bool
    {
        $d = $this->daysToExpiry();

        return $this->is_active && $d !== null && $d >= 0 && $d <= $days;
    }

    // ---------------------------------------------------------------
    // Scopes
    // ---------------------------------------------------------------

    /** Active warranties expiring between today and today+$days (inclusive). */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays($days)->toDateString());
    }
}
