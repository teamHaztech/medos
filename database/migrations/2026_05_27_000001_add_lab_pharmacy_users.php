<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $password = Hash::make('password123');
        $hospitalId = DB::table('hospitals')->where('is_active', true)->value('id');

        if (!$hospitalId) return;

        // Check if lab user already exists
        if (!DB::table('users')->where('email', 'lab@haztech.in')->exists()) {
            $labUserId = Str::uuid()->toString();
            $labStaffId = Str::uuid()->toString();

            DB::table('users')->insert([
                'id' => $labUserId,
                'name' => 'Lab Technician',
                'email' => 'lab@haztech.in',
                'email_verified_at' => $now,
                'password' => $password,
                'hospital_id' => $hospitalId,
                'role' => 'lab_tech',
                'staff_id' => $labStaffId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('staff')->insert([
                'id' => $labStaffId,
                'hospital_id' => $hospitalId,
                'user_id' => $labUserId,
                'name' => 'Lab Technician',
                'email' => 'lab@haztech.in',
                'role' => 'lab_tech',
                'department' => 'Pathology',
                'specialization' => 'Clinical Pathology',
                'is_active' => true,
                'consultation_duration_default' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Check if pharmacist user already exists
        if (!DB::table('users')->where('email', 'pharmacy@haztech.in')->exists()) {
            $pharmUserId = Str::uuid()->toString();
            $pharmStaffId = Str::uuid()->toString();

            DB::table('users')->insert([
                'id' => $pharmUserId,
                'name' => 'Pharmacist',
                'email' => 'pharmacy@haztech.in',
                'email_verified_at' => $now,
                'password' => $password,
                'hospital_id' => $hospitalId,
                'role' => 'pharmacist',
                'staff_id' => $pharmStaffId,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('staff')->insert([
                'id' => $pharmStaffId,
                'hospital_id' => $hospitalId,
                'user_id' => $pharmUserId,
                'name' => 'Pharmacist',
                'email' => 'pharmacy@haztech.in',
                'role' => 'pharmacist',
                'department' => 'Pharmacy',
                'specialization' => 'Pharmacy',
                'is_active' => true,
                'consultation_duration_default' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('staff')->whereIn('email', ['lab@haztech.in', 'pharmacy@haztech.in'])->delete();
        DB::table('users')->whereIn('email', ['lab@haztech.in', 'pharmacy@haztech.in'])->delete();
    }
};
