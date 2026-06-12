<?php

namespace App\Modules\Asset\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'vendors';

    protected $fillable = [
        'id', 'hospital_id', 'name', 'contact_person', 'phone', 'email',
        'address', 'service_type', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
