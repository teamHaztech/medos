<?php

namespace App\Modules\Billing\Models;

use App\Modules\Core\Traits\BelongsToHospital;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillPayment extends Model
{
    use HasUuid;
    use BelongsToHospital;

    protected $table = 'bill_payments';

    protected $fillable = [
        'id', 'hospital_id', 'bill_id', 'amount', 'method', 'reference',
        'received_by', 'received_by_name', 'paid_at', 'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public const METHODS = ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'insurance' => 'Insurance', 'bank' => 'Bank Transfer', 'cheque' => 'Cheque'];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
