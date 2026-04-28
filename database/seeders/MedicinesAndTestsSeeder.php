<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MedicinesAndTestsSeeder extends Seeder
{
    /**
     * Seed the medicines and available_tests tables with comprehensive global data.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Disable FK constraints, truncate, re-enable
        Schema::disableForeignKeyConstraints();
        DB::table('medicines')->truncate();
        DB::table('available_tests')->truncate();
        Schema::enableForeignKeyConstraints();

        // ──────────────────────────────────────────────
        //  MEDICINES
        // ──────────────────────────────────────────────

        $medicines = $this->getMedicines();

        $medicineRows = array_map(function (array $m) use ($now) {
            return [
                'id'                => Str::uuid()->toString(),
                'hospital_id'       => null,
                'name'              => $m['name'],
                'generic_name'      => $m['generic_name'],
                'category'          => $m['category'],
                'default_dosage'    => $m['default_dosage'],
                'default_frequency' => $m['default_frequency'],
                'default_duration'  => $m['default_duration'],
                'default_timing'    => $m['default_timing'],
                'form'              => $m['form'],
                'is_global'         => true,
                'is_active'         => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }, $medicines);

        // Insert in chunks to avoid packet-size limits
        foreach (array_chunk($medicineRows, 50) as $chunk) {
            DB::table('medicines')->insert($chunk);
        }

        $this->command->info('Seeded ' . count($medicineRows) . ' medicines.');

        // ──────────────────────────────────────────────
        //  AVAILABLE TESTS
        // ──────────────────────────────────────────────

        $tests = $this->getAvailableTests();

        $testRows = array_map(function (array $t) use ($now) {
            return [
                'id'              => Str::uuid()->toString(),
                'hospital_id'     => null,
                'name'            => $t['name'],
                'code'            => $t['code'],
                'type'            => $t['type'],
                'category'        => $t['category'],
                'price'           => $t['price'],
                'turnaround_time' => $t['turnaround_time'],
                'instructions'    => $t['instructions'] ?? null,
                'is_global'       => true,
                'is_active'       => true,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }, $tests);

        foreach (array_chunk($testRows, 50) as $chunk) {
            DB::table('available_tests')->insert($chunk);
        }

        $this->command->info('Seeded ' . count($testRows) . ' available tests.');
    }

    // ================================================================
    //  Medicine definitions
    // ================================================================

    private function getMedicines(): array
    {
        return [
            // ── Antibiotics ────────────────────────────
            ['name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin', 'category' => 'Antibiotics', 'default_dosage' => '500mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Azithromycin 500mg', 'generic_name' => 'Azithromycin', 'category' => 'Antibiotics', 'default_dosage' => '500mg', 'default_frequency' => 'OD', 'default_duration' => '3 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Ciprofloxacin 500mg', 'generic_name' => 'Ciprofloxacin', 'category' => 'Antibiotics', 'default_dosage' => '500mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Metronidazole 400mg', 'generic_name' => 'Metronidazole', 'category' => 'Antibiotics', 'default_dosage' => '400mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Doxycycline 100mg', 'generic_name' => 'Doxycycline', 'category' => 'Antibiotics', 'default_dosage' => '100mg', 'default_frequency' => 'BD', 'default_duration' => '7 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Cefixime 200mg', 'generic_name' => 'Cefixime', 'category' => 'Antibiotics', 'default_dosage' => '200mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Augmentin 625mg', 'generic_name' => 'Amoxicillin + Clavulanic Acid', 'category' => 'Antibiotics', 'default_dosage' => '625mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Levofloxacin 500mg', 'generic_name' => 'Levofloxacin', 'category' => 'Antibiotics', 'default_dosage' => '500mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Cephalexin 500mg', 'generic_name' => 'Cephalexin', 'category' => 'Antibiotics', 'default_dosage' => '500mg', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Norfloxacin 400mg', 'generic_name' => 'Norfloxacin', 'category' => 'Antibiotics', 'default_dosage' => '400mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Ofloxacin 200mg', 'generic_name' => 'Ofloxacin', 'category' => 'Antibiotics', 'default_dosage' => '200mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Cotrimoxazole 960mg', 'generic_name' => 'Sulfamethoxazole + Trimethoprim', 'category' => 'Antibiotics', 'default_dosage' => '960mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Clindamycin 300mg', 'generic_name' => 'Clindamycin', 'category' => 'Antibiotics', 'default_dosage' => '300mg', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Nitrofurantoin 100mg', 'generic_name' => 'Nitrofurantoin', 'category' => 'Antibiotics', 'default_dosage' => '100mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'capsule'],

            // ── Painkillers / Anti-inflammatory ────────
            ['name' => 'Paracetamol 500mg', 'generic_name' => 'Paracetamol', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '500mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Paracetamol 650mg', 'generic_name' => 'Paracetamol', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '650mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Ibuprofen 400mg', 'generic_name' => 'Ibuprofen', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '400mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Diclofenac 50mg', 'generic_name' => 'Diclofenac Sodium', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '50mg', 'default_frequency' => 'BD', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Aceclofenac 100mg', 'generic_name' => 'Aceclofenac', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '100mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Naproxen 500mg', 'generic_name' => 'Naproxen', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '500mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Tramadol 50mg', 'generic_name' => 'Tramadol Hydrochloride', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '50mg', 'default_frequency' => 'BD', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Mefenamic Acid 500mg', 'generic_name' => 'Mefenamic Acid', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '500mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Piroxicam 20mg', 'generic_name' => 'Piroxicam', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '20mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Etoricoxib 90mg', 'generic_name' => 'Etoricoxib', 'category' => 'Painkillers/Anti-inflammatory', 'default_dosage' => '90mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],

            // ── Antacids / GI ──────────────────────────
            ['name' => 'Pantoprazole 40mg', 'generic_name' => 'Pantoprazole', 'category' => 'Antacids/GI', 'default_dosage' => '40mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Omeprazole 20mg', 'generic_name' => 'Omeprazole', 'category' => 'Antacids/GI', 'default_dosage' => '20mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'Before food', 'form' => 'capsule'],
            ['name' => 'Rabeprazole 20mg', 'generic_name' => 'Rabeprazole', 'category' => 'Antacids/GI', 'default_dosage' => '20mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Ranitidine 150mg', 'generic_name' => 'Ranitidine', 'category' => 'Antacids/GI', 'default_dosage' => '150mg', 'default_frequency' => 'BD', 'default_duration' => '7 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Domperidone 10mg', 'generic_name' => 'Domperidone', 'category' => 'Antacids/GI', 'default_dosage' => '10mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Ondansetron 4mg', 'generic_name' => 'Ondansetron', 'category' => 'Antacids/GI', 'default_dosage' => '4mg', 'default_frequency' => 'BD', 'default_duration' => '3 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Metoclopramide 10mg', 'generic_name' => 'Metoclopramide', 'category' => 'Antacids/GI', 'default_dosage' => '10mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Sucralfate 1g', 'generic_name' => 'Sucralfate', 'category' => 'Antacids/GI', 'default_dosage' => '1g', 'default_frequency' => 'BD', 'default_duration' => '14 days', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Antacid Gel', 'generic_name' => 'Aluminium Hydroxide + Magnesium Hydroxide', 'category' => 'Antacids/GI', 'default_dosage' => '10ml', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'After food', 'form' => 'syrup'],
            ['name' => 'ORS Sachet', 'generic_name' => 'Oral Rehydration Salts', 'category' => 'Antacids/GI', 'default_dosage' => '1 sachet in 1L water', 'default_frequency' => 'SOS', 'default_duration' => '3 days', 'default_timing' => 'Any time', 'form' => 'sachet'],
            ['name' => 'Loperamide 2mg', 'generic_name' => 'Loperamide', 'category' => 'Antacids/GI', 'default_dosage' => '2mg', 'default_frequency' => 'SOS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Bisacodyl 5mg', 'generic_name' => 'Bisacodyl', 'category' => 'Antacids/GI', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => '3 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Lactulose Syrup', 'generic_name' => 'Lactulose', 'category' => 'Antacids/GI', 'default_dosage' => '15ml', 'default_frequency' => 'BD', 'default_duration' => '7 days', 'default_timing' => 'Empty stomach', 'form' => 'syrup'],
            ['name' => 'Drotaverine 40mg', 'generic_name' => 'Drotaverine', 'category' => 'Antacids/GI', 'default_dosage' => '40mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],

            // ── Antihistamines / Anti-allergy ──────────
            ['name' => 'Cetirizine 10mg', 'generic_name' => 'Cetirizine', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Levocetirizine 5mg', 'generic_name' => 'Levocetirizine', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Fexofenadine 120mg', 'generic_name' => 'Fexofenadine', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '120mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Chlorpheniramine 4mg', 'generic_name' => 'Chlorpheniramine Maleate', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '4mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Montelukast 10mg', 'generic_name' => 'Montelukast', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Hydroxyzine 25mg', 'generic_name' => 'Hydroxyzine', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '25mg', 'default_frequency' => 'BD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Loratadine 10mg', 'generic_name' => 'Loratadine', 'category' => 'Antihistamines/Anti-allergy', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'Any time', 'form' => 'tablet'],

            // ── Antidiabetic ──────────────────────────
            ['name' => 'Metformin 500mg', 'generic_name' => 'Metformin Hydrochloride', 'category' => 'Antidiabetic', 'default_dosage' => '500mg', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Metformin 1000mg', 'generic_name' => 'Metformin Hydrochloride', 'category' => 'Antidiabetic', 'default_dosage' => '1000mg', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Glimepiride 1mg', 'generic_name' => 'Glimepiride', 'category' => 'Antidiabetic', 'default_dosage' => '1mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Glimepiride 2mg', 'generic_name' => 'Glimepiride', 'category' => 'Antidiabetic', 'default_dosage' => '2mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Gliclazide 80mg', 'generic_name' => 'Gliclazide', 'category' => 'Antidiabetic', 'default_dosage' => '80mg', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Sitagliptin 100mg', 'generic_name' => 'Sitagliptin', 'category' => 'Antidiabetic', 'default_dosage' => '100mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Voglibose 0.3mg', 'generic_name' => 'Voglibose', 'category' => 'Antidiabetic', 'default_dosage' => '0.3mg', 'default_frequency' => 'TDS', 'default_duration' => 'Ongoing', 'default_timing' => 'Before food', 'form' => 'tablet'],
            ['name' => 'Pioglitazone 15mg', 'generic_name' => 'Pioglitazone', 'category' => 'Antidiabetic', 'default_dosage' => '15mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Empagliflozin 25mg', 'generic_name' => 'Empagliflozin', 'category' => 'Antidiabetic', 'default_dosage' => '25mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Insulin (Regular)', 'generic_name' => 'Insulin Human Regular', 'category' => 'Antidiabetic', 'default_dosage' => 'As directed', 'default_frequency' => 'TDS', 'default_duration' => 'Ongoing', 'default_timing' => 'Before food', 'form' => 'injection'],
            ['name' => 'Insulin (Glargine)', 'generic_name' => 'Insulin Glargine', 'category' => 'Antidiabetic', 'default_dosage' => 'As directed', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'injection'],

            // ── Cardiovascular ─────────────────────────
            ['name' => 'Amlodipine 5mg', 'generic_name' => 'Amlodipine Besylate', 'category' => 'Cardiovascular', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Amlodipine 10mg', 'generic_name' => 'Amlodipine Besylate', 'category' => 'Cardiovascular', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Telmisartan 40mg', 'generic_name' => 'Telmisartan', 'category' => 'Cardiovascular', 'default_dosage' => '40mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Losartan 50mg', 'generic_name' => 'Losartan Potassium', 'category' => 'Cardiovascular', 'default_dosage' => '50mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Atenolol 50mg', 'generic_name' => 'Atenolol', 'category' => 'Cardiovascular', 'default_dosage' => '50mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Metoprolol 50mg', 'generic_name' => 'Metoprolol Succinate', 'category' => 'Cardiovascular', 'default_dosage' => '50mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Ramipril 5mg', 'generic_name' => 'Ramipril', 'category' => 'Cardiovascular', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'capsule'],
            ['name' => 'Enalapril 5mg', 'generic_name' => 'Enalapril Maleate', 'category' => 'Cardiovascular', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Hydrochlorothiazide 12.5mg', 'generic_name' => 'Hydrochlorothiazide', 'category' => 'Cardiovascular', 'default_dosage' => '12.5mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Empty stomach', 'form' => 'tablet'],
            ['name' => 'Aspirin 75mg', 'generic_name' => 'Aspirin', 'category' => 'Cardiovascular', 'default_dosage' => '75mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Clopidogrel 75mg', 'generic_name' => 'Clopidogrel', 'category' => 'Cardiovascular', 'default_dosage' => '75mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Atorvastatin 10mg', 'generic_name' => 'Atorvastatin Calcium', 'category' => 'Cardiovascular', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Atorvastatin 20mg', 'generic_name' => 'Atorvastatin Calcium', 'category' => 'Cardiovascular', 'default_dosage' => '20mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Rosuvastatin 10mg', 'generic_name' => 'Rosuvastatin Calcium', 'category' => 'Cardiovascular', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Nitroglycerin 0.5mg', 'generic_name' => 'Nitroglycerin', 'category' => 'Cardiovascular', 'default_dosage' => '0.5mg', 'default_frequency' => 'SOS', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],

            // ── Respiratory ────────────────────────────
            ['name' => 'Salbutamol Inhaler 100mcg', 'generic_name' => 'Salbutamol', 'category' => 'Respiratory', 'default_dosage' => '100mcg/puff, 2 puffs', 'default_frequency' => 'SOS', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'inhaler'],
            ['name' => 'Budesonide Inhaler 200mcg', 'generic_name' => 'Budesonide', 'category' => 'Respiratory', 'default_dosage' => '200mcg/puff, 2 puffs', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'inhaler'],
            ['name' => 'Montelukast 10mg (Respiratory)', 'generic_name' => 'Montelukast', 'category' => 'Respiratory', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Dextromethorphan 15mg', 'generic_name' => 'Dextromethorphan', 'category' => 'Respiratory', 'default_dosage' => '15mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'syrup'],
            ['name' => 'Ambroxol 30mg', 'generic_name' => 'Ambroxol Hydrochloride', 'category' => 'Respiratory', 'default_dosage' => '30mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Guaifenesin 100mg', 'generic_name' => 'Guaifenesin', 'category' => 'Respiratory', 'default_dosage' => '100mg', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'syrup'],
            ['name' => 'Theophylline 300mg', 'generic_name' => 'Theophylline', 'category' => 'Respiratory', 'default_dosage' => '300mg', 'default_frequency' => 'BD', 'default_duration' => '7 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Levosalbutamol Nebulizer', 'generic_name' => 'Levosalbutamol', 'category' => 'Respiratory', 'default_dosage' => '1.25mg/3ml', 'default_frequency' => 'TDS', 'default_duration' => '5 days', 'default_timing' => 'Any time', 'form' => 'injection'],
            ['name' => 'Codeine 10mg', 'generic_name' => 'Codeine Phosphate', 'category' => 'Respiratory', 'default_dosage' => '10mg', 'default_frequency' => 'TDS', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'syrup'],

            // ── Vitamins / Supplements ─────────────────
            ['name' => 'Vitamin D3 60000IU', 'generic_name' => 'Cholecalciferol', 'category' => 'Vitamins/Supplements', 'default_dosage' => '60000IU', 'default_frequency' => 'Once a week', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'sachet'],
            ['name' => 'Vitamin B12 1500mcg', 'generic_name' => 'Methylcobalamin', 'category' => 'Vitamins/Supplements', 'default_dosage' => '1500mcg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Iron (Ferrous Sulfate) 200mg', 'generic_name' => 'Ferrous Sulfate', 'category' => 'Vitamins/Supplements', 'default_dosage' => '200mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Empty stomach', 'form' => 'tablet'],
            ['name' => 'Calcium + Vitamin D', 'generic_name' => 'Calcium Carbonate + Cholecalciferol', 'category' => 'Vitamins/Supplements', 'default_dosage' => '500mg + 250IU', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Multivitamin Tablet', 'generic_name' => 'Multivitamin', 'category' => 'Vitamins/Supplements', 'default_dosage' => '1 tablet', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Folic Acid 5mg', 'generic_name' => 'Folic Acid', 'category' => 'Vitamins/Supplements', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'tablet'],
            ['name' => 'Zinc 20mg', 'generic_name' => 'Zinc Sulfate', 'category' => 'Vitamins/Supplements', 'default_dosage' => '20mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Vitamin C 500mg', 'generic_name' => 'Ascorbic Acid', 'category' => 'Vitamins/Supplements', 'default_dosage' => '500mg', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'After food', 'form' => 'tablet'],

            // ── Dermatology ────────────────────────────
            ['name' => 'Betamethasone Cream', 'generic_name' => 'Betamethasone Valerate', 'category' => 'Dermatology', 'default_dosage' => 'Apply thin layer', 'default_frequency' => 'BD', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'cream'],
            ['name' => 'Clotrimazole Cream', 'generic_name' => 'Clotrimazole', 'category' => 'Dermatology', 'default_dosage' => 'Apply thin layer', 'default_frequency' => 'BD', 'default_duration' => '14 days', 'default_timing' => 'Any time', 'form' => 'cream'],
            ['name' => 'Mupirocin Ointment', 'generic_name' => 'Mupirocin', 'category' => 'Dermatology', 'default_dosage' => 'Apply thin layer', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'ointment'],
            ['name' => 'Permethrin 5% Cream', 'generic_name' => 'Permethrin', 'category' => 'Dermatology', 'default_dosage' => 'Apply all over body', 'default_frequency' => 'Once', 'default_duration' => '1 day', 'default_timing' => 'At bedtime', 'form' => 'cream'],
            ['name' => 'Benzoyl Peroxide 2.5% Gel', 'generic_name' => 'Benzoyl Peroxide', 'category' => 'Dermatology', 'default_dosage' => 'Apply thin layer', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'At bedtime', 'form' => 'gel'],
            ['name' => 'Adapalene Gel 0.1%', 'generic_name' => 'Adapalene', 'category' => 'Dermatology', 'default_dosage' => 'Apply thin layer', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'At bedtime', 'form' => 'gel'],
            ['name' => 'Calamine Lotion', 'generic_name' => 'Calamine + Zinc Oxide', 'category' => 'Dermatology', 'default_dosage' => 'Apply as needed', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'lotion'],
            ['name' => 'Antifungal Powder', 'generic_name' => 'Clotrimazole Dusting Powder', 'category' => 'Dermatology', 'default_dosage' => 'Dust on affected area', 'default_frequency' => 'BD', 'default_duration' => '14 days', 'default_timing' => 'Any time', 'form' => 'powder'],

            // ── Eye / Ear / Nose ───────────────────────
            ['name' => 'Ciprofloxacin Eye Drops', 'generic_name' => 'Ciprofloxacin Ophthalmic', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '1-2 drops', 'default_frequency' => 'QID', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Moxifloxacin Eye Drops', 'generic_name' => 'Moxifloxacin Ophthalmic', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '1 drop', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Tobramycin Eye Drops', 'generic_name' => 'Tobramycin Ophthalmic', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '1-2 drops', 'default_frequency' => 'QID', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Olopatadine Eye Drops', 'generic_name' => 'Olopatadine Hydrochloride', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '1 drop', 'default_frequency' => 'BD', 'default_duration' => '14 days', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Artificial Tears', 'generic_name' => 'Carboxymethylcellulose', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '1-2 drops', 'default_frequency' => 'QID', 'default_duration' => 'Ongoing', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Otosporin Ear Drops', 'generic_name' => 'Neomycin + Polymyxin B + Hydrocortisone', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '2-3 drops', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'drops'],
            ['name' => 'Nasal Saline Spray', 'generic_name' => 'Sodium Chloride 0.65%', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '2 sprays each nostril', 'default_frequency' => 'TDS', 'default_duration' => '7 days', 'default_timing' => 'Any time', 'form' => 'spray'],
            ['name' => 'Fluticasone Nasal Spray', 'generic_name' => 'Fluticasone Propionate', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '2 sprays each nostril', 'default_frequency' => 'OD', 'default_duration' => '14 days', 'default_timing' => 'Any time', 'form' => 'spray'],
            ['name' => 'Xylometazoline Nasal Drops', 'generic_name' => 'Xylometazoline', 'category' => 'Eye/Ear/Nose', 'default_dosage' => '2-3 drops each nostril', 'default_frequency' => 'BD', 'default_duration' => '3 days', 'default_timing' => 'Any time', 'form' => 'drops'],

            // ── Psychiatric / Neuro ────────────────────
            ['name' => 'Alprazolam 0.25mg', 'generic_name' => 'Alprazolam', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '0.25mg', 'default_frequency' => 'OD', 'default_duration' => '7 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Escitalopram 10mg', 'generic_name' => 'Escitalopram Oxalate', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '10mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Sertraline 50mg', 'generic_name' => 'Sertraline Hydrochloride', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '50mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Amitriptyline 25mg', 'generic_name' => 'Amitriptyline Hydrochloride', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '25mg', 'default_frequency' => 'OD', 'default_duration' => 'Ongoing', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
            ['name' => 'Gabapentin 300mg', 'generic_name' => 'Gabapentin', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '300mg', 'default_frequency' => 'TDS', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Pregabalin 75mg', 'generic_name' => 'Pregabalin', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '75mg', 'default_frequency' => 'BD', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'capsule'],
            ['name' => 'Zolpidem 5mg', 'generic_name' => 'Zolpidem Tartrate', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => '7 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],

            // ── Others ─────────────────────────────────
            ['name' => 'Prednisolone 5mg', 'generic_name' => 'Prednisolone', 'category' => 'Corticosteroids', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Dexamethasone 4mg', 'generic_name' => 'Dexamethasone', 'category' => 'Corticosteroids', 'default_dosage' => '4mg', 'default_frequency' => 'OD', 'default_duration' => '3 days', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Methotrexate 7.5mg', 'generic_name' => 'Methotrexate', 'category' => 'Immunosuppressant', 'default_dosage' => '7.5mg', 'default_frequency' => 'Once a week', 'default_duration' => 'Ongoing', 'default_timing' => 'After food', 'form' => 'tablet'],
            ['name' => 'Rabies Vaccine', 'generic_name' => 'Rabies Vaccine (Inactivated)', 'category' => 'Vaccines', 'default_dosage' => '1ml IM', 'default_frequency' => 'As per schedule', 'default_duration' => 'As per schedule', 'default_timing' => 'Any time', 'form' => 'injection'],
            ['name' => 'Tetanus Toxoid', 'generic_name' => 'Tetanus Toxoid Adsorbed', 'category' => 'Vaccines', 'default_dosage' => '0.5ml IM', 'default_frequency' => 'As per schedule', 'default_duration' => 'As per schedule', 'default_timing' => 'Any time', 'form' => 'injection'],
            ['name' => 'IV Normal Saline', 'generic_name' => 'Sodium Chloride 0.9%', 'category' => 'IV Fluids', 'default_dosage' => '500ml', 'default_frequency' => 'As directed', 'default_duration' => 'As directed', 'default_timing' => 'Any time', 'form' => 'injection'],
            ['name' => 'IV Dextrose 5%', 'generic_name' => 'Dextrose 5% in Water', 'category' => 'IV Fluids', 'default_dosage' => '500ml', 'default_frequency' => 'As directed', 'default_duration' => 'As directed', 'default_timing' => 'Any time', 'form' => 'injection'],
            ['name' => 'Diazepam 5mg', 'generic_name' => 'Diazepam', 'category' => 'Psychiatric/Neuro', 'default_dosage' => '5mg', 'default_frequency' => 'OD', 'default_duration' => '5 days', 'default_timing' => 'At bedtime', 'form' => 'tablet'],
        ];
    }

    // ================================================================
    //  Available-tests definitions
    // ================================================================

    private function getAvailableTests(): array
    {
        return [
            // ── Lab - Blood ────────────────────────────
            ['name' => 'Complete Blood Count', 'code' => 'CBC', 'type' => 'lab', 'category' => 'blood', 'price' => 350, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Erythrocyte Sedimentation Rate', 'code' => 'ESR', 'type' => 'lab', 'category' => 'blood', 'price' => 200, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'C-Reactive Protein', 'code' => 'CRP', 'type' => 'lab', 'category' => 'blood', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Blood Sugar Fasting', 'code' => 'BSF', 'type' => 'lab', 'category' => 'blood', 'price' => 150, 'turnaround_time' => '2 hours', 'instructions' => 'Fasting 8-12 hours required'],
            ['name' => 'Blood Sugar Post Prandial', 'code' => 'BSPP', 'type' => 'lab', 'category' => 'blood', 'price' => 150, 'turnaround_time' => '2 hours', 'instructions' => 'Sample to be taken 2 hours after meal'],
            ['name' => 'Random Blood Sugar', 'code' => 'RBS', 'type' => 'lab', 'category' => 'blood', 'price' => 150, 'turnaround_time' => '30 min', 'instructions' => null],
            ['name' => 'Glycated Hemoglobin', 'code' => 'HbA1c', 'type' => 'lab', 'category' => 'blood', 'price' => 600, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Lipid Profile', 'code' => 'LIPID', 'type' => 'lab', 'category' => 'blood', 'price' => 600, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 8-12 hours required'],
            ['name' => 'Liver Function Test', 'code' => 'LFT', 'type' => 'lab', 'category' => 'blood', 'price' => 700, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Kidney Function Test / Renal Function Test', 'code' => 'KFT/RFT', 'type' => 'lab', 'category' => 'blood', 'price' => 700, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Thyroid Stimulating Hormone', 'code' => 'TSH', 'type' => 'lab', 'category' => 'blood', 'price' => 400, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'T3 / T4 Levels', 'code' => 'T3T4', 'type' => 'lab', 'category' => 'blood', 'price' => 600, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Uric Acid', 'code' => 'UA', 'type' => 'lab', 'category' => 'blood', 'price' => 250, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Serum Creatinine', 'code' => 'SCREAT', 'type' => 'lab', 'category' => 'blood', 'price' => 250, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Blood Urea Nitrogen', 'code' => 'BUN', 'type' => 'lab', 'category' => 'blood', 'price' => 250, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Serum Electrolytes (Na/K/Cl)', 'code' => 'ELEC', 'type' => 'lab', 'category' => 'blood', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Serum Calcium', 'code' => 'CA', 'type' => 'lab', 'category' => 'blood', 'price' => 300, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Serum Phosphorus', 'code' => 'PHOS', 'type' => 'lab', 'category' => 'blood', 'price' => 300, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Vitamin D (25-OH)', 'code' => 'VITD', 'type' => 'lab', 'category' => 'blood', 'price' => 1200, 'turnaround_time' => 'next day', 'instructions' => null],
            ['name' => 'Vitamin B12', 'code' => 'VITB12', 'type' => 'lab', 'category' => 'blood', 'price' => 900, 'turnaround_time' => 'next day', 'instructions' => null],
            ['name' => 'Iron Studies (Serum Iron, TIBC)', 'code' => 'IRON', 'type' => 'lab', 'category' => 'blood', 'price' => 800, 'turnaround_time' => 'next day', 'instructions' => 'Fasting 8-12 hours required'],
            ['name' => 'Serum Ferritin', 'code' => 'FERR', 'type' => 'lab', 'category' => 'blood', 'price' => 600, 'turnaround_time' => 'next day', 'instructions' => null],
            ['name' => 'Prothrombin Time / INR', 'code' => 'PT/INR', 'type' => 'lab', 'category' => 'blood', 'price' => 400, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Blood Group & Rh Typing', 'code' => 'BG', 'type' => 'lab', 'category' => 'blood', 'price' => 200, 'turnaround_time' => '30 min', 'instructions' => null],
            ['name' => 'Dengue NS1 Antigen', 'code' => 'DNS1', 'type' => 'lab', 'category' => 'blood', 'price' => 700, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Dengue IgM / IgG', 'code' => 'DENG', 'type' => 'lab', 'category' => 'blood', 'price' => 800, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Malaria Rapid Antigen Test', 'code' => 'MRAP', 'type' => 'lab', 'category' => 'blood', 'price' => 400, 'turnaround_time' => '30 min', 'instructions' => null],
            ['name' => 'Widal Test', 'code' => 'WIDAL', 'type' => 'lab', 'category' => 'blood', 'price' => 350, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Blood Culture & Sensitivity', 'code' => 'BCULT', 'type' => 'lab', 'category' => 'blood', 'price' => 1200, 'turnaround_time' => '2-3 days', 'instructions' => 'Collect before starting antibiotics if possible'],
            ['name' => 'HIV I & II (ELISA)', 'code' => 'HIV', 'type' => 'lab', 'category' => 'blood', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Hepatitis B Surface Antigen', 'code' => 'HBsAg', 'type' => 'lab', 'category' => 'blood', 'price' => 400, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Anti-HCV Antibody', 'code' => 'AHCV', 'type' => 'lab', 'category' => 'blood', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'VDRL (Syphilis Screening)', 'code' => 'VDRL', 'type' => 'lab', 'category' => 'blood', 'price' => 300, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Rheumatoid Factor', 'code' => 'RA', 'type' => 'lab', 'category' => 'blood', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Antinuclear Antibody', 'code' => 'ANA', 'type' => 'lab', 'category' => 'blood', 'price' => 1500, 'turnaround_time' => 'next day', 'instructions' => null],
            ['name' => 'Serum Total Protein', 'code' => 'TP', 'type' => 'lab', 'category' => 'blood', 'price' => 250, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Serum Albumin', 'code' => 'ALB', 'type' => 'lab', 'category' => 'blood', 'price' => 250, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Bilirubin Total & Direct', 'code' => 'BILI', 'type' => 'lab', 'category' => 'blood', 'price' => 300, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Prostate Specific Antigen', 'code' => 'PSA', 'type' => 'lab', 'category' => 'blood', 'price' => 800, 'turnaround_time' => 'next day', 'instructions' => null],

            // ── Lab - Urine ───────────────────────────
            ['name' => 'Urine Routine & Microscopy', 'code' => 'URM', 'type' => 'lab', 'category' => 'urine', 'price' => 200, 'turnaround_time' => 'same day', 'instructions' => 'Mid-stream clean catch sample preferred'],
            ['name' => 'Urine Culture & Sensitivity', 'code' => 'UCULT', 'type' => 'lab', 'category' => 'urine', 'price' => 800, 'turnaround_time' => '2-3 days', 'instructions' => 'Mid-stream clean catch sample required'],
            ['name' => '24-Hour Urine Protein', 'code' => '24UP', 'type' => 'lab', 'category' => 'urine', 'price' => 500, 'turnaround_time' => 'next day', 'instructions' => 'Collect all urine over 24 hours in provided container'],
            ['name' => 'Urine Pregnancy Test', 'code' => 'UPT', 'type' => 'lab', 'category' => 'urine', 'price' => 200, 'turnaround_time' => '30 min', 'instructions' => 'First morning sample preferred'],

            // ── Lab - Other ───────────────────────────
            ['name' => 'Stool Routine & Microscopy', 'code' => 'STOOL', 'type' => 'lab', 'category' => 'stool', 'price' => 200, 'turnaround_time' => 'same day', 'instructions' => 'Fresh sample required'],
            ['name' => 'Sputum Culture & Sensitivity', 'code' => 'SPCULT', 'type' => 'lab', 'category' => 'stool', 'price' => 800, 'turnaround_time' => '2-3 days', 'instructions' => 'Early morning deep cough sample preferred'],
            ['name' => 'Sputum for AFB (Acid Fast Bacilli)', 'code' => 'AFB', 'type' => 'lab', 'category' => 'stool', 'price' => 300, 'turnaround_time' => 'next day', 'instructions' => 'Collect 3 early morning samples on consecutive days'],
            ['name' => 'Throat Swab Culture', 'code' => 'TSCULT', 'type' => 'lab', 'category' => 'stool', 'price' => 700, 'turnaround_time' => '2-3 days', 'instructions' => null],

            // ── Imaging ───────────────────────────────
            ['name' => 'X-Ray Chest PA View', 'code' => 'XRCP', 'type' => 'imaging', 'category' => 'imaging', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => 'Remove metallic objects from chest area'],
            ['name' => 'X-Ray Abdomen', 'code' => 'XRAB', 'type' => 'imaging', 'category' => 'imaging', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'X-Ray Spine (AP/Lateral)', 'code' => 'XRSP', 'type' => 'imaging', 'category' => 'imaging', 'price' => 600, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'X-Ray Knee (AP/Lateral)', 'code' => 'XRKN', 'type' => 'imaging', 'category' => 'imaging', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'X-Ray Shoulder (AP)', 'code' => 'XRSH', 'type' => 'imaging', 'category' => 'imaging', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'X-Ray Hand/Wrist', 'code' => 'XRHW', 'type' => 'imaging', 'category' => 'imaging', 'price' => 500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'USG Abdomen', 'code' => 'USGA', 'type' => 'imaging', 'category' => 'imaging', 'price' => 1200, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 6 hours required. Drink 4-5 glasses of water 1 hour before'],
            ['name' => 'USG Pelvis', 'code' => 'USGP', 'type' => 'imaging', 'category' => 'imaging', 'price' => 1200, 'turnaround_time' => 'same day', 'instructions' => 'Full bladder required. Drink 4-5 glasses of water 1 hour before'],
            ['name' => 'USG KUB (Kidney, Ureter, Bladder)', 'code' => 'USGK', 'type' => 'imaging', 'category' => 'imaging', 'price' => 1200, 'turnaround_time' => 'same day', 'instructions' => 'Full bladder required'],
            ['name' => 'USG Thyroid', 'code' => 'USGT', 'type' => 'imaging', 'category' => 'imaging', 'price' => 1000, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'CT Scan Head (Plain)', 'code' => 'CTHP', 'type' => 'imaging', 'category' => 'imaging', 'price' => 3500, 'turnaround_time' => 'same day', 'instructions' => 'Remove metallic objects. Inform if pregnant'],
            ['name' => 'CT Scan Head (Contrast)', 'code' => 'CTHC', 'type' => 'imaging', 'category' => 'imaging', 'price' => 5500, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 4 hours required. Inform about allergies or kidney issues. Serum creatinine required'],
            ['name' => 'CT Scan Chest', 'code' => 'CTCH', 'type' => 'imaging', 'category' => 'imaging', 'price' => 5000, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 4 hours if contrast. Inform about allergies'],
            ['name' => 'CT Scan Abdomen', 'code' => 'CTAB', 'type' => 'imaging', 'category' => 'imaging', 'price' => 5500, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 4 hours required. Drink oral contrast as directed'],
            ['name' => 'MRI Brain', 'code' => 'MRIB', 'type' => 'imaging', 'category' => 'imaging', 'price' => 7000, 'turnaround_time' => 'next day', 'instructions' => 'Remove all metallic objects. Inform about implants or pacemaker'],
            ['name' => 'MRI Spine (Cervical)', 'code' => 'MRISC', 'type' => 'imaging', 'category' => 'imaging', 'price' => 7000, 'turnaround_time' => 'next day', 'instructions' => 'Remove all metallic objects. Inform about implants or pacemaker'],
            ['name' => 'MRI Spine (Lumbar)', 'code' => 'MRISL', 'type' => 'imaging', 'category' => 'imaging', 'price' => 7000, 'turnaround_time' => 'next day', 'instructions' => 'Remove all metallic objects. Inform about implants or pacemaker'],
            ['name' => 'MRI Knee', 'code' => 'MRIK', 'type' => 'imaging', 'category' => 'imaging', 'price' => 7500, 'turnaround_time' => 'next day', 'instructions' => 'Remove all metallic objects. Inform about implants'],

            // ── Procedures / Cardiac ──────────────────
            ['name' => 'Electrocardiogram', 'code' => 'ECG', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 300, 'turnaround_time' => '30 min', 'instructions' => null],
            ['name' => '2D Echocardiography', 'code' => 'ECHO', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 2500, 'turnaround_time' => 'same day', 'instructions' => null],
            ['name' => 'Treadmill Test (TMT)', 'code' => 'TMT', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 2000, 'turnaround_time' => 'same day', 'instructions' => 'Wear comfortable shoes. Avoid heavy meals 2 hours before. Bring list of current medications'],
            ['name' => 'Holter Monitor (24hr)', 'code' => 'HOLTER', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 3000, 'turnaround_time' => '2-3 days', 'instructions' => 'Maintain diary of activities and symptoms during monitoring period'],
            ['name' => 'Pulmonary Function Test', 'code' => 'PFT', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 1500, 'turnaround_time' => 'same day', 'instructions' => 'Avoid smoking 4 hours before. Avoid bronchodilators as directed by doctor'],
            ['name' => 'Electroencephalogram', 'code' => 'EEG', 'type' => 'procedure', 'category' => 'neuro', 'price' => 2000, 'turnaround_time' => 'next day', 'instructions' => 'Wash hair before test. Avoid caffeine. Sleep deprivation may be required as directed'],
            ['name' => 'EMG / Nerve Conduction Velocity', 'code' => 'EMG/NCV', 'type' => 'procedure', 'category' => 'neuro', 'price' => 3500, 'turnaround_time' => 'next day', 'instructions' => 'Inform if on blood thinners. Avoid lotions on skin'],
            ['name' => 'Upper GI Endoscopy', 'code' => 'UGIE', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 5000, 'turnaround_time' => 'same day', 'instructions' => 'Fasting 8-12 hours required. Stop blood thinners as directed. Arrange someone to drive you home'],
            ['name' => 'Colonoscopy', 'code' => 'COLON', 'type' => 'procedure', 'category' => 'cardiac', 'price' => 8000, 'turnaround_time' => 'same day', 'instructions' => 'Bowel preparation required as per instructions. Clear liquid diet 24 hours before. Arrange someone to drive you home'],
        ];
    }
}
