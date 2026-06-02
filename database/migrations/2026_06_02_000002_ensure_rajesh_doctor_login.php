<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Robustly ensure Dr. Rajesh Kumar (City Care) has his own doctor login, and
     * admin@haztech.in is a pure IT/hospital admin.
     *
     * The earlier 2026_06_02_000001 split only created the doctor login when the
     * admin happened to carry a staff_id link, which isn't guaranteed on prod.
     * This one finds the staff record by name within City Care directly, so it
     * works regardless of how the admin/staff were linked. Idempotent.
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
            $alreadyHasDoctorLogin = DB::table('users')->where('email', $email)->exists()
                || DB::table('users')->where('staff_id', $staff->id)->where('role', 'doctor')->exists();

            if (! $alreadyHasDoctorLogin) {
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

        // Make the admin a pure IT/hospital admin (no doctor link).
        DB::table('users')->where('email', 'admin@haztech.in')
            ->update(['staff_id' => null, 'role' => 'hospital_admin']);
    }

    public function down(): void
    {
        // No-op: one-time account correction.
    }
};
