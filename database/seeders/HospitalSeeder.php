<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HospitalSeeder extends Seeder
{
    /**
     * Seed the hospitals table.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('hospitals')->truncate();
        Schema::enableForeignKeyConstraints();

        $now = now();

        DB::table('hospitals')->insert([
            [
                'id'              => SeedData::CITY_CARE_ID,
                'name'            => 'City Care Hospital',
                'slug'            => 'city-care',
                'address'         => '42, MG Road, Koramangala',
                'city'            => 'Bangalore',
                'state'           => 'Karnataka',
                'country'         => 'IN',
                'phone'           => '+918041234567',
                'email'           => 'admin@citycare.medos.local',
                'logo_path'       => null,
                'config'          => json_encode([
                    'departments'     => [
                        'General Medicine',
                        'Pediatrics',
                        'Cardiology',
                        'Orthopedics',
                        'Gynecology',
                        'Dermatology',
                        'ENT',
                        'Dental',
                    ],
                    'operating_hours' => [
                        'open'  => '08:00',
                        'close' => '21:00',
                    ],
                    'consultation_fees' => [
                        'General Medicine' => 500,
                        'Pediatrics'       => 600,
                        'Cardiology'       => 1000,
                        'Orthopedics'      => 800,
                        'Gynecology'       => 700,
                        'Dermatology'      => 500,
                        'ENT'              => 600,
                        'Dental'           => 500,
                    ],
                    'sms_enabled'   => true,
                    'whatsapp_bot'  => true,
                    'queue_display' => true,
                ]),
                'modules_enabled' => json_encode([
                    'ai_receptionist',
                    'whatsapp',
                    'triage',
                    'scheduling',
                    'queue',
                    'insurance',
                    'billing',
                    'analytics',
                    'engagement',
                ]),
                'subscription_plan'   => 'premium',
                'subscription_status' => 'active',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'              => SeedData::GULF_MEDICAL_ID,
                'name'            => 'Gulf Medical Center',
                'slug'            => 'gulf-medical',
                'address'         => 'Al Barsha 1, Sheikh Zayed Road',
                'city'            => 'Dubai',
                'state'           => 'Dubai',
                'country'         => 'AE',
                'phone'           => '+97143215678',
                'email'           => 'admin@gulfmedical.medos.local',
                'logo_path'       => null,
                'config'          => json_encode([
                    'departments'     => [
                        'General Medicine',
                        'Pediatrics',
                        'Cardiology',
                        'Orthopedics',
                        'Dermatology',
                        'Internal Medicine',
                    ],
                    'operating_hours' => [
                        'open'  => '08:00',
                        'close' => '21:00',
                    ],
                    'consultation_fees' => [
                        'General Medicine'  => 200,
                        'Pediatrics'        => 250,
                        'Cardiology'        => 400,
                        'Orthopedics'       => 350,
                        'Dermatology'       => 200,
                        'Internal Medicine' => 300,
                    ],
                    'sms_enabled'   => true,
                    'whatsapp_bot'  => true,
                    'queue_display' => true,
                ]),
                'modules_enabled' => json_encode([
                    'ai_receptionist',
                    'whatsapp',
                    'triage',
                    'scheduling',
                    'queue',
                    'insurance',
                    'billing',
                    'analytics',
                    'engagement',
                ]),
                'subscription_plan'   => 'standard',
                'subscription_status' => 'active',
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
