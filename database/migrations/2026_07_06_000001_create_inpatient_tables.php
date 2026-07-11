<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Inpatient (IP) + Admission/Discharge/Transfer module.
 * Wards, beds, admissions, and the IP case sheet (vitals, intake/output, notes).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wards')) {
            Schema::create('wards', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->string('name');
                $table->string('ward_type')->nullable(); // General, ICU, Private, Semi-Private, Maternity, Pediatric
                $table->string('floor')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['hospital_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('beds')) {
            Schema::create('beds', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('ward_id')->constrained('wards')->cascadeOnDelete();
                $table->string('bed_number');
                $table->string('status')->default('available'); // available, occupied, maintenance
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['ward_id', 'status']);
                $table->index(['hospital_id', 'status']);
            });
        }

        if (! Schema::hasTable('admissions')) {
            Schema::create('admissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->foreignUuid('admitting_doctor_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->foreignUuid('attending_doctor_id')->nullable()->constrained('staff')->nullOnDelete();
                $table->foreignUuid('ward_id')->nullable()->constrained('wards')->nullOnDelete();
                $table->foreignUuid('bed_id')->nullable()->constrained('beds')->nullOnDelete();
                $table->string('admission_no');
                $table->dateTime('admitted_at');
                $table->string('reason')->nullable();
                $table->string('provisional_diagnosis')->nullable();
                $table->string('status')->default('admitted'); // admitted, discharged
                $table->dateTime('discharged_at')->nullable();
                $table->text('discharge_summary')->nullable();
                $table->string('discharge_type')->nullable(); // recovered, referred, dama, lama, expired
                $table->timestamps();
                $table->index(['hospital_id', 'status']);
                $table->index('patient_id');
            });
        }

        if (! Schema::hasTable('ip_vitals')) {
            Schema::create('ip_vitals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('admission_id')->constrained('admissions')->cascadeOnDelete();
                $table->string('recorded_by')->nullable();
                $table->dateTime('recorded_at');
                $table->unsignedSmallInteger('bp_systolic')->nullable();
                $table->unsignedSmallInteger('bp_diastolic')->nullable();
                $table->unsignedSmallInteger('pulse')->nullable();
                $table->unsignedSmallInteger('spo2')->nullable();
                $table->unsignedSmallInteger('resp_rate')->nullable();
                $table->decimal('temperature', 4, 1)->nullable();
                $table->decimal('weight', 5, 1)->nullable();
                $table->decimal('height', 5, 1)->nullable();
                $table->decimal('bmi', 4, 1)->nullable();
                $table->string('notes')->nullable();
                $table->timestamps();
                $table->index(['admission_id', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('ip_intake_output')) {
            Schema::create('ip_intake_output', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('admission_id')->constrained('admissions')->cascadeOnDelete();
                $table->dateTime('recorded_at');
                $table->string('direction'); // intake, output
                $table->string('category')->nullable(); // Oral, IV, Urine, Drain, Vomit, Stool
                $table->unsignedInteger('volume_ml');
                $table->string('notes')->nullable();
                $table->string('recorded_by')->nullable();
                $table->timestamps();
                $table->index(['admission_id', 'recorded_at']);
            });
        }

        if (! Schema::hasTable('ip_notes')) {
            Schema::create('ip_notes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('admission_id')->constrained('admissions')->cascadeOnDelete();
                $table->uuid('author_id')->nullable();
                $table->string('author_name')->nullable();
                $table->string('author_role')->nullable();
                $table->string('note_type')->default('doctor'); // doctor, nurse, order
                $table->text('body');
                $table->timestamps();
                $table->index(['admission_id', 'created_at']);
            });
        }

        $this->seedSample();
    }

    /** 2 wards + ~10 beds + one active admission with vitals, so the module has starter data. */
    private function seedSample(): void
    {
        $hospital = DB::table('hospitals')->where('name', 'City Care Hospital')->first()
            ?? DB::table('hospitals')->first();
        if (! $hospital) {
            return;
        }
        $hid = $hospital->id;
        if (DB::table('wards')->where('hospital_id', $hid)->exists()) {
            return;
        }

        $now = now();
        $uuid = fn () => (string) Str::uuid();

        $wards = [
            ['name' => 'General Ward A', 'ward_type' => 'General', 'floor' => '1', 'beds' => 6],
            ['name' => 'ICU', 'ward_type' => 'ICU', 'floor' => '2', 'beds' => 4],
        ];

        $firstAvailableBed = null; $firstWardId = null;
        foreach ($wards as $w) {
            $wid = $uuid();
            DB::table('wards')->insert([
                'id' => $wid, 'hospital_id' => $hid, 'name' => $w['name'],
                'ward_type' => $w['ward_type'], 'floor' => $w['floor'], 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $prefix = $w['ward_type'] === 'ICU' ? 'ICU' : 'G';
            for ($i = 1; $i <= $w['beds']; $i++) {
                $bid = $uuid();
                DB::table('beds')->insert([
                    'id' => $bid, 'hospital_id' => $hid, 'ward_id' => $wid,
                    'bed_number' => $prefix . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'status' => 'available', 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                if (! $firstAvailableBed) { $firstAvailableBed = $bid; $firstWardId = $wid; }
            }
        }

        // One live admission (if a patient + doctor exist).
        $patient = DB::table('patients')->where('hospital_id', $hid)->first();
        $doctor = DB::table('staff')->where('hospital_id', $hid)->where('role', 'doctor')->first();
        if ($patient && $firstAvailableBed) {
            $aid = $uuid();
            DB::table('admissions')->insert([
                'id' => $aid, 'hospital_id' => $hid, 'patient_id' => $patient->id,
                'admitting_doctor_id' => $doctor->id ?? null, 'attending_doctor_id' => $doctor->id ?? null,
                'ward_id' => $firstWardId, 'bed_id' => $firstAvailableBed,
                'admission_no' => 'IP-' . $now->format('Ymd') . '-0001',
                'admitted_at' => $now->copy()->subDays(1),
                'reason' => 'Observation', 'provisional_diagnosis' => 'Acute gastroenteritis',
                'status' => 'admitted', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('beds')->where('id', $firstAvailableBed)->update(['status' => 'occupied', 'updated_at' => $now]);
            DB::table('ip_vitals')->insert([
                'id' => $uuid(), 'hospital_id' => $hid, 'admission_id' => $aid,
                'recorded_by' => 'Nurse', 'recorded_at' => $now->copy()->subHours(6),
                'bp_systolic' => 122, 'bp_diastolic' => 80, 'pulse' => 78, 'spo2' => 98, 'resp_rate' => 16,
                'temperature' => 98.6, 'weight' => 70, 'height' => 172, 'bmi' => 23.7,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_notes');
        Schema::dropIfExists('ip_intake_output');
        Schema::dropIfExists('ip_vitals');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('beds');
        Schema::dropIfExists('wards');
    }
};
