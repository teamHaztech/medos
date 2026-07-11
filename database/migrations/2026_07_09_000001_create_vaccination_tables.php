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
        if (! Schema::hasTable('vaccines')) {
            Schema::create('vaccines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('name');
                $table->string('code', 40)->nullable();
                $table->string('category', 60)->default('routine'); // routine | travel | covid | other
                $table->integer('total_doses')->default(1);
                $table->integer('dose_interval_days')->nullable(); // gap to the next dose
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('patient_vaccinations')) {
            Schema::create('patient_vaccinations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('vaccine_id')->index();
                $table->integer('dose_number')->default(1);
                $table->date('given_date');
                $table->string('batch_number', 60)->nullable();
                $table->string('given_by_name')->nullable();
                $table->date('next_due_date')->nullable()->index();
                $table->boolean('next_dose_done')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Seed a common vaccine master for the first active hospital (idempotent).
        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hospitalId && DB::table('vaccines')->where('hospital_id', $hospitalId)->count() === 0) {
            $now = now();
            $seed = [
                ['BCG', 'BCG', 'routine', 1, null],
                ['Hepatitis B', 'HEPB', 'routine', 3, 30],
                ['OPV (Polio)', 'OPV', 'routine', 4, 30],
                ['Pentavalent (DPT-HepB-Hib)', 'PENTA', 'routine', 3, 30],
                ['MMR', 'MMR', 'routine', 2, 180],
                ['Tetanus Toxoid', 'TT', 'routine', 2, 30],
                ['Influenza', 'FLU', 'routine', 1, 365],
                ['COVID-19', 'COVID', 'covid', 2, 84],
                ['Typhoid', 'TYPH', 'travel', 1, null],
            ];
            foreach ($seed as [$name, $code, $cat, $doses, $interval]) {
                DB::table('vaccines')->insert([
                    'id'                 => (string) Str::uuid(),
                    'hospital_id'        => $hospitalId,
                    'name'               => $name,
                    'code'               => $code,
                    'category'           => $cat,
                    'total_doses'        => $doses,
                    'dose_interval_days' => $interval,
                    'is_active'          => true,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_vaccinations');
        Schema::dropIfExists('vaccines');
    }
};
