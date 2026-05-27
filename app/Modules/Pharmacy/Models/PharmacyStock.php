<?php
namespace App\Modules\Pharmacy\Models;

use App\Modules\Core\Traits\HasUuid;
use App\Modules\Core\Traits\BelongsToHospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyStock extends Model
{
    use HasUuid, BelongsToHospital;

    protected $table = 'pharmacy_stock';

    protected $fillable = [
        'id',
        'hospital_id',
        'medicine_id',
        'batch_number',
        'expiry_date',
        'quantity_total',
        'quantity_available',
        'purchase_price',
        'selling_price',
        'supplier',
        'received_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'received_at' => 'datetime',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(\Illuminate\Database\Eloquent\Model::class, 'medicine_id')
            ->withDefault();
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('quantity_available', '>', 0);
    }

    public function scopeExpiringSoon(Builder $query): Builder
    {
        return $query->where('expiry_date', '<=', now()->addDays(30))
            ->where('expiry_date', '>', now());
    }
}
