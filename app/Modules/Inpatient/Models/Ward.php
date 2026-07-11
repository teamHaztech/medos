<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'wards';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'ward_type', 'daily_rate', 'nursing_daily_rate', 'floor', 'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'daily_rate'         => 'decimal:2',
        'nursing_daily_rate' => 'decimal:2',
    ];

    public const TYPES = ['General', 'Semi-Private', 'Private', 'ICU', 'Maternity', 'Pediatric', 'Emergency'];

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class)->orderBy('bed_number');
    }

    public function occupiedCount(): int
    {
        return $this->beds->where('status', 'occupied')->count();
    }

    public function availableCount(): int
    {
        return $this->beds->where('status', 'available')->where('is_active', true)->count();
    }

    public function occupancyPct(): int
    {
        $total = max(1, $this->beds->where('is_active', true)->count());
        return (int) round(($this->occupiedCount() / $total) * 100);
    }
}
