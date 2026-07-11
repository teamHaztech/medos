<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seed a few department-appropriate treatment packages for the main
     * City Care doctors. Idempotent — skips any package that already exists for
     * that doctor, and only adds them once.
     */
    public function up(): void
    {
        $hospitalId = DB::table('hospitals')->where('slug', 'city-care')->value('id')
            ?? DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id');

        if (! $hospitalId || ! \Illuminate\Support\Facades\Schema::hasTable('treatment_packages')) {
            return;
        }

        // doctor name (partial) => [ [name, price, description], ... ]
        $plan = [
            'Rajesh Kumar' => [ // General Medicine
                ['Full Body Health Checkup', 2500, 'Consultation + CBC, lipid profile, blood sugar & report review'],
                ['Diabetes Care Plan', 1800, 'Consultation + HbA1c test + personalised diet plan'],
                ['Fever & Infection Package', 800, 'Consultation + CBC + basic infection screening'],
            ],
            'Amit Patel' => [ // Cardiology
                ['Heart Health Screening', 3500, 'Consultation + ECG + 2D Echo + report review'],
                ['BP Management Plan', 1500, 'Consultation + ECG + blood pressure monitoring plan'],
            ],
            'Priya Sharma' => [ // Pediatrics
                ['Child Wellness & Vaccination', 1200, 'Consultation + growth check + vaccination guidance'],
                ['Newborn Care Package', 2000, 'Consultation + newborn screening + feeding & care plan'],
            ],
            'Neha Gupta' => [ // Gynecology
                ['Well Woman Checkup', 2800, 'Consultation + pelvic exam + Pap smear + report review'],
                ['Antenatal Care Package', 3500, 'Consultation + ultrasound + routine antenatal bloodwork'],
            ],
        ];

        $now = now();

        foreach ($plan as $doctorName => $packages) {
            $staff = DB::table('staff')
                ->where('hospital_id', $hospitalId)
                ->where('name', 'like', '%' . $doctorName . '%')
                ->first();

            if (! $staff) {
                continue;
            }

            foreach ($packages as [$name, $price, $description]) {
                $exists = DB::table('treatment_packages')
                    ->where('staff_id', $staff->id)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('treatment_packages')->insert([
                    'id'          => Str::uuid()->toString(),
                    'hospital_id' => $hospitalId,
                    'staff_id'    => $staff->id,
                    'name'        => $name,
                    'description' => $description,
                    'price'       => $price,
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op: seeded data is left in place.
    }
};
