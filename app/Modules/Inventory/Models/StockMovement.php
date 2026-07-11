<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUuid;

    protected $table = 'stock_movements';

    public $timestamps = false;

    protected $fillable = [
        'id', 'hospital_id', 'item_id', 'type', 'quantity', 'batch_number',
        'expiry_date', 'department', 'reference', 'performed_by_name', 'notes', 'created_at',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'expiry_date' => 'date',
        'created_at'  => 'datetime',
    ];

    public const TYPES = ['receipt' => 'Receipt', 'issue' => 'Issue', 'adjustment' => 'Adjustment'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
