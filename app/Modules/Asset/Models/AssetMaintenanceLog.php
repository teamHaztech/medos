<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenanceLog extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'asset_maintenance_logs';

    protected $fillable = [
        'id', 'hospital_id', 'asset_id', 'maintenance_type', 'performed_by',
        'date', 'cost', 'next_due_date', 'notes',
    ];

    protected $casts = [
        'date'          => 'date',
        'next_due_date' => 'date',
        'cost'          => 'decimal:2',
    ];

    public const TYPES = [
        'preventive' => 'Preventive',
        'corrective' => 'Corrective',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->maintenance_type] ?? ucfirst((string) $this->maintenance_type);
    }
}
