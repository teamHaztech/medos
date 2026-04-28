<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BillSeeder extends Seeder
{
    /**
     * Seed the bills table for completed encounters.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('bills')->truncate();
        Schema::enableForeignKeyConstraints();

        $now = Carbon::now();

        // Fetch completed encounters
        $completedEncounters = DB::table('encounters')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->where('status', 'completed')
            ->get();

        // Specialty -> consultation fee mapping
        $consultationFees = [
            'General Medicine' => 500,
            'Pediatrics'       => 600,
            'Cardiology'       => 1000,
            'Orthopedics'      => 800,
            'Gynecology'       => 700,
            'Dermatology'      => 500,
            'ENT'              => 600,
            'Dental'           => 500,
        ];

        // Staff specialization lookup
        $staffSpecialties = DB::table('staff')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->pluck('specialization', 'id')
            ->toArray();

        // Additional charge templates
        $labCharges = [
            ['description' => 'Complete Blood Count (CBC)', 'unit_price' => 350],
            ['description' => 'Blood Sugar Fasting', 'unit_price' => 150],
            ['description' => 'HbA1c Test', 'unit_price' => 600],
            ['description' => 'Thyroid Profile (TSH, T3, T4)', 'unit_price' => 800],
            ['description' => 'Lipid Profile', 'unit_price' => 500],
            ['description' => 'Urine Routine', 'unit_price' => 200],
            ['description' => 'ECG', 'unit_price' => 300],
            ['description' => 'X-Ray', 'unit_price' => 400],
            ['description' => 'USG Pelvis', 'unit_price' => 1200],
            ['description' => 'Troponin Test', 'unit_price' => 900],
        ];

        $pharmacyCharges = [
            ['description' => 'Paracetamol 650mg x 10', 'unit_price' => 30],
            ['description' => 'Amoxicillin 500mg x 15', 'unit_price' => 120],
            ['description' => 'Azithromycin 500mg x 3', 'unit_price' => 95],
            ['description' => 'Cetirizine 10mg x 10', 'unit_price' => 25],
            ['description' => 'Ibuprofen 400mg x 10', 'unit_price' => 40],
            ['description' => 'Metformin 500mg x 30', 'unit_price' => 65],
            ['description' => 'Amlodipine 5mg x 30', 'unit_price' => 80],
            ['description' => 'Calamine Lotion', 'unit_price' => 85],
            ['description' => 'Doxycycline 100mg x 14', 'unit_price' => 110],
            ['description' => 'ORS Sachets x 10', 'unit_price' => 50],
        ];

        $billNumber = 1;

        foreach ($completedEncounters as $index => $encounter) {
            $specialty  = $staffSpecialties[$encounter->doctor_id] ?? 'General Medicine';
            $consultFee = $consultationFees[$specialty] ?? 500;

            // Build line items
            $lineItems = [
                [
                    'description' => "Consultation - {$specialty}",
                    'quantity'    => 1,
                    'unit_price'  => $consultFee,
                    'total'       => $consultFee,
                ],
            ];

            // Add lab charges for some encounters (every other one)
            if ($index % 2 === 0) {
                $lab = $labCharges[$index % count($labCharges)];
                $lineItems[] = [
                    'description' => $lab['description'],
                    'quantity'    => 1,
                    'unit_price'  => $lab['unit_price'],
                    'total'       => $lab['unit_price'],
                ];
            }

            // Add pharmacy charges for most encounters
            if ($index % 3 !== 2) {
                $pharm = $pharmacyCharges[$index % count($pharmacyCharges)];
                $lineItems[] = [
                    'description' => $pharm['description'],
                    'quantity'    => 1,
                    'unit_price'  => $pharm['unit_price'],
                    'total'       => $pharm['unit_price'],
                ];
            }

            // Calculate totals
            $subtotal = array_sum(array_column($lineItems, 'total'));

            // Check if patient has insurance
            $patient         = DB::table('patients')->where('id', $encounter->patient_id)->first();
            $hasInsurance    = ! empty($patient->insurance_details);
            $insuranceCovered = $hasInsurance ? round($subtotal * 0.7, 2) : 0;
            $totalAmount     = $subtotal;
            $patientPayable  = round($subtotal - $insuranceCovered, 2);

            // Payment status variation
            $paymentStatuses = ['paid', 'paid', 'paid', 'pending', 'partial'];
            $paymentStatus   = $paymentStatuses[$index % count($paymentStatuses)];

            $paymentMethod = match ($paymentStatus) {
                'paid'    => ['cash', 'upi', 'card'][$index % 3],
                'partial' => 'cash',
                default   => null,
            };

            $createdAt = Carbon::parse($encounter->created_at);
            $billNum   = 'CC-' . $createdAt->format('Ymd') . '-' . str_pad($billNumber, 4, '0', STR_PAD_LEFT);

            DB::table('bills')->insert([
                'id'                => Str::uuid()->toString(),
                'hospital_id'       => SeedData::CITY_CARE_ID,
                'encounter_id'      => $encounter->id,
                'patient_id'        => $encounter->patient_id,
                'bill_number'       => $billNum,
                'line_items'        => json_encode($lineItems),
                'subtotal'          => $subtotal,
                'tax_amount'        => 0,
                'discount_amount'   => 0,
                'total_amount'      => $totalAmount,
                'insurance_covered' => $insuranceCovered,
                'patient_payable'   => $patientPayable,
                'payment_status'    => $paymentStatus,
                'payment_method'    => $paymentMethod,
                'payment_reference' => $paymentMethod === 'upi' ? 'UPI-' . strtoupper(Str::random(12)) : null,
                'paid_at'           => $paymentStatus === 'paid' ? $createdAt->copy()->addMinutes(15) : null,
                'created_at'        => $createdAt,
                'updated_at'        => $now,
            ]);

            $billNumber++;
        }
    }
}
