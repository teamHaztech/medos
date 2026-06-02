<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Split the City Care IT Admin from the doctor it was conflated with.
     *
     * Seed/setup left admin@haztech.in acting as BOTH the hospital (IT) admin and
     * Dr. Rajesh Kumar (its staff_id pointed at his staff record). This:
     *  1. Gives Dr. Rajesh Kumar his own doctor login (-> Doctor Console).
     *  2. Makes admin@haztech.in a pure hospital_admin with no staff link, so it
     *     manages the hospital and creates staff accounts but is not a doctor.
     * Idempotent and guarded — safe to re-run.
     */
    public function up(): void
    {
        $admin = DB::table('users')->where('email', 'admin@haztech.in')->first();
        if (! $admin) {
            return;
        }

        $staffId = $admin->staff_id;

        if ($staffId) {
            $staff = DB::table('staff')->where('id', $staffId)->first();

            // Create a dedicated doctor login for this staff member if none exists.
            $docLogin = DB::table('users')
                ->where('staff_id', $staffId)
                ->where('id', '!=', $admin->id)
                ->first();

            if ($staff && ! $docLogin) {
                $email = 'rajesh.kumar@city-care.medos.local';
                if (! DB::table('users')->where('email', $email)->exists()) {
                    $newId = Str::uuid()->toString();
                    DB::table('users')->insert([
                        'id'          => $newId,
                        'name'        => $staff->name,
                        'email'       => $email,
                        'password'    => Hash::make('password123'),
                        'hospital_id' => $admin->hospital_id,
                        'staff_id'    => $staffId,
                        'role'        => 'doctor',
                        'is_active'   => true,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                    // Point the staff record's canonical login at the doctor account.
                    DB::table('staff')->where('id', $staffId)->update(['user_id' => $newId]);
                }
            }
        }

        // Make the admin a pure IT/hospital admin: drop the doctor link, ensure role.
        DB::table('users')->where('id', $admin->id)->update([
            'staff_id' => null,
            'role'     => 'hospital_admin',
        ]);
    }

    public function down(): void
    {
        // No-op: this is a one-time account correction.
    }
};
