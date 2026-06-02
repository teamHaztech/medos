<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Restore Healthway Hospital, its 20 doctors, and a hospital admin.
     *
     * Healthway was created at runtime (Super Admin + importer) and was wiped when
     * an earlier deploy re-ran the truncating seeders. Deploys no longer re-seed a
     * populated DB (v2.5.26), so recreating it here makes it persist. The original
     * hospital UUID is reused so existing links (e.g. the chat URL) keep working.
     * Idempotent — skips anything that already exists.
     */
    public function up(): void
    {
        $hospitalId = '4cabe5e9-e25b-4502-b3c4-28b90903f41a';
        $now = now();
        $password = Hash::make('password123');

        // 1. Hospital
        $existing = DB::table('hospitals')->where('slug', 'HWH')->orWhere('id', $hospitalId)->first();
        if ($existing) {
            $hospitalId = $existing->id;
        } else {
            DB::table('hospitals')->insert([
                'id'                  => $hospitalId,
                'name'                => 'Healthway Hospital',
                'slug'                => 'HWH',
                'country'             => 'IN',
                'city'                => 'Panaji',
                'state'               => 'Goa',
                'config'              => json_encode(['departments' => [], 'operating_hours' => ['open' => '08:00', 'close' => '21:00']]),
                'modules_enabled'     => json_encode(['ai_receptionist', 'whatsapp', 'triage', 'scheduling', 'queue', 'billing', 'analytics', 'engagement']),
                'subscription_plan'   => 'standard',
                'subscription_status' => 'active',
                'is_active'           => true,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // 2. Doctors (page 1 + page 2 from the directory)
        $doctors = [
            ['Dr. Amit Subhash Kalangutkar', 'General Medicine'],
            ['Dr. Praveen Satish', 'General Medicine'],
            ['Dr. Reuben De Souza', 'General Medicine'],
            ['Dr. Hemchandra Maenkar', 'General Medicine'],
            ['Dr. Suraj Rane', 'General Medicine'],
            ['Dr. Farook Sayed', 'General Medicine'],
            ['Dr. Aparna Joshi', 'Gynecology'],
            ['Dr. Chandrakant Sharma', 'General Medicine'],
            ['Dr. Janitta Shamkant Kundaikar', 'Pediatrics'],
            ['Dr. Prathibha B. Naik', 'Gynecology'],
            ['Dr. Simran', 'General Medicine'],
            ['Dr. Ashwini Colaco', 'General Medicine'],
            ['Dr. Vasan Satya Srini', 'General Medicine'],
            ['Dr. Rudra Nayak', 'General Medicine'],
            ['Dr. Divesha Shikerkar', 'General Medicine'],
            ['Dr. Akshada Amonkar', 'General Medicine'],
            ['Dr. Gaurav Sardesai', 'General Medicine'],
            ['Dr. Harish Peshwe', 'General Medicine'],
            ['Dr. Mayank Prakash Nigam', 'General Medicine'],
            ['Dr. Antonio De Bossuet Afonso', 'General Medicine'],
        ];

        foreach ($doctors as [$name, $department]) {
            $email = trim(preg_replace('/[^a-z0-9]+/', '.', preg_replace('/^dr\.?\s+/', '', strtolower($name))), '.') . '@healthway.medos.local';
            if (DB::table('users')->where('email', $email)->exists()) {
                continue;
            }
            $userId  = Str::uuid()->toString();
            $staffId = Str::uuid()->toString();
            DB::table('users')->insert([
                'id' => $userId, 'name' => $name, 'email' => $email, 'email_verified_at' => $now,
                'password' => $password, 'hospital_id' => $hospitalId, 'staff_id' => $staffId,
                'role' => 'doctor', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('staff')->insert([
                'id' => $staffId, 'hospital_id' => $hospitalId, 'user_id' => $userId, 'name' => $name,
                'email' => $email, 'role' => 'doctor', 'department' => $department,
                'specialization' => $department, 'consultation_duration_default' => 15,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // 3. Hospital admin
        if (! DB::table('users')->where('email', 'admin@healthway.medos.local')->exists()) {
            DB::table('users')->insert([
                'id' => Str::uuid()->toString(), 'name' => 'Healthway Admin',
                'email' => 'admin@healthway.medos.local', 'email_verified_at' => $now,
                'password' => $password, 'hospital_id' => $hospitalId, 'staff_id' => null,
                'role' => 'hospital_admin', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: restoration is not reversed automatically.
    }
};
