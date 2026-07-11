<?php

namespace App\Modules\Housekeeping\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class HousekeepingLog extends Model
{
    use HasUuid;

    protected $table = 'housekeeping_logs';

    protected $fillable = [
        'id', 'hospital_id', 'location', 'category', 'description', 'priority',
        'status', 'reported_by_name', 'assigned_to_name', 'closure_notes', 'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'cleanliness'    => 'Cleanliness',
        'waste'          => 'Waste Disposal',
        'linen'          => 'Linen',
        'consumable'     => 'Consumable Shortage',
        'item_missing'   => 'Item Not in Place',
        'equipment'      => 'Equipment',
        'non_compliance' => 'Non-compliance',
        'other'          => 'Other',
    ];

    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const STATUSES = ['open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed'];
}
