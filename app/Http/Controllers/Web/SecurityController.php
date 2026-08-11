<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Models\AccountActivity;
use App\Modules\Core\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Security Center — account activity, anomaly flags, and account controls.
 * Scoped by the acting user's role: a super admin sees the whole platform; a
 * hospital admin sees (and manages) only their own hospital's accounts.
 */
class SecurityController extends Controller
{
    /** @return array{0:User,1:string,2:bool,3:?string} [user, role, isSuper, hospitalId] */
    private function actor(): array
    {
        $user = Auth::user();
        $role = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
        abort_unless(in_array($role, ['super_admin', 'hospital_admin'], true), 403);

        return [$user, $role, $role === 'super_admin', $user->hospital_id];
    }

    public function index(Request $request)
    {
        [$actor, $role, $isSuper, $hid] = $this->actor();

        // Accounts in scope.
        $users = User::query()
            ->when(! $isSuper, fn ($q) => $q->where('hospital_id', $hid))
            ->orderBy('name')->get();

        $emails = $users->pluck('email')->filter()->map(fn ($e) => strtolower($e))->all();
        $now = now();

        // Activity in scope: authenticated rows carry hospital_id; failed logins do
        // not (no user yet), so scope those by the attempted email.
        $activity = AccountActivity::query()
            ->when(! $isSuper, function ($q) use ($hid, $emails) {
                $q->where(function ($w) use ($hid, $emails) {
                    $w->where('hospital_id', $hid)
                        ->orWhere(fn ($x) => $x->where('action', 'failed_login')
                            ->whereIn(\DB::raw('lower(user_email)'), $emails));
                });
            })
            ->orderByDesc('created_at')
            ->limit(2000)->get();

        $recent   = $activity->take(60);
        $last24h  = $activity->where('created_at', '>=', $now->copy()->subDay());
        $last7d   = $activity->where('created_at', '>=', $now->copy()->subDays(7));

        // KPI tiles.
        $kpis = [
            'accounts'    => $users->count(),
            'active'      => $users->where('is_active', true)->count(),
            'disabled'    => $users->where('is_active', false)->count(),
            'logins_24h'  => $last24h->where('action', 'login')->count(),
            'failed_24h'  => $last24h->where('action', 'failed_login')->count(),
            'never'       => $users->whereNull('last_login_at')->count(),
        ];

        // --- Anomaly detection ---
        $flags = [];

        // 1) Brute-force: an email with >= 5 failed sign-ins in the last 24h.
        $failedByEmail = $last24h->where('action', 'failed_login')
            ->groupBy(fn ($a) => strtolower((string) $a->user_email));
        foreach ($failedByEmail as $email => $rows) {
            if ($rows->count() >= 5) {
                $flags[] = [
                    'level' => 'high', 'icon' => 'alert',
                    'title' => $rows->count() . ' failed sign-ins',
                    'detail' => ($email ?: 'unknown') . ' · last ' . optional($rows->first()->created_at)->diffForHumans(),
                ];
            }
        }

        // 2) Multiple IPs: an account signing in from >= 3 distinct IPs in 30 days.
        $loginsByUser = $activity->where('action', 'login')
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->groupBy('user_id');
        foreach ($loginsByUser as $uid => $rows) {
            $ips = $rows->pluck('ip_address')->filter()->unique();
            if ($ips->count() >= 3) {
                $flags[] = [
                    'level' => 'warn', 'icon' => 'users',
                    'title' => $ips->count() . ' different IP addresses',
                    'detail' => ($rows->first()->user_name ?: $rows->first()->user_email) . ' signed in from ' . $ips->count() . ' IPs in 30 days',
                ];
            }
        }

        // 3) Disabled account still being used (login attempt while inactive).
        $disabledEmails = $users->where('is_active', false)->pluck('email')->map(fn ($e) => strtolower($e))->all();
        foreach ($last7d->whereIn('action', ['login', 'failed_login']) as $a) {
            if (in_array(strtolower((string) $a->user_email), $disabledEmails, true)) {
                $flags[] = [
                    'level' => 'warn', 'icon' => 'clock',
                    'title' => 'Disabled account attempted access',
                    'detail' => $a->user_email . ' · ' . optional($a->created_at)->diffForHumans(),
                ];
                break; // one flag is enough to prompt review
            }
        }

        // 4) Stale privileged account: an admin with no sign-in in 30 days.
        foreach ($users as $u) {
            $r = $u->role instanceof \BackedEnum ? $u->role->value : (string) $u->role;
            if (in_array($r, ['super_admin', 'hospital_admin'], true) && $u->is_active
                && (! $u->last_login_at || $u->last_login_at->lt($now->copy()->subDays(30)))) {
                $flags[] = [
                    'level' => 'warn', 'icon' => 'clock',
                    'title' => 'Stale admin account',
                    'detail' => $u->name . ' (' . $r . ') — ' . ($u->last_login_at ? 'last in ' . $u->last_login_at->diffForHumans() : 'never signed in'),
                ];
            }
        }

        // ---- SIEM: correlation / threat detection (higher-order than the flags) ----
        $threats = [];

        // Credential stuffing: one IP failing sign-in across >= 3 distinct accounts.
        foreach ($last7d->where('action', 'failed_login')->whereNotNull('ip_address')->groupBy('ip_address') as $ip => $rows) {
            $accts = $rows->pluck('user_email')->filter()->unique();
            if ($accts->count() >= 3) {
                $threats[] = ['level' => 'high', 'title' => 'Possible credential stuffing',
                    'detail' => $ip . ' hit ' . $accts->count() . ' accounts with ' . $rows->count() . ' failures (7d)'];
            }
        }
        // Possible compromise: >= 3 failures then a success for the same account in a day.
        foreach ($last7d->whereIn('action', ['login', 'failed_login'])->groupBy(fn ($a) => strtolower((string) $a->user_email) . '|' . optional($a->created_at)->format('Y-m-d')) as $k => $rows) {
            if ($rows->where('action', 'failed_login')->count() >= 3 && $rows->where('action', 'login')->count() >= 1) {
                $threats[] = ['level' => 'high', 'title' => 'Sign-in succeeded after repeated failures',
                    'detail' => explode('|', $k)[0] . ' — review for account takeover'];
            }
        }
        // Off-hours admin sign-in (before 06:00 or after 22:00).
        $offHours = 0;
        foreach ($last7d->where('action', 'login') as $a) {
            $r = $a->role;
            $h = (int) optional($a->created_at)->format('G');
            if (in_array($r, ['super_admin', 'hospital_admin'], true) && ($h < 6 || $h >= 22)) {
                if ($offHours++ < 3) {
                    $threats[] = ['level' => 'warn', 'title' => 'Off-hours admin sign-in',
                        'detail' => ($a->user_name ?: $a->user_email) . ' at ' . optional($a->created_at)->format('H:i') . ' · ' . optional($a->created_at)->diffForHumans()];
                }
            }
        }
        $threats = array_slice($threats, 0, 12);

        // ---- SIEM: 14-day sign-in vs failure trend ----
        $trend = [];
        $maxTrend = 1;
        for ($d = 13; $d >= 0; $d--) {
            $day = $now->copy()->subDays($d);
            $key = $day->format('Y-m-d');
            $rows = $activity->filter(fn ($a) => optional($a->created_at)->format('Y-m-d') === $key);
            $ok = $rows->where('action', 'login')->count();
            $bad = $rows->where('action', 'failed_login')->count();
            $maxTrend = max($maxTrend, $ok, $bad);
            $trend[] = ['label' => $day->format('M j'), 'dow' => $day->format('D'), 'logins' => $ok, 'failed' => $bad];
        }

        // ---- SIEM: top source IPs (last 30 days in-scope) ----
        $topIps = [];
        foreach ($activity->whereNotNull('ip_address')->groupBy('ip_address') as $ip => $rows) {
            $topIps[] = [
                'ip'       => $ip,
                'total'    => $rows->count(),
                'failed'   => $rows->where('action', 'failed_login')->count(),
                'success'  => $rows->where('action', 'login')->count(),
                'accounts' => $rows->pluck('user_email')->filter()->unique()->count(),
                'last'     => $rows->max('created_at'),
            ];
        }
        usort($topIps, fn ($a, $b) => $b['total'] <=> $a['total']);
        $topIps = array_slice($topIps, 0, 8);

        // ---- SIEM: filterable, paginated event explorer (own DB query) ----
        $fAction   = $request->get('action');
        $fSearch   = trim((string) $request->get('q', ''));
        $fHospital = $isSuper ? $request->get('hospital') : null;
        $fFrom     = $request->get('from');
        $fTo       = $request->get('to');

        $events = AccountActivity::query()
            ->when(! $isSuper, function ($q) use ($hid, $emails) {
                $q->where(function ($w) use ($hid, $emails) {
                    $w->where('hospital_id', $hid)
                        ->orWhere(fn ($x) => $x->where('action', 'failed_login')
                            ->whereIn(\DB::raw('lower(user_email)'), $emails));
                });
            })
            ->when($fHospital, fn ($q) => $q->where('hospital_id', $fHospital))
            ->when($fAction, fn ($q) => $q->where('action', $fAction))
            ->when($fSearch !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('user_name', 'like', "%{$fSearch}%")
                ->orWhere('user_email', 'like', "%{$fSearch}%")
                ->orWhere('ip_address', 'like', "%{$fSearch}%")
                ->orWhere('description', 'like', "%{$fSearch}%")))
            ->when($fFrom, fn ($q) => $q->whereDate('created_at', '>=', $fFrom))
            ->when($fTo, fn ($q) => $q->whereDate('created_at', '<=', $fTo))
            ->orderByDesc('created_at')
            ->paginate(50)->withQueryString();

