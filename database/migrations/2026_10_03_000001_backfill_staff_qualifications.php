<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill realistic qualifications and specializations for any doctor records where empty.
     */
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }

        $defaultQualifications = [
            'General Medicine'   => 'MBBS, MD (General Medicine)',
            'Cardiology'         => 'MBBS, MD, DM (Cardiology)',
            'Pediatrics'         => 'MBBS, MD (Pediatrics)',
            'Orthopedics'        => 'MBBS, MS (Orthopedics)',
            'Gynecology'         => 'MBBS, MS (OBG)',
            'Dermatology'        => 'MBBS, MD (Dermatology)',
            'ENT'                => 'MBBS, MS (ENT)',
            'Dental'             => 'BDS, MDS (Oral & Maxillofacial)',
            'Clinical Nutrition' => 'M.Sc (Clinical Nutrition), RD',
            'Ophthalmology'      => 'MBBS, MS (Ophthalmology)',
        ];

        $doctors = DB::table('staff')
            ->whereIn('role', ['doctor', 'hospital_admin', 'dentist', 'dietitian'])
            ->get();

        foreach ($doctors as $d) {
            $updates = [];
            $dept = $d->department ?: 'General Medicine';

            if (empty($d->specialization)) {
                $updates['specialization'] = $dept;
            }

            if (empty($d->qualification)) {
                $updates['qualification'] = $defaultQualifications[$dept] ?? 'MBBS';
            }

            if (! empty($updates)) {
                DB::table('staff')->where('id', $d->id)->update($updates);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive: keep populated qualifications
    }
};
