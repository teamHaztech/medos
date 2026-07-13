<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Append-only security log of account activity (logins, logouts, failed logins,
 * and other notable actions). Not hospital-scoped by a global scope — hospital_id
 * is nullable so super-admin and failed-login rows are supported.
 */
class AccountActivity extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $table = 'account_activity';

    protected $fillable = [
        'id', 'user_id', 'user_name', 'user_email', 'role', 'hospital_id',
        'hospital_name', 'action', 'description', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public const ACTIONS = [
        'login'        => 'Signed in',
        'logout'       => 'Signed out',
        'failed_login' => 'Failed sign-in',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Record an activity row. Pass a User for authenticated actions, or null +
     * an email for failed logins.
     */
    public static function record(?User $user, string $action, ?Request $request = null, ?string $email = null, ?string $description = null): void
    {
        try {
            $role = $user ? ($user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role) : null;
            $hospitalName = null;
            if ($user?->hospital_id) {
                $hospitalName = Hospital::where('id', $user->hospital_id)->value('name');
            }

            static::create([
                'id'            => Str::uuid()->toString(),
                'user_id'       => $user?->id,
                'user_name'     => $user?->name,
                'user_email'    => $user?->email ?? $email,
                'role'          => $role,
                'hospital_id'   => $user?->hospital_id,
                'hospital_name' => $hospitalName,
                'action'        => $action,
                'description'   => $description,
                'ip_address'    => $request?->ip(),
                'user_agent'    => $request ? Str::limit((string) $request->userAgent(), 480, '') : null,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[AccountActivity] record failed: ' . $e->getMessage());
        }
    }
}
