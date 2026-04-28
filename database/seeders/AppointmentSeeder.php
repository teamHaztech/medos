<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AppointmentSeeder extends Seeder
{
    /**
     * Seed the appointments table, linked to existing encounters.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('appointments')->truncate();
        Schema::enableForeignKeyConstraints();

        $now   = Carbon::now();
        $today = $now->copy()->startOfDay();

        // Fetch encounters with their details
        $encounters = DB::table('encounters')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->get();

        // Consultation duration defaults per staff
        $staffDurations = DB::table('staff')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->pluck('consultation_duration_default', 'id')
            ->toArray();

        // Specialty token prefix mapping
        $staffSpecialties = DB::table('staff')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->pluck('specialization', 'id')
            ->toArray();

        $tokenPrefixes = [
            'General Medicine' => 'GEN',
            'Pediatrics'       => 'PED',
            'Cardiology'       => 'CAR',
            'Orthopedics'      => 'ORT',
            'Gynecology'       => 'GYN',
            'Dermatology'      => 'DRM',
            'ENT'              => 'ENT',
            'Dental'           => 'DEN',
        ];

        $tokenCounters = [];
        $hourOffset = 0;

        foreach ($encounters as $encounter) {
            $specialty = $staffSpecialties[$encounter->doctor_id] ?? 'General Medicine';
            $prefix    = $tokenPrefixes[$specialty] ?? 'GEN';

            if (! isset($tokenCounters[$prefix])) {
                $tokenCounters[$prefix] = 0;
            }
            $tokenCounters[$prefix]++;
            $tokenNumber = $prefix . '-' . str_pad($tokenCounters[$prefix], 3, '0', STR_PAD_LEFT);

            $consultationDuration = $staffDurations[$encounter->doctor_id] ?? 15;

            $encCreatedAt = Carbon::parse($encounter->created_at);

            if ($encounter->status === 'completed') {
                $slotStart = $encCreatedAt->copy();
                $slotEnd   = $slotStart->copy()->addMinutes($consultationDuration);

                $checkInTime       = $slotStart->copy()->subMinutes(10);
                $consultationStart = $slotStart->copy()->addMinutes(3);
                $consultationEnd   = $consultationStart->copy()->addMinutes($consultationDuration);
                $appointmentStatus = 'completed';
                $actualDuration    = $consultationDuration;

            } elseif ($encounter->status === 'in_progress') {
                $hour      = 9 + ($hourOffset % 4);
                $minute    = [0, 15, 30, 45][$hourOffset % 4];
                $hourOffset++;
                $slotStart = $today->copy()->addHours($hour)->addMinutes($minute);
                $slotEnd   = $slotStart->copy()->addMinutes($consultationDuration);

                $checkInTime       = $slotStart->copy()->subMinutes(5);
                $consultationStart = $slotStart->copy()->addMinutes(2);
                $consultationEnd   = null;
                $appointmentStatus = 'in_progress';
                $actualDuration    = null;

            } elseif ($encounter->status === 'booked') {
                $hour      = 14 + ($hourOffset % 5);
                $minute    = [0, 15, 30, 45][$hourOffset % 4];
                $hourOffset++;
                $slotStart = $today->copy()->addHours($hour)->addMinutes($minute);
                $slotEnd   = $slotStart->copy()->addMinutes($consultationDuration);

                $checkInTime       = null;
                $consultationStart = null;
                $consultationEnd   = null;
                $actualDuration    = null;
                $appointmentStatus = $tokenCounters[$prefix] % 2 === 0 ? 'confirmed' : 'scheduled';

            } else {
                $slotStart = $today->copy()->addHours(16);
                $slotEnd   = $slotStart->copy()->addMinutes($consultationDuration);

                $checkInTime       = null;
                $consultationStart = null;
                $consultationEnd   = null;
                $appointmentStatus = 'scheduled';
                $actualDuration    = null;
            }

            DB::table('appointments')->insert([
                'id'                         => Str::uuid()->toString(),
                'hospital_id'                => SeedData::CITY_CARE_ID,
                'encounter_id'               => $encounter->id,
                'patient_id'                 => $encounter->patient_id,
                'doctor_id'                  => $encounter->doctor_id,
                'slot_start'                 => $slotStart,
                'slot_end'                   => $slotEnd,
                'predicted_duration_minutes' => $consultationDuration,
                'actual_duration_minutes'    => $actualDuration,
                'status'                     => $appointmentStatus,
                'queue_position'             => $tokenCounters[$prefix],
                'check_in_time'              => $checkInTime,
                'consultation_start_time'    => $consultationStart,
                'consultation_end_time'      => $consultationEnd,
                'cancellation_reason'        => null,
                'rescheduled_from'           => null,
                'booking_source'             => $encounter->channel,
                'notes'                      => $tokenNumber,
                'created_at'                 => $slotStart->copy()->subDay(),
                'updated_at'                 => $now,
            ]);
        }

        // ------------------------------------------------------------------
        // Add 5 future appointments (next 3 days) without encounters
        // ------------------------------------------------------------------
        $patients = DB::table('patients')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->pluck('id')
            ->toArray();

        $doctors = DB::table('staff')
            ->where('hospital_id', SeedData::CITY_CARE_ID)
            ->whereIn('role', ['doctor', 'hospital_admin'])
            ->get();

        $futureNotes = [
            'Follow-up visit for diabetes management',
            'Routine check-up',
            'Lab results review',
            'Post-surgery follow-up',
            'Annual health screening',
        ];

        $sources = ['whatsapp', 'web', 'kiosk'];

        for ($i = 0; $i < 5; $i++) {
            $futureDay = ($i % 3) + 1;
            $doctor    = $doctors[$i % $doctors->count()];
            $hour      = 9 + ($i * 2);
            $minute    = [0, 15, 30, 45][$i % 4];
            $slotStart = $today->copy()->addDays($futureDay)->addHours($hour)->addMinutes($minute);
            $duration  = $doctor->consultation_duration_default ?? 15;

            DB::table('appointments')->insert([
                'id'                         => Str::uuid()->toString(),
                'hospital_id'                => SeedData::CITY_CARE_ID,
                'encounter_id'               => null,
                'patient_id'                 => $patients[$i % count($patients)],
                'doctor_id'                  => $doctor->id,
                'slot_start'                 => $slotStart,
                'slot_end'                   => $slotStart->copy()->addMinutes($duration),
                'predicted_duration_minutes' => $duration,
                'actual_duration_minutes'    => null,
                'status'                     => 'scheduled',
                'queue_position'             => null,
                'check_in_time'              => null,
                'consultation_start_time'    => null,
                'consultation_end_time'      => null,
                'cancellation_reason'        => null,
                'rescheduled_from'           => null,
                'booking_source'             => $sources[$i % 3],
                'notes'                      => $futureNotes[$i],
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);
        }
    }
}
