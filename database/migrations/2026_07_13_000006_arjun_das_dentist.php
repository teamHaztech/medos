<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dr. Arjun Das (Dental dept) was seeded as a generic 'doctor' before the dentist
 * role existed. Make him a 'dentist' so he lands on the Dental module with a
 * dental-focused workspace. Idempotent (scoped by email).
 */
return new class extends Migration
{
    public function up(): void
    {
        $email = 'arjun.das@city-care.medos.local';

        if (DB::getSchemaBuilder()->hasTable('users')) {
            DB::table('users')->where('email', $email)->update(['role' => 'dentist', 'updated_at' => now()]);
        }
        if (DB::getSchemaBuilder()->hasTable('staff')) {
            DB::table('staff')->where('email', $email)->update(['role' => 'dentist', 'department' => 'Dental', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $email = 'arjun.das@city-care.medos.local';
        DB::table('users')->where('email', $email)->update(['role' => 'doctor']);
        DB::table('staff')->where('email', $email)->update(['role' => 'doctor']);
    }
};
