<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Models\AccountActivity;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\RegionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function index()
    {
        $hospitals = Hospital::orderBy('country')->orderBy('name')->get();
        $regions = config('regions');

        // Cross-hospital KPIs
        $totalStaff    = DB::table('staff')->where('is_active', true)->count();
        $totalPatients = DB::table('patients')->count();
        $totalAppointmentsToday = DB::table('appointments')->whereDate('slot_start', today())->count();
        $totalRevenueToday = DB::table('bills')->whereDate('created_at', today())->sum('total_amount') ?? 0;

        // Per-hospital stats (batch queries)
        $staffCounts   = DB::table('staff')->where('is_active', true)->selectRaw('hospital_id, count(*) as cnt')->groupBy('hospital_id')->pluck('cnt', 'hospital_id');
        $patientCounts = DB::table('patients')->selectRaw('hospital_id, count(*) as cnt')->groupBy('hospital_id')->pluck('cnt', 'hospital_id');
        $aptCounts     = DB::table('appointments')->whereDate('slot_start', today())->selectRaw('hospital_id, count(*) as cnt')->groupBy('hospital_id')->pluck('cnt', 'hospital_id');
        $revCounts     = DB::table('bills')->whereDate('created_at', today())->selectRaw('hospital_id, coalesce(sum(total_amount),0) as rev')->groupBy('hospital_id')->pluck('rev', 'hospital_id');

        return view('superadmin.index', compact(
            'hospitals', 'regions',
            'totalStaff', 'totalPatients', 'totalAppointmentsToday', 'totalRevenueToday',
            'staffCounts', 'patientCounts', 'aptCounts', 'revCounts'
        ));
    }

    public function hospitalDetail(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $regions  = config('regions');
        $region   = $regions[$hospital->country] ?? $regions['IN'];

        // Staff at this hospital (primary assignment via staff.hospital_id)
        $staff = Staff::withoutGlobalScopes()
            ->where('hospital_id', $hospital->id)
            ->where('is_active', true)
            ->orderBy('department')->orderBy('name')
            ->get();

        // Also get staff assigned via pivot table (safe check)
        $hasPivotTables = Schema::hasTable('staff_hospital');
        $hasUserPivot   = Schema::hasTable('user_hospital');

        $pivotStaffIds = [];
        $pivotStaff    = collect();
        $pivotData     = collect();

        if ($hasPivotTables) {
            $pivotStaffIds = DB::table('staff_hospital')
                ->where('hospital_id', $hospital->id)
                ->where('is_active', true)
                ->pluck('staff_id')
                ->toArray();

            $pivotStaff = Staff::withoutGlobalScopes()
                ->whereIn('id', $pivotStaffIds)
                ->where('hospital_id', '!=', $hospital->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $pivotData = DB::table('staff_hospital')
                ->where('hospital_id', $hospital->id)
                ->get()
                ->keyBy('staff_id');
        }

        // Admins for this hospital
        $admins = User::where('hospital_id', $hospital->id)
            ->where('role', 'hospital_admin')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Also from user_hospital pivot
        $pivotAdmins = collect();

        if ($hasUserPivot) {
            $pivotAdminUserIds = DB::table('user_hospital')
                ->where('hospital_id', $hospital->id)
                ->where('role', 'hospital_admin')
                ->where('is_active', true)
                ->pluck('user_id')
                ->toArray();

            $pivotAdmins = User::whereIn('id', $pivotAdminUserIds)
                ->where('hospital_id', '!=', $hospital->id)
                ->where('is_active', true)
                ->get();
        }

        // Stats
        $patientCount   = DB::table('patients')->where('hospital_id', $hospital->id)->count();
        $appointmentCount = DB::table('appointments')->where('hospital_id', $hospital->id)->whereDate('slot_start', today())->count();
        $revenueToday   = DB::table('bills')->where('hospital_id', $hospital->id)->whereDate('created_at', today())->sum('total_amount') ?? 0;
        $totalRevenue   = DB::table('bills')->where('hospital_id', $hospital->id)->sum('total_amount') ?? 0;

        // All staff not at this hospital (for "add existing staff" dropdown)
        $allStaffIds = $staff->pluck('id')->merge($pivotStaffIds)->unique()->toArray();
        $availableStaff = Staff::withoutGlobalScopes()
            ->whereNotIn('id', $allStaffIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'department', 'role']);

        return view('superadmin.hospital-detail', compact(
            'hospital', 'region', 'regions',
            'staff', 'pivotStaff', 'pivotData',
            'admins', 'pivotAdmins',
            'patientCount', 'appointmentCount', 'revenueToday', 'totalRevenue',
            'availableStaff'
        ));
    }

    public function addStaffToHospital(Request $request, string $id)
    {
        $hospital = Hospital::findOrFail($id);

        if ($request->filled('staff_id')) {
            // Assign existing staff to this hospital via pivot
            $request->validate([
                'staff_id'   => 'required|exists:staff,id',
                'role'       => 'nullable|string|max:50',
                'department' => 'nullable|string|max:100',
            ]);

            $existingStaff = Staff::withoutGlobalScopes()->findOrFail($request->staff_id);

            if (Schema::hasTable('staff_hospital')) {
                DB::table('staff_hospital')->updateOrInsert(
                    ['staff_id' => $existingStaff->id, 'hospital_id' => $hospital->id],
                    [
                        'id'         => Str::uuid()->toString(),
                        'role'       => $request->input('role', is_object($existingStaff->role) ? $existingStaff->role->value : ($existingStaff->role ?? 'doctor')),
                        'department' => $request->input('department', $existingStaff->department),
                        'is_active'  => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            return redirect()->route('web.superadmin.hospitals.show', $hospital->id)
                ->with('success', $existingStaff->name . ' assigned to ' . $hospital->name);
        }

        // Create new staff
        $v = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|max:255',
            'role'       => 'required|string|max:50',
            'department' => 'nullable|string|max:100',
            'password'   => 'nullable|string|min:6',
        ]);

        $staffId = Str::uuid()->toString();
        $userId  = Str::uuid()->toString();

        // Create user account. staff_id links the login to the staff record so the
        // Doctor Console can resolve $user->staff — without it the queue is empty.
        User::create([
            'id'          => $userId,
            'name'        => $v['name'],
            'email'       => $v['email'],
            'password'    => Hash::make($v['password'] ?? 'password123'),
            'hospital_id' => $hospital->id,
            'staff_id'    => $staffId,
            'role'        => $v['role'],
            'is_active'   => true,
        ]);

        // Create staff record
        Staff::withoutGlobalScopes()->create([
            'id'          => $staffId,
            'hospital_id' => $hospital->id,
            'user_id'     => $userId,
            'name'        => $v['name'],
            'email'       => $v['email'],
            'role'        => $v['role'],
            'department'  => $v['department'] ?? null,
            'consultation_duration_default' => 15,
            'is_active'   => true,
        ]);

        // Also create pivot entry
        if (Schema::hasTable('staff_hospital')) {
            DB::table('staff_hospital')->insert([
                'id'          => Str::uuid()->toString(),
                'staff_id'    => $staffId,
                'hospital_id' => $hospital->id,
                'role'        => $v['role'],
                'department'  => $v['department'] ?? null,
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return redirect()->route('web.superadmin.hospitals.show', $hospital->id)
            ->with('success', 'Staff member "' . $v['name'] . '" created and assigned.');
    }

    public function addAdminToHospital(Request $request, string $id)
    {
        $hospital = Hospital::findOrFail($id);

        $v = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:6',
        ]);

        $userId = Str::uuid()->toString();

        User::create([
            'id'          => $userId,
            'name'        => $v['name'],
            'email'       => $v['email'],
            'password'    => Hash::make($v['password'] ?? 'password123'),
            'hospital_id' => $hospital->id,
            'role'        => 'hospital_admin',
            'is_active'   => true,
        ]);

        // Also create user_hospital pivot entry
        if (Schema::hasTable('user_hospital')) {
            DB::table('user_hospital')->insert([
                'id'          => Str::uuid()->toString(),
                'user_id'     => $userId,
                'hospital_id' => $hospital->id,
                'role'        => 'hospital_admin',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return redirect()->route('web.superadmin.hospitals.show', $hospital->id)
            ->with('success', 'Admin "' . $v['name'] . '" added to ' . $hospital->name);
    }

    /**
     * Reset a hospital user's (admin/staff) login password to a new temporary
     * one and show it so it can be handed over.
     */
    public function resetUserPassword(string $hospitalId, string $userId)
    {
        $hospital = Hospital::findOrFail($hospitalId);
        $user = User::where('id', $userId)->where('hospital_id', $hospitalId)->firstOrFail();

        $plain = Str::random(10);
        $user->update(['password' => Hash::make($plain), 'is_active' => true]);

        return redirect()->route('web.superadmin.hospitals.show', $hospital->id)->with('success',
            "New password for {$user->name} → {$user->email} / {$plain}  (share securely).");
    }

    public function removeStaffFromHospital(string $hospitalId, string $staffId)
    {
        $hospital = Hospital::findOrFail($hospitalId);
        $staff = Staff::withoutGlobalScopes()->findOrFail($staffId);

        // Remove from pivot table
        if (Schema::hasTable('staff_hospital')) {
            DB::table('staff_hospital')
                ->where('staff_id', $staffId)
                ->where('hospital_id', $hospitalId)
                ->delete();
        }

        // If the staff's primary hospital_id matches, we just deactivate the pivot — don't delete the staff record
        return redirect()->route('web.superadmin.hospitals.show', $hospital->id)
            ->with('success', $staff->name . ' removed from ' . $hospital->name);
    }

    public function createHospital()
    {
        $regions = config('regions');
        return view('superadmin.hospital-form', ['hospital' => null, 'regions' => $regions]);
    }

    public function storeHospital(Request $request)
    {
        $v = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => 'required|string|max:100|alpha_dash|unique:hospitals,slug',
            'country'        => 'required|in:IN,AE',
            'city'           => 'required|string|max:100',
            'state'          => 'nullable|string|max:100',
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            // Hospital Admin login created alongside the hospital.
            'admin_name'     => 'required|string|max:255',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_password' => 'nullable|string|min:6',
        ]);

        $hospitalId = Str::uuid()->toString();

        Hospital::create([
            'id'                  => $hospitalId,
            'name'                => $v['name'],
            'slug'                => $v['slug'],
            'country'             => $v['country'],
            'city'                => $v['city'],
            'state'               => $v['state'] ?? null,
            'address'             => $v['address'] ?? null,
            'phone'               => $v['phone'] ?? null,
            'email'               => $v['email'] ?? null,
            // Pass raw arrays — the Hospital model casts these to 'array' and
            // encodes them once. json_encode() here would double-encode and the
            // value would read back as a string (breaks in_array / isModuleEnabled).
            'config'              => [
                'departments' => [],
                'operating_hours' => ['open' => '08:00', 'close' => '21:00'],
            ],
            // New hospitals get the core comms/finance set plus every module added
            // this cycle (voice_calls, lab, pharmacy, inpatient, clinical, ops) so
            // nothing is silently missing — the super admin can turn any off later.
            'modules_enabled'     => array_values(array_unique(array_merge(
                ['ai_receptionist', 'whatsapp', 'triage', 'scheduling', 'queue', 'billing', 'analytics', 'engagement'],
                \App\Modules\Core\Support\ModuleCatalog::NEW_MODULE_KEYS,
            ))),
            'subscription_plan'   => 'standard',
            'subscription_status' => 'active',
            'is_active'           => true,
        ]);

        // Create the Hospital Admin login. They then create their own staff.
        $plainPassword = $request->filled('admin_password') ? $v['admin_password'] : Str::random(10);
        $userId = Str::uuid()->toString();

        User::create([
            'id'          => $userId,
            'name'        => $v['admin_name'],
            'email'       => $v['admin_email'],
            'password'    => Hash::make($plainPassword),
            'hospital_id' => $hospitalId,
            'role'        => 'hospital_admin',
            'is_active'   => true,
        ]);

        if (Schema::hasTable('user_hospital')) {
            DB::table('user_hospital')->insert([
                'id'          => Str::uuid()->toString(),
                'user_id'     => $userId,
                'hospital_id' => $hospitalId,
                'role'        => 'hospital_admin',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return redirect()->route('web.superadmin.index')->with('success',
            'Hospital "' . $v['name'] . '" created. Admin login → ' . $v['admin_email'] . ' / ' . $plainPassword . ' (share securely; ask them to change it).');
    }

    public function editHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $regions = config('regions');
        return view('superadmin.hospital-form', compact('hospital', 'regions'));
    }

    public function updateHospital(Request $request, string $id)
    {
        $hospital = Hospital::findOrFail($id);

        $v = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'required|string|max:100|alpha_dash|unique:hospitals,slug,' . $id,
            'country'   => 'required|in:IN,AE',
            'city'      => 'required|string|max:100',
            'state'     => 'nullable|string|max:100',
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $hospital->update([
            'name'      => $v['name'],
            'slug'      => $v['slug'],
            'country'   => $v['country'],
            'city'      => $v['city'],
            'state'     => $v['state'] ?? $hospital->state,
            'address'   => $v['address'] ?? $hospital->address,
            'phone'     => $v['phone'] ?? $hospital->phone,
            'email'     => $v['email'] ?? $hospital->email,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('web.superadmin.index')->with('success', 'Hospital updated.');
    }

    /** Super-admin control of which modules a hospital has enabled. */
    public function updateHospitalModules(Request $request, string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $valid = \App\Modules\Core\Support\ModuleCatalog::keys();
        $submitted = (array) $request->input('modules', []);

        // Preserve any enabled keys outside the catalog (e.g. scheduling, queue, analytics).
        $existing = (array) ($hospital->modules_enabled ?? []);
        $preserved = array_diff($existing, $valid);
        $enabled = array_values(array_unique(array_merge($preserved, array_intersect($valid, $submitted))));

        // Empty means "all on" (never-configured default); keep a sentinel so an
        // explicit all-off save sticks instead of silently re-enabling everything.
        if (empty($enabled)) {
            $enabled = ['__configured__'];
        }

        $hospital->update(['modules_enabled' => $enabled]);

        return redirect()->route('web.superadmin.hospitals.show', $hospital->id)
            ->with('success', 'Modules updated for ' . $hospital->name . '.');
    }

    public function deleteHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        // If the super admin is operating in this hospital, move their context off
        // it so the sidebar/banner don't keep pointing at a deactivated hospital.
        $this->detachActingUserFrom($hospital->id);
        $hospital->update(['is_active' => false]);
        return redirect()->route('web.superadmin.index')->with('success', 'Hospital "' . $hospital->name . '" deactivated.');
    }

    /**
     * Permanently delete a hospital and its data. If the super admin is currently
     * operating in it, their context is first moved to another active hospital
     * (or cleared) so the delete isn't blocked. Falls back to a deactivate if
     * linked records block the delete.
     */
    public function destroyHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $name = $hospital->name;

        // Move the acting super admin off this hospital before deleting, otherwise
        // the FK from users.hospital_id (SET NULL) would strand them on a dead id.
        $this->detachActingUserFrom($hospital->id);

        try {
            DB::transaction(fn () => $hospital->delete()); // cascades via FKs
            return redirect()->route('web.superadmin.index')->with('success', 'Hospital "' . $name . '" permanently deleted.');
        } catch (\Throwable $e) {
            $hospital->update(['is_active' => false]);
            return redirect()->route('web.superadmin.index')
                ->with('error', 'Could not fully delete "' . $name . '" (' . $e->getMessage() . ') — it has been deactivated instead.');
        }
    }

    /**
     * If the currently authenticated super admin is pinned to $hospitalId, repoint
     * them to another active hospital (or null). Super admins operate at the
     * platform level, so a null hospital context is fine.
     */
    private function detachActingUserFrom(string $hospitalId): void
    {
        $user = auth()->user();
        if (! $user || $user->hospital_id !== $hospitalId) {
            return;
        }

        $fallback = Hospital::where('id', '!=', $hospitalId)
            ->where('is_active', true)
            ->orderBy('name')
            ->value('id');

        $user->forceFill(['hospital_id' => $fallback])->save();
    }

    // ---------------------------------------------------------------
    // Backups (disaster recovery — Hostinger has no SSH/terminal)
    // ---------------------------------------------------------------

    /**
     * Download the ENTIRE SQLite database as a single file. This is the
     * platform-wide safety net: it is a complete, restorable snapshot that can
     * be re-uploaded via hPanel File Manager to recover from downtime.
     */
    public function downloadFullBackup()
    {
        $path = config('database.connections.sqlite.database') ?: database_path('database.sqlite');

        if (! is_string($path) || ! is_file($path)) {
            return redirect()->route('web.superadmin.index')
                ->with('error', 'Database file not found — only the SQLite driver supports a full-file backup.');
        }

        $stamp = now()->format('Y-m-d_His');

        return response()->download($path, "medos-full-backup-{$stamp}.sqlite", [
            'Content-Type' => 'application/x-sqlite3',
        ]);
    }

    /**
     * Export a single hospital's data as a restorable .sql file. Emits INSERT
     * statements (idempotent — INSERT OR IGNORE) for the hospital row plus every
     * table that carries a hospital_id, scoped to this hospital only. Restore by
     * running the file against the DB (or importing via a tool).
     */
    public function backupHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);

        $lines = [];
        $lines[] = '-- MedOS per-hospital backup';
        $lines[] = '-- Hospital: ' . $hospital->name . ' (' . $hospital->id . ')';
        $lines[] = '-- Generated: ' . now()->toDateTimeString();
        $lines[] = 'PRAGMA foreign_keys = OFF;';
        $lines[] = 'BEGIN TRANSACTION;';
        $lines[] = '';

        // 1. The hospital row itself.
        $lines[] = '-- Table: hospitals';
        foreach (DB::table('hospitals')->where('id', $id)->get() as $row) {
            $lines[] = $this->rowToInsert('hospitals', (array) $row);
        }
        $lines[] = '';

        // 2. Every other table that has a hospital_id column, scoped to this hospital.
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        foreach ($tables as $t) {
            $table = $t->name;
            if ($table === 'hospitals' || ! Schema::hasColumn($table, 'hospital_id')) {
                continue;
            }
            $rows = DB::table($table)->where('hospital_id', $id)->get();
            if ($rows->isEmpty()) {
                continue;
            }
            $lines[] = '-- Table: ' . $table . ' (' . $rows->count() . ' rows)';
            foreach ($rows as $row) {
                $lines[] = $this->rowToInsert($table, (array) $row);
            }
            $lines[] = '';
        }

        $lines[] = 'COMMIT;';
        $lines[] = 'PRAGMA foreign_keys = ON;';

        $sql   = implode("\n", $lines) . "\n";
        $slug  = Str::slug($hospital->name) ?: 'hospital';
        $stamp = now()->format('Y-m-d_His');

        return response($sql, 200, [
            'Content-Type'        => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $slug . '-backup-' . $stamp . '.sql"',
        ]);
    }

    /** Build a single `INSERT OR IGNORE` statement for one row. */
    private function rowToInsert(string $table, array $row): string
    {
        $cols = array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', array_keys($row));

        $vals = array_map(function ($v) {
            if ($v === null) {
                return 'NULL';
            }
            if (is_int($v) || is_float($v)) {
                return (string) $v;
            }
            if (is_bool($v)) {
                return $v ? '1' : '0';
            }

            return "'" . str_replace("'", "''", (string) $v) . "'";
        }, array_values($row));

        return 'INSERT OR IGNORE INTO "' . $table . '" (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ');';
    }

    // ---------------------------------------------------------------
    // IAM — user accounts across all hospitals
    // ---------------------------------------------------------------

    /**
     * All user accounts, grouped by hospital, with orphan / unknown-account
     * detection so the super admin can spot and remove accounts that aren't
     * tied to a known hospital or use an unrecognised role.
     */
    public function users(Request $request)
    {
        $hospitals   = Hospital::orderBy('name')->get()->keyBy('id');
        $validRoles  = array_map(fn ($c) => $c->value, \App\Modules\Core\Enums\UserRole::cases());

        $grouped = [];        // hospital_id => [users]
        $unknown = collect(); // orphan / unrecognised accounts
        $system  = collect(); // super admins

        foreach (User::orderBy('name')->get() as $u) {
            $roleVal = $u->role instanceof \BackedEnum ? $u->role->value : (string) $u->role;
            $u->role_str      = $roleVal;
            $u->role_known    = in_array($roleVal, $validRoles, true);
            $u->hospital_name = $u->hospital_id ? ($hospitals[$u->hospital_id]->name ?? null) : null;
            $u->is_orphan     = ! $u->role_known
                || ($roleVal !== 'super_admin' && ($u->hospital_id === null || ! isset($hospitals[$u->hospital_id])));

            if ($roleVal === 'super_admin') {
                $system->push($u);
            } elseif ($u->is_orphan) {
                $unknown->push($u);
            } else {
                $grouped[$u->hospital_id][] = $u;
            }
        }

        $allUsers = User::count();
        $counts = [
            'total'     => $allUsers,
            'active'    => User::where('is_active', true)->count(),
            'inactive'  => User::where('is_active', false)->count(),
            'unknown'   => $unknown->count(),
            'hospitals' => count($grouped),
        ];

        return view('superadmin.users', compact('hospitals', 'grouped', 'unknown', 'system', 'counts'));
    }

    /** Activate / deactivate a user account. */
    public function toggleUserActive(string $userId)
    {
        $target = User::findOrFail($userId);

        if ($target->id === auth()->id()) {
            return back()->with('error', 'You cannot change your own account status.');
        }

        $roleVal = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
        $newState = ! $target->is_active;

        if (! $newState && $roleVal === 'super_admin'
            && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()->with('error', 'Cannot deactivate the last active super admin.');
        }

        $target->update(['is_active' => $newState]);

        return back()->with('success', $target->email . ($newState ? ' activated.' : ' deactivated.'));
    }

    /**
     * Drill-down for a single account: profile, login/logout security history, and
     * recent audited actions — the "click an account and see what's going on" view.
     */
    public function userDetail(string $userId)
    {
        $user = User::findOrFail($userId);

        $hospitalName = $user->hospital_id
            ? Hospital::where('id', $user->hospital_id)->value('name')
            : null;

        $activity = AccountActivity::where('user_id', $user->id)
            ->orderByDesc('created_at')->limit(100)->get();

        $actions = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            $actions = AuditLog::where('user_id', $user->id)
                ->orderByDesc('created_at')->limit(50)->get();
        }

        $stats = [
            'logins'      => AccountActivity::where('user_id', $user->id)->where('action', 'login')->count(),
            'last_login'  => $user->last_login_at,
            'last_ip'     => $user->last_login_ip,
            'actions'     => $actions->count(),
        ];

        return view('superadmin.user-detail', compact('user', 'hospitalName', 'activity', 'actions', 'stats'));
    }

    /** Permanently delete a user account (e.g. an unknown / test account). */
    public function deleteUser(string $userId)
    {
        $target = User::findOrFail($userId);

        if ($target->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $roleVal = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
        if ($roleVal === 'super_admin' && User::where('role', 'super_admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the last super admin.');
        }

        $email = $target->email;
        // Clear the loose staff back-link so no staff row points at a deleted user.
        DB::table('staff')->where('user_id', $target->id)->update(['user_id' => null]);
        $target->delete();

        return back()->with('success', 'Account ' . $email . ' deleted.');
    }
}
