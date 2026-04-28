<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Run order matters: later seeders depend on data created by earlier ones.
     */
    public function run(): void
    {
        $this->call([
            HospitalSeeder::class,
            StaffSeeder::class,
            PatientSeeder::class,
            EncounterSeeder::class,
            AppointmentSeeder::class,
            ConversationSeeder::class,
            BillSeeder::class,
            MedicinesAndTestsSeeder::class,
        ]);
    }
}
