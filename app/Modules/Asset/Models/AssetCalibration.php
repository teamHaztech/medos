<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCalibration extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'asset_calibrations';

    protected $fillable = [
        'id', 'hospital_id', 'asset_id', 'calibrated_on', 'next_due_date',
        'performed_by', 'result', 'certificate_path', 'notes',
        'reminder_days_before_due', 'is_active',
    ];

    protected $casts = [
        'calibrated_on'            => 'date',
        'next_due_date'            => 'date',
        'reminder_days_before_due' => 'integer',
        'is_active'                => 'boolean',
    ];

    public const RESULTS = ['pass' => 'Pass', 'fail' => 'Fail', 'adjusted' => 'Adjusted'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function resultLabel(): string
    {
        return self::RESULTS[$this->result] ?? ucfirst((string) $this->result);
    }

    public function daysToDue(): ?int
    {
        if (! $this->next_due_date) {
            return null;
        }

        return (int) round(now()->startOfDay()->diffInDays($this->next_due_date->copy()->startOfDay(), false));
    }

    public function isDueWithin(int $days): bool
    {
        $d = $this->daysToDue();

        return $this->is_active && $d !== null && $d <= $days; // includes overdue (negative)
    }

    /** Active calibrations due on/before today+$days (incl. overdue). */
    public function scopeDueWithin(Builder $query, int $days): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_due_date')
            ->whereDate('next_due_date', '<=', now()->addDays($days)->toDateString());
    }
}
