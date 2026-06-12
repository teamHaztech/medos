<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetServiceRequest extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'asset_service_requests';

    protected $fillable = [
        'id', 'hospital_id', 'asset_id', 'reported_by', 'reported_at', 'issue',
        'priority', 'status', 'assigned_to', 'resolution_notes', 'resolved_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'];
    public const STATUSES   = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }

    /** Downtime in whole hours between report and resolution (null if unresolved). */
    public function downtimeHours(): ?int
    {
        if (! $this->reported_at || ! $this->resolved_at) {
            return null;
        }

        return (int) round($this->reported_at->diffInHours($this->resolved_at));
    }
}
