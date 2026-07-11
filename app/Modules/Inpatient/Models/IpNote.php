<?php

namespace App\Modules\Inpatient\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpNote extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'ip_notes';

    protected $fillable = [
        'id', 'hospital_id', 'admission_id', 'author_id', 'author_name',
        'author_role', 'note_type', 'body',
    ];

    public const TYPES = ['doctor' => "Doctor's Note", 'nurse' => "Nurse's Note", 'order' => "Doctor's Order"];

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->note_type] ?? ucfirst((string) $this->note_type);
    }
}
