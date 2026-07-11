<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Ensure a login exists for every department/role in the primary hospital so
 * the login page's quick-login buttons all work. Idempotent — skips any
 * account whose email already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('users') || ! DB::getSchemaBuilder()->hasTable('staff')) {
            return;
        }

        // Primary hospital (admin@haztech.in). Fall back to first active.
        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if (! $hospitalId) {
            return;
        }

        $accounts = [
            ['nurse@haztech.in',     'Staff Nurse',        'nurse',         'Nursing'],
            ['reception@haztech.in', 'Receptionist',       'receptionist',  'Front Office'],
            ['billing@haztech.in',   'Billing Executive',  'billing_staff', 'Billing'],
        ];

        $now = now();
        foreach ($accounts as [$email, $name, $role, $department]) {
            if (DB::table('users')->where('email', $email)->exists()) {
                continue;
            }

            $userId = Str::uuid()->toString();
            $staffId = Str::uuid()->toString();

            // User first (staff_id null) to avoid the users<->staff circular FK.
            DB::table('users')->insert([
                'id'                => $userId,
                'hospital_id'       => $hospitalId,
                'staff_id'          => null,
                'name'              => $name,
                'email'             => $email,
                'password'          => Hash::make('password123'),
                'role'              => $role,
                'is_active'         => true,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            DB::table('staff')->insert([
                'id'          => $staffId,
                'hospital_id' => $hospitalId,
                'user_id'     => $userId,
                'name'        => $name,
                'email'       => $email,
                'phone'       => null,
                'role'        => $role,
                'department'  => $department,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            DB::table('users')->where('id', $userId)->update(['staff_id' => $staffId]);
        }
    }

    public function down(): void
    {
        $emails = ['nurse@haztech.in', 'reception@haztech.in', 'billing@haztech.in'];
        DB::table('users')->whereIn('email', $emails)->delete();
        DB::table('staff')->whereIn('email', $emails)->delete();
    }
};
