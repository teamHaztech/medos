<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Re-create Dr. Rajesh Kumar's doctor login and keep admin@haztech.in a pure
     * IT/hospital admin.
     *
     * The earlier 2026_06_02_000002 migration created the login, but db:seed
     * (which truncates users/staff) then wiped it on the same deploy. Now that
     * deploy.php no longer re-seeds a populated DB, this fresh migration re-creates
     * the login and it survives. Finds the staff record by name within City Care,
     * so it does not depend on the admin link. Idempotent.
     */
    public function up(): void
    {
        $hospitalId = DB::table('hospitals')->where('slug', 'city-care')->value('id')
            ?? DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id');

        if (! $hospitalId) {
            return;
        }

        $staff = DB::table('staff')
            ->where('hospital_id', $hospitalId)
            ->where('name', 'like', '%Rajesh Kumar%')
            ->first();

        if ($staff) {
            $email = 'rajesh.kumar@city-care.medos.local';
            $hasDoctorLogin = DB::table('users')->where('email', $email)->exists()
                || DB::table('users')->where('staff_id', $staff->id)->where('role', 'doctor')->exists();

            if (! $hasDoctorLogin) {
                $newId = Str::uuid()->toString();
                DB::table('users')->insert([
                    'id'                => $newId,
                    'name'              => $staff->name,
                    'email'             => $email,
                    'email_verified_at' => now(),
                    'password'          => Hash::make('password123'),
                    'hospital_id'       => $hospitalId,
                    'staff_id'          => $staff->id,
                    'role'              => 'doctor',
                    'is_active'         => true,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
                DB::table('staff')->where('id', $staff->id)->update(['user_id' => $newId]);
            }
        }

        // Keep the IT admin pure: hospital_admin, no staff link.
        DB::table('users')->where('email', 'admin@haztech.in')
            ->update(['staff_id' => null, 'role' => 'hospital_admin']);
    }

    public function down(): void
    {
        // No-op: one-time account correction.
    }
};
