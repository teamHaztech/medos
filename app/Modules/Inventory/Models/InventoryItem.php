<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasUuid;

    protected $table = 'inventory_items';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'code', 'category', 'unit',
        'reorder_min', 'reorder_max', 'current_stock', 'is_active',
    ];

    protected $casts = [
        'reorder_min'   => 'integer',
        'reorder_max'   => 'integer',
        'current_stock' => 'integer',
        'is_active'     => 'boolean',
    ];

    public const CATEGORIES = [
        'consumable' => 'Consumable',
        'drug'       => 'Drug',
        'surgical'   => 'Surgical',
        'linen'      => 'Linen',
        'stationery' => 'Stationery',
        'ppe'        => 'PPE',
        'other'      => 'Other',
    ];

    public const UNITS = ['piece', 'box', 'pack', 'bottle', 'vial', 'roll', 'pair', 'strip', 'ml', 'mg'];

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->reorder_min;
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'item_id')->orderByDesc('created_at');
    }
}
