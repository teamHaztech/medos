<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffSeeder extends Seeder
{
    /**
     * Seed the staff and corresponding user records.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('staff')->truncate();
        DB::table('users')->truncate();
        Schema::enableForeignKeyConstraints();

        $now      = now();
        $password = Hash::make('password123');

        // ------------------------------------------------------------------
        // Schedule templates (varied per doctor)
        // ------------------------------------------------------------------
        $fullWeek = [
            'monday'    => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '17:00']],
            'tuesday'   => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '17:00']],
            'wednesday' => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '17:00']],
            'thursday'  => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '17:00']],
            'friday'    => [['start' => '09:00', 'end' => '13:00']],
            'saturday'  => [['start' => '10:00', 'end' => '14:00']],
        ];

        $morningOnly = [
            'monday'    => [['start' => '08:00', 'end' => '13:00']],
            'tuesday'   => [['start' => '08:00', 'end' => '13:00']],
            'wednesday' => [['start' => '08:00', 'end' => '13:00']],
            'thursday'  => [['start' => '08:00', 'end' => '13:00']],
            'friday'    => [['start' => '08:00', 'end' => '13:00']],
        ];

        $afternoonHeavy = [
            'monday'    => [['start' => '10:00', 'end' => '13:00'], ['start' => '15:00', 'end' => '20:00']],
            'tuesday'   => [['start' => '15:00', 'end' => '20:00']],
            'wednesday' => [['start' => '10:00', 'end' => '13:00'], ['start' => '15:00', 'end' => '20:00']],
            'thursday'  => [['start' => '15:00', 'end' => '20:00']],
            'friday'    => [['start' => '10:00', 'end' => '13:00']],
            'saturday'  => [['start' => '10:00', 'end' => '14:00']],
        ];

        $mwfOnly = [
            'monday'    => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']],
            'wednesday' => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']],
            'friday'    => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:00', 'end' => '18:00']],
        ];

        $ttsOnly = [
            'tuesday'  => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:30', 'end' => '18:00']],
            'thursday' => [['start' => '09:00', 'end' => '13:00'], ['start' => '14:30', 'end' => '18:00']],
            'saturday' => [['start' => '09:00', 'end' => '14:00']],
        ];

        $eveningShift = [
            'monday'    => [['start' => '14:00', 'end' => '21:00']],
            'tuesday'   => [['start' => '14:00', 'end' => '21:00']],
            'wednesday' => [['start' => '14:00', 'end' => '21:00']],
            'thursday'  => [['start' => '14:00', 'end' => '21:00']],
            'friday'    => [['start' => '14:00', 'end' => '19:00']],
        ];

        $splitShift = [
            'monday'    => [['start' => '08:00', 'end' => '11:00'], ['start' => '16:00', 'end' => '20:00']],
            'tuesday'   => [['start' => '08:00', 'end' => '11:00'], ['start' => '16:00', 'end' => '20:00']],
            'wednesday' => [['start' => '08:00', 'end' => '11:00']],
            'thursday'  => [['start' => '08:00', 'end' => '11:00'], ['start' => '16:00', 'end' => '20:00']],
            'friday'    => [['start' => '08:00', 'end' => '11:00']],
            'saturday'  => [['start' => '09:00', 'end' => '13:00']],
        ];

        // ------------------------------------------------------------------
        // Staff definitions: City Care Hospital
        // ------------------------------------------------------------------
        $cityStaff = [
            [
                'staff_id'     => SeedData::STAFF_RAJESH,
                'user_id'      => SeedData::USER_RAJESH,
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Rajesh Kumar',
                'email'        => 'admin@haztech.in',
                'phone'        => '+919845012345',
                'role'         => 'hospital_admin',
                'department'   => 'General Medicine',
                'specialization' => 'General Medicine',
                'qualification'  => 'KA-MED-2015-00421',
                'schedule'       => $fullWeek,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_PRIYA,
                'user_id'      => SeedData::USER_PRIYA,
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Priya Sharma',
                'email'        => 'priya@haztech.in',
                'phone'        => '+919845112345',
                'role'         => 'doctor',
                'department'   => 'Pediatrics',
                'specialization' => 'Pediatrics',
                'qualification'  => 'KA-MED-2017-01234',
                'schedule'       => $morningOnly,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_AMIT,
                'user_id'      => SeedData::USER_AMIT,
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Amit Patel',
                'email'        => 'amit@haztech.in',
                'phone'        => '+919845212345',
                'role'         => 'doctor',
                'department'   => 'Cardiology',
                'specialization' => 'Cardiology',
                'qualification'  => 'KA-MED-2012-00987',
                'schedule'       => $mwfOnly,
                'consultation_duration_default' => 20,
            ],
            [
                'staff_id'     => SeedData::STAFF_NEHA,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Neha Gupta',
                'email'        => 'neha.gupta@city-care.medos.local',
                'phone'        => '+919845312345',
                'role'         => 'doctor',
                'department'   => 'Gynecology',
                'specialization' => 'Gynecology',
                'qualification'  => 'KA-MED-2016-00556',
                'schedule'       => $ttsOnly,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_SURESH,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Suresh Reddy',
                'email'        => 'suresh.reddy@city-care.medos.local',
                'phone'        => '+919845412345',
                'role'         => 'doctor',
                'department'   => 'Orthopedics',
                'specialization' => 'Orthopedics',
                'qualification'  => 'KA-MED-2014-00823',
                'schedule'       => $afternoonHeavy,
                'consultation_duration_default' => 20,
            ],
            [
                'staff_id'     => SeedData::STAFF_ANJALI,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Anjali Mehta',
                'email'        => 'anjali.mehta@city-care.medos.local',
                'phone'        => '+919845512345',
                'role'         => 'doctor',
                'department'   => 'Dermatology',
                'specialization' => 'Dermatology',
                'qualification'  => 'KA-MED-2018-01567',
                'schedule'       => $fullWeek,
                'consultation_duration_default' => 10,
            ],
            [
                'staff_id'     => SeedData::STAFF_VIKRAM,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Vikram Singh',
                'email'        => 'vikram.singh@city-care.medos.local',
                'phone'        => '+919845612345',
                'role'         => 'doctor',
                'department'   => 'ENT',
                'specialization' => 'ENT',
                'qualification'  => 'KA-MED-2013-00345',
                'schedule'       => $eveningShift,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_MEERA,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Meera Nair',
                'email'        => 'meera.nair@city-care.medos.local',
                'phone'        => '+919845712345',
                'role'         => 'doctor',
                'department'   => 'General Medicine',
                'specialization' => 'General Medicine',
                'qualification'  => 'KA-MED-2019-02001',
                'schedule'       => $morningOnly,
                'consultation_duration_default' => 12,
            ],
            [
                'staff_id'     => SeedData::STAFF_ARJUN,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::CITY_CARE_ID,
                'name'         => 'Dr. Arjun Das',
                'email'        => 'arjun.das@city-care.medos.local',
                'phone'        => '+919845812345',
                'role'         => 'doctor',
                'department'   => 'Dental',
                'specialization' => 'Dental',
                'qualification'  => 'KA-DEN-2016-00789',
                'schedule'       => $mwfOnly,
                'consultation_duration_default' => 20,
            ],
        ];

        // ------------------------------------------------------------------
        // Staff definitions: Gulf Medical Center
        // ------------------------------------------------------------------
        $gulfStaff = [
            [
                'staff_id'     => SeedData::STAFF_AHMED,
                'user_id'      => SeedData::USER_AHMED,
                'hospital_id'  => SeedData::GULF_MEDICAL_ID,
                'name'         => 'Dr. Ahmed Al-Rashid',
                'email'        => 'ahmed.alrashid@gulf-medical.medos.local',
                'phone'        => '+971501234567',
                'role'         => 'hospital_admin',
                'department'   => 'General Medicine',
                'specialization' => 'General Medicine',
                'qualification'  => 'DHA-MED-2012-04521',
                'schedule'       => $fullWeek,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_FATIMAH,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::GULF_MEDICAL_ID,
                'name'         => 'Dr. Fatimah Hassan',
                'email'        => 'fatimah.hassan@gulf-medical.medos.local',
                'phone'        => '+971502345678',
                'role'         => 'doctor',
                'department'   => 'Pediatrics',
                'specialization' => 'Pediatrics',
                'qualification'  => 'DHA-MED-2015-05678',
                'schedule'       => $morningOnly,
                'consultation_duration_default' => 15,
            ],
            [
                'staff_id'     => SeedData::STAFF_OMAR,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::GULF_MEDICAL_ID,
                'name'         => 'Dr. Omar Khalil',
                'email'        => 'omar.khalil@gulf-medical.medos.local',
                'phone'        => '+971503456789',
                'role'         => 'doctor',
                'department'   => 'Cardiology',
                'specialization' => 'Cardiology',
                'qualification'  => 'DHA-MED-2013-03456',
                'schedule'       => $mwfOnly,
                'consultation_duration_default' => 20,
            ],
            [
                'staff_id'     => SeedData::STAFF_LAYLA,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::GULF_MEDICAL_ID,
                'name'         => 'Dr. Layla Abbas',
                'email'        => 'layla.abbas@gulf-medical.medos.local',
                'phone'        => '+971504567890',
                'role'         => 'doctor',
                'department'   => 'Dermatology',
                'specialization' => 'Dermatology',
                'qualification'  => 'DHA-MED-2017-06789',
                'schedule'       => $ttsOnly,
                'consultation_duration_default' => 10,
            ],
            [
                'staff_id'     => SeedData::STAFF_HASSAN,
                'user_id'      => Str::uuid()->toString(),
                'hospital_id'  => SeedData::GULF_MEDICAL_ID,
                'name'         => 'Dr. Hassan Mahmoud',
                'email'        => 'hassan.mahmoud@gulf-medical.medos.local',
                'phone'        => '+971505678901',
                'role'         => 'doctor',
                'department'   => 'Orthopedics',
                'specialization' => 'Orthopedics',
                'qualification'  => 'DHA-MED-2014-04567',
                'schedule'       => $splitShift,
                'consultation_duration_default' => 20,
            ],
        ];

        $allStaff = array_merge($cityStaff, $gulfStaff);

        // Insert super admin user first
        DB::table('users')->insert([
            'id'                => Str::uuid()->toString(),
            'name'              => 'Haztech Admin',
            'email'             => 'superadmin@haztech.in',
            'email_verified_at' => $now,
            'password'          => $password,
            // Super Admin is platform-level — not pinned to any hospital, so no
            // "operating here" marker and settings use the hospital picker instead.
            'hospital_id'       => null,
            'role'              => 'super_admin',
            'staff_id'          => null,
            'phone'             => null,
            'is_active'         => true,
            'remember_token'    => null,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // Insert users (so user_id FK is valid when staff rows reference them)
        foreach ($allStaff as $s) {
            DB::table('users')->insert([
                'id'                => $s['user_id'],
                'name'              => $s['name'],
                'email'             => $s['email'],
                'email_verified_at' => $now,
                'password'          => $password,
                'hospital_id'       => $s['hospital_id'],
                'role'              => $s['role'],
                'staff_id'          => $s['staff_id'],
                'phone'             => $s['phone'],
                'is_active'         => true,
                'remember_token'    => null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        // Insert staff records
        foreach ($allStaff as $s) {
            DB::table('staff')->insert([
                'id'                            => $s['staff_id'],
                'hospital_id'                   => $s['hospital_id'],
                'user_id'                       => $s['user_id'],
                'name'                          => $s['name'],
                'email'                         => $s['email'],
                'phone'                         => $s['phone'],
                'role'                          => $s['role'],
                'department'                    => $s['department'],
                'specialization'                => $s['specialization'],
                'qualification'                 => $s['qualification'],
                'schedule'                      => json_encode($s['schedule']),
                'consultation_duration_default' => $s['consultation_duration_default'],
                'performance_metrics'           => null,
                'is_active'                     => true,
                'created_at'                    => $now,
                'updated_at'                    => $now,
            ]);
        }
    }
}
