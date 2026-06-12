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
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'is_active'     => 'boolean',
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
