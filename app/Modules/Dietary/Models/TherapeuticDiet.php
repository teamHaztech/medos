<?php

namespace App\Modules\Dietary\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TherapeuticDiet extends Model
{
    use HasUuid;

    protected $table = 'therapeutic_diets';

    protected $fillable = [
        'id', 'hospital_id', 'code', 'name', 'category', 'default_texture',
        'indications', 'restrictions', 'default_kcal', 'default_protein_g', 'is_active',
    ];

    protected $casts = [
        'default_kcal'      => 'integer',
        'default_protein_g' => 'integer',
        'is_active'         => 'boolean',
    ];

    public const CATEGORIES = [
        'regular'     => 'Regular',
        'soft'        => 'Soft / Texture-modified',
        'liquid'      => 'Liquid',
        'therapeutic' => 'Therapeutic',
        'enteral'     => 'Enteral / Tube',
        'npo'         => 'NPO',
    ];

    // IDDSI-aligned texture levels
    public const TEXTURES = [
        'regular'      => 'Regular (IDDSI 7)',
        'soft'         => 'Soft & Bite-sized (6)',
        'minced_moist' => 'Minced & Moist (5)',
        'pureed'       => 'Pureed (4)',
        'liquid'       => 'Liquidised (3)',
        'clear_liquid' => 'Clear/Thin fluid (0)',
    ];
}
