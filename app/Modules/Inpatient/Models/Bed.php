<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bed extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'beds';

    protected $fillable = [
        'id', 'hospital_id', 'ward_id', 'bed_number', 'status', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public const STATUSES = ['available' => 'Available', 'occupied' => 'Occupied', 'maintenance' => 'Maintenance'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /** The active admission currently occupying this bed, if any. */
    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class, 'bed_id')->where('status', 'admitted');
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