        $hospitals = Hospital::orderBy('name')->pluck('name', 'id');

        return view('admin.security', compact('actor', 'role', 'isSuper', 'hid', 'users', 'kpis',
            'flags', 'threats', 'trend', 'maxTrend', 'topIps', 'events', 'recent', 'hospitals',
            'fAction', 'fSearch', 'fHospital', 'fFrom', 'fTo'));
    }

    /** Export the scoped security event log as JSON for an external SIEM. */
    public function export(Request $request)
    {
        [$actor, $role, $isSuper, $hid] = $this->actor();

        $emails = User::query()
            ->when(! $isSuper, fn ($q) => $q->where('hospital_id', $hid))
            ->pluck('email')->filter()->map(fn ($e) => strtolower($e))->all();

        $rows = AccountActivity::query()
            ->when(! $isSuper, function ($q) use ($hid, $emails) {
                $q->where(function ($w) use ($hid, $emails) {
                    $w->where('hospital_id', $hid)
                        ->orWhere(fn ($x) => $x->where('action', 'failed_login')
                            ->whereIn(\DB::raw('lower(user_email)'), $emails));
                });
            })
            ->orderByDesc('created_at')->limit(5000)->get()
            ->map(fn ($a) => [
                'timestamp'   => optional($a->created_at)->toIso8601String(),
                'action'      => $a->action,
                'user'        => $a->user_name,
                'email'       => $a->user_email,
                'role'        => $a->role,
                'hospital'    => $a->hospital_name,
                'ip'          => $a->ip_address,
                'user_agent'  => $a->user_agent,
                'description' => $a->description,
            ]);

        $filename = 'medos-security-events-' . now()->format('Ymd-His') . '.json';

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'scope'       => $isSuper ? 'platform' : 'hospital',
            'count'       => $rows->count(),
            'events'      => $rows,
        ], 200, ['Content-Disposition' => 'attachment; filename="' . $filename . '"'], JSON_PRETTY_PRINT);
    }

    /** Enable / disable an account (hospital admin scoped to own hospital). */
    public function toggleActive(string $userId)
    {
        [$actor, $role, $isSuper, $hid] = $this->actor();
        $target = $this->scopedTarget($userId, $isSuper, $hid);

        if ($target->id === $actor->id) {
            return back()->with('error', 'You cannot change your own account status.');
        }
        $tRole = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
        if ($tRole === 'super_admin'
            && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Cannot disable the last active super admin.');
        }

        $newState = ! $target->is_active;
        $target->update(['is_active' => $newState]);
        AccountActivity::record($actor, 'update', request(), null,
            ($newState ? 'Enabled' : 'Disabled') . ' account ' . $target->email);

        return back()->with('success', $target->email . ($newState ? ' enabled.' : ' disabled.'));
    }

    /** Reset an account's password to a new random one (shown once). */
    public function resetPassword(string $userId)
    {
        [$actor, $role, $isSuper, $hid] = $this->actor();
        $target = $this->scopedTarget($userId, $isSuper, $hid);

        $plain = Str::random(12);
        $target->forceFill([
            'password'             => Hash::make($plain),
            'is_active'            => true,
            'must_change_password' => true,   // force the user to set their own at next login
        ])->save();
        AccountActivity::record($actor, 'update', request(), null, 'Reset password for ' . $target->email);

        return back()->with('success', "New password for {$target->name} → {$target->email} / {$plain}  (share securely, they should change it).");
    }

    /**
     * Resolve the target user, enforcing that a hospital admin can only act on
     * their own hospital's non-super-admin accounts.
     */
    private function scopedTarget(string $userId, bool $isSuper, ?string $hid): User
    {
        $target = User::findOrFail($userId);
        if (! $isSuper) {
            $tRole = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
            abort_if($target->hospital_id !== $hid || $tRole === 'super_admin', 403,
                'You can only manage accounts in your own hospital.');
        }

        return $target;
    }
}
