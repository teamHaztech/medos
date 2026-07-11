<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Vaccines gain an age-based schedule (National Immunization Schedule) + default route.
        Schema::table('vaccines', function (Blueprint $table) {
            if (! Schema::hasColumn('vaccines', 'age_schedule')) {
                $table->json('age_schedule')->nullable()->after('dose_interval_days'); // [{dose,age_days,label}]
            }
            if (! Schema::hasColumn('vaccines', 'route')) {
                $table->string('route', 10)->default('im')->after('age_schedule'); // oral|im|sc|id
            }
        });

        // Administration record gains lot/manufacturer/expiry, route, site, and AEFI capture.
        Schema::table('patient_vaccinations', function (Blueprint $table) {
            foreach ([
                'route'        => fn () => $table->string('route', 10)->nullable()->after('batch_number'),
                'site'         => fn () => $table->string('site', 40)->nullable()->after('route'),
                'manufacturer' => fn () => $table->string('manufacturer', 120)->nullable()->after('site'),
                'expiry_date'  => fn () => $table->date('expiry_date')->nullable()->after('manufacturer'),
                'has_aefi'     => fn () => $table->boolean('has_aefi')->default(false)->after('next_dose_done'),
                'aefi_notes'   => fn () => $table->string('aefi_notes', 500)->nullable()->after('has_aefi'),
            ] as $col => $add) {
                if (! Schema::hasColumn('patient_vaccinations', $col)) {
                    $add();
                }
            }
        });

        // Upsert the National Immunization Schedule for every hospital (idempotent, by code).
        // [name, code, category, route, total_doses, [ [dose, age_days, label] ... ]]
        $nis = [
            ['BCG', 'BCG', 'routine', 'id', 1, [[1, 0, 'At birth']]],
            ['Hepatitis B (birth dose)', 'HEPB', 'routine', 'im', 1, [[1, 0, 'At birth']]],
            ['OPV (Oral Polio)', 'OPV', 'routine', 'oral', 5, [[1, 0, 'Birth'], [2, 42, '6 weeks'], [3, 70, '10 weeks'], [4, 98, '14 weeks'], [5, 480, '16–24 months']]],
            ['Pentavalent (DPT-HepB-Hib)', 'PENTA', 'routine', 'im', 3, [[1, 42, '6 weeks'], [2, 70, '10 weeks'], [3, 98, '14 weeks']]],
            ['Rotavirus', 'ROTA', 'routine', 'oral', 3, [[1, 42, '6 weeks'], [2, 70, '10 weeks'], [3, 98, '14 weeks']]],
            ['PCV (Pneumococcal)', 'PCV', 'routine', 'im', 3, [[1, 42, '6 weeks'], [2, 98, '14 weeks'], [3, 270, '9 months (booster)']]],
            ['fIPV (Inactivated Polio)', 'FIPV', 'routine', 'id', 2, [[1, 42, '6 weeks'], [2, 98, '14 weeks']]],
            ['Measles-Rubella (MR)', 'MR', 'routine', 'sc', 2, [[1, 270, '9 months'], [2, 480, '16–24 months']]],
            ['JE (Japanese Encephalitis)', 'JE', 'routine', 'sc', 2, [[1, 270, '9 months'], [2, 480, '16–24 months']]],
            ['DPT Booster', 'DPTB', 'routine', 'im', 2, [[1, 480, '16–24 months'], [2, 1825, '5–6 years']]],
            ['Td (Tetanus-diphtheria)', 'TD', 'routine', 'im', 2, [[1, 3650, '10 years'], [2, 5840, '16 years']]],
            ['Influenza', 'FLU', 'travel', 'im', 1, []],
            ['COVID-19', 'COVID', 'covid', 'im', 2, []],
            ['Typhoid', 'TYPH', 'travel', 'im', 1, []],
        ];

        $now = now();
        foreach (DB::table('hospitals')->pluck('id') as $hid) {
            foreach ($nis as [$name, $code, $cat, $route, $doses, $schedule]) {
                $sched = empty($schedule) ? null : json_encode(array_map(
                    fn ($s) => ['dose' => $s[0], 'age_days' => $s[1], 'label' => $s[2]],
                    $schedule
                ));
                $existing = DB::table('vaccines')->where('hospital_id', $hid)->where('code', $code)->first();
                if ($existing) {
                    DB::table('vaccines')->where('id', $existing->id)->update([
                        'name' => $name, 'category' => $cat, 'route' => $route,
                        'total_doses' => $doses, 'age_schedule' => $sched, 'updated_at' => $now,
                    ]);
                } else {
                    DB::table('vaccines')->insert([
                        'id' => Str::uuid()->toString(), 'hospital_id' => $hid,
                        'name' => $name, 'code' => $code, 'category' => $cat, 'route' => $route,
                        'total_doses' => $doses, 'dose_interval_days' => null, 'age_schedule' => $sched,
                        'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['age_schedule', 'route'] as $col) {
            if (Schema::hasColumn('vaccines', $col)) {
                Schema::table('vaccines', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
        foreach (['route', 'site', 'manufacturer', 'expiry_date', 'has_aefi', 'aefi_notes'] as $col) {
            if (Schema::hasColumn('patient_vaccinations', $col)) {
                Schema::table('patient_vaccinations', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
