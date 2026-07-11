<?php

namespace App\Modules\Pathway\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PathwayTemplate extends Model
{
    use HasUuid;

    protected $table = 'pathway_templates';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'category', 'steps', 'is_active',
    ];

    protected $casts = [
        'steps'     => 'array',
        'is_active' => 'boolean',
    ];

    public const CATEGORIES = [
        'medical'   => 'Medical',
        'surgical'  => 'Surgical',
        'obstetric' => 'Obstetric',
        'pediatric' => 'Pediatric',
        'emergency' => 'Emergency',
        'other'     => 'Other',
    ];
}
