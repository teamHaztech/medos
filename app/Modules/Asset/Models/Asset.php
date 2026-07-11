<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'assets';

    protected $fillable = [
        'id', 'hospital_id', 'asset_name', 'asset_type', 'serial_number',
        'model', 'manufacturer', 'department', 'location', 'purchase_date',
        'purchase_cost', 'vendor_id', 'status', 'notes', 'is_active',
        'decommissioned_on', 'decommission_reason', 'disposal_method',
        'useful_life_years', 'salvage_value',
    ];

    protected $casts = [
        'purchase_date'     => 'date',
        'purchase_cost'     => 'decimal:2',
        'salvage_value'     => 'decimal:2',
        'useful_life_years' => 'integer',
        'decommissioned_on' => 'date',
        'is_active'         => 'boolean',
    ];

    /** Canonical status values + human labels. */
    public const STATUSES = [
        'active'            => 'Active',
        'under_maintenance' => 'Under Maintenance',
        'decommissioned'    => 'Decommissioned',
    ];

    /** Common OT/hospital equipment types (suggestions, not enforced). */
    public const TYPES = [
        'OT Table', 'Anesthesia Machine', 'Ventilator', 'Patient Monitor',
        'Electrosurgical Cautery', 'OT Light', 'Defibrillator', 'Infusion Pump',
        'Suction Apparatus', 'C-Arm', 'Autoclave/Sterilizer', 'Surgical Microscope',
    ];

    public const DEPARTMENTS = ['OT', 'ICU', 'Ward', 'Emergency', 'Radiology', 'Laboratory', 'OPD'];

    // ---------------------------------------------------------------
    // Depreciation (straight-line)
    // ---------------------------------------------------------------

    /** Depreciable base = cost − salvage value. */
    public function depreciableBase(): float
    {
        return max(0, (float) $this->purchase_cost - (float) ($this->salvage_value ?? 0));
    }

    public function annualDepreciation(): float
    {
        $life = (int) ($this->useful_life_years ?? 0);

        return $life > 0 ? round($this->depreciableBase() / $life, 2) : 0.0;
    }

    /** Whole+fractional years since purchase (0 if no purchase date). */
    public function ageYears(): float
    {
        return $this->purchase_date ? max(0, $this->purchase_date->floatDiffInYears(now())) : 0.0;
    }

    public function accumulatedDepreciation(): float
    {
        if (! $this->useful_life_years || ! $this->purchase_cost) {
            return 0.0;
        }

        return round(min($this->annualDepreciation() * $this->ageYears(), $this->depreciableBase()), 2);
    }

    /** Current written-down / book value, floored at salvage value. */
    public function bookValue(): float
    {
        if (! $this->purchase_cost) {
            return 0.0;
        }

        return round((float) $this->purchase_cost - $this->accumulatedDepreciation(), 2);
    }

    // ---------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warranties(): HasMany
    {
        return $this->hasMany(AssetWarranty::class)->orderByDesc('end_date');
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class)->orderByDesc('date');
    }

    public function calibrations(): HasMany
    {
        return $this->hasMany(AssetCalibration::class)->orderByDesc('next_due_date');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(AssetServiceRequest::class)->orderByDesc('reported_at');
    }

    /** Total downtime (hours) across resolved service requests. */
    public function downtimeHours(): int
    {
        return (int) $this->serviceRequests
            ->sum(fn (AssetServiceRequest $r) => $r->downtimeHours() ?? 0);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /** The current (non-expired, active) warranty with the furthest end date, if any. */
    public function activeWarranty(): ?AssetWarranty
    {
        return $this->warranties
            ->where('is_active', true)
            ->filter(fn (AssetWarranty $w) => $w->end_date && $w->end_date->gte(now()->startOfDay()))
            ->sortByDesc('end_date')
            ->first();
    }

    public function hasActiveWarranty(): bool
    {
        return $this->activeWarranty() !== null;
    }
}
