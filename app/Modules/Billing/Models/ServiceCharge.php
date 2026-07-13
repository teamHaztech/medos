<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class ServiceCharge extends Model
{
    use HasUuid;

    protected $table = 'service_charges';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'code', 'category', 'price', 'is_taxable', 'gst_rate', 'hsn_sac', 'is_active',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'is_taxable' => 'boolean',
        'gst_rate'   => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public const CATEGORIES = [
        'consultation'  => 'Consultation',
        'room'          => 'Room / Bed',
        'nursing'       => 'Nursing',
        'procedure'     => 'Procedure',
        'investigation' => 'Investigation',
        'consumable'    => 'Consumable',
        'package'       => 'Package',
        'other'         => 'Other',
    ];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }
}
