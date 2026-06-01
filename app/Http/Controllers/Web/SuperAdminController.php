<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Billing\Models\Bill;
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
            'name'    => 'required|string|max:255',
            'slug'    => 'required|string|max:100|alpha_dash|unique:hospitals,slug',
            'country' => 'required|in:IN,AE',
            'city'    => 'required|string|max:100',
            'state'   => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
        ]);

        $region = RegionService::get($v['country']);

        Hospital::create([
            'id'                  => Str::uuid()->toString(),
            'name'                => $v['name'],
            'slug'                => $v['slug'],
            'country'             => $v['country'],
            'city'                => $v['city'],
            'state'               => $v['state'] ?? null,
            'address'             => $v['address'] ?? null,
            'phone'               => $v['phone'] ?? null,
            'email'               => $v['email'] ?? null,
            'config'              => json_encode([
                'departments' => [],
                'operating_hours' => ['open' => '08:00', 'close' => '21:00'],
            ]),
            'modules_enabled'     => json_encode(['ai_receptionist', 'whatsapp', 'triage', 'scheduling', 'queue', 'billing', 'analytics', 'engagement']),
            'subscription_plan'   => 'standard',
            'subscription_status' => 'active',
            'is_active'           => true,
        ]);

        return redirect()->route('web.superadmin.index')->with('success', 'Hospital "' . $v['name'] . '" created.');
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

    public function deleteHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['is_active' => false]);
        return redirect()->route('web.superadmin.index')->with('success', 'Hospital deactivated.');
    }
}
