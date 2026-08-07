<?php

use App\Modules\Core\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ophthalmology (Eye Hospital) module — clinical workspace for an eye department.
 *   - eye_procedures : per-hospital fee schedule (consultation, refraction, laser, surgery…)
 *   - eye_treatments : the patient's planned/completed procedures → billed to MedOS billing
 *   - eye_exams      : per-visit exam — visual acuity, IOP, refraction / spectacle Rx,
 *                      anterior & posterior segment, diagnosis and advice
 * Idempotent: safe to re-run via /public/deploy.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eye_procedures')) {
            Schema::create('eye_procedures', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('code', 20);
                $table->string('name', 150);
                $table->string('category', 20)->default('general');
                $table->decimal('default_fee', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eye_treatments')) {
            Schema::create('eye_treatments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('procedure_id')->nullable();
                $table->string('eye', 4)->nullable();          // od | os | ou
                $table->string('procedure', 150);
                $table->string('status', 20)->default('planned'); // planned|in_progress|completed
                $table->date('performed_date')->nullable();
                $table->decimal('cost', 10, 2)->default(0);
                $table->uuid('bill_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eye_exams')) {
            Schema::create('eye_exams', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->date('exam_date');
                $table->string('chief_complaint', 255)->nullable();
                // Visual acuity (e.g. 6/6, 6/9, CF, HM) — right (OD) / left (OS), unaided & aided
                $table->string('va_od_unaided', 12)->nullable();
                $table->string('va_od_aided', 12)->nullable();
                $table->string('va_os_unaided', 12)->nullable();
                $table->string('va_os_aided', 12)->nullable();
                // Intraocular pressure (mmHg)
                $table->decimal('iop_od', 5, 1)->nullable();
                $table->decimal('iop_os', 5, 1)->nullable();
                // Refraction / spectacle prescription — stored as strings to keep signs & axis
                $table->string('od_sph', 10)->nullable();
                $table->string('od_cyl', 10)->nullable();
                $table->string('od_axis', 10)->nullable();
                $table->string('od_add', 10)->nullable();
                $table->string('os_sph', 10)->nullable();
                $table->string('os_cyl', 10)->nullable();
                $table->string('os_axis', 10)->nullable();
                $table->string('os_add', 10)->nullable();
                $table->string('pd', 10)->nullable();          // pupillary distance
                $table->string('rx_type', 20)->nullable();     // glasses | contact | none
                // Clinical findings
                $table->text('anterior_segment')->nullable();
                $table->text('posterior_segment')->nullable();
                $table->text('diagnosis')->nullable();
                $table->text('advice')->nullable();
                $table->date('next_visit_date')->nullable();
                $table->string('examiner_name', 150)->nullable();
                $table->timestamps();
            });
        }

        $this->seedFeeSchedule();
        $this->seedOphthalmologist();
        $this->enableModule();
    }

    /** A sensible default eye fee schedule for every hospital that has none yet. */
    private function seedFeeSchedule(): void
    {
        if (! Schema::hasTable('eye_procedures') || ! Schema::hasTable('hospitals')) {
            return;
        }

        $rows = [
            ['EYE-CONS', 'Ophthalmology Consultation', 'consultation', 400],
            ['EYE-REF', 'Refraction / Eye Power Test', 'refraction', 300],
            ['EYE-IOP', 'Tonometry (IOP measurement)', 'diagnostic', 250],
            ['EYE-FUN', 'Dilated Fundus Examination', 'diagnostic', 500],
            ['EYE-OCT', 'OCT Scan', 'diagnostic', 2500],
            ['EYE-VF', 'Visual Field (Perimetry)', 'diagnostic', 1200],
            ['EYE-BIO', 'A-Scan Biometry', 'diagnostic', 800],
            ['EYE-PHACO', 'Cataract Surgery (Phaco + IOL)', 'surgical', 35000],
            ['EYE-YAG', 'YAG Laser Capsulotomy', 'laser', 6000],
            ['EYE-PRP', 'Pan-Retinal Photocoagulation (Laser)', 'laser', 8000],
            ['EYE-IVT', 'Intravitreal Injection', 'injection', 15000],
            ['EYE-SPEC', 'Spectacle Dispensing', 'optical', 0],
        ];

        $now = now();
        foreach (DB::table('hospitals')->pluck('id') as $hid) {
            if (DB::table('eye_procedures')->where('hospital_id', $hid)->exists()) {
                continue;
            }
            foreach ($rows as [$code, $name, $category, $fee]) {
                DB::table('eye_procedures')->insert([
                    'id'          => Str::uuid()->toString(),
                    'hospital_id' => $hid,
                    'code'        => $code,
                    'name'        => $name,
                    'category'    => $category,
                    'default_fee' => $fee,
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    /** Seed an ophthalmologist login on the primary hospital + expose the department. */
    private function seedOphthalmologist(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('staff')) {
            return;
        }

        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if (! $hospitalId) {
            return;
        }

        $email = 'eye@haztech.in';
        $name  = 'Dr. Sanjay Rao';
        $now   = now();

        if (! DB::table('users')->where('email', $email)->exists()) {
            $userId  = Str::uuid()->toString();
            $staffId = Str::uuid()->toString();

            DB::table('users')->insert([
                'id' => $userId, 'hospital_id' => $hospitalId, 'staff_id' => null,
                'name' => $name, 'email' => $email, 'password' => Hash::make('password123'),
                'role' => 'doctor', 'is_active' => true, 'email_verified_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('staff')->insert([
                'id' => $staffId, 'hospital_id' => $hospitalId, 'user_id' => $userId,
                'name' => $name, 'email' => $email, 'phone' => null, 'role' => 'doctor',
                'department' => 'Ophthalmology', 'specialization' => 'Ophthalmology',
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('users')->where('id', $userId)->update(['staff_id' => $staffId]);
        }

        // Surface "Ophthalmology" as a bookable department on the primary hospital.
        $hospital = DB::table('hospitals')->where('id', $hospitalId)->first();
        if ($hospital) {
            $config = json_decode($hospital->config ?? '{}', true) ?: [];
            $depts = $config['departments'] ?? [];
            if (! in_array('Ophthalmology', $depts, true)) {
                $depts[] = 'Ophthalmology';
                $config['departments'] = array_values($depts);
                DB::table('hospitals')->where('id', $hospitalId)->update(['config' => json_encode($config)]);
            }
        }
    }

    /** Keep the module accessible on hospitals that carry an explicit modules_enabled list. */
    private function enableModule(): void
    {
        if (! Schema::hasTable('hospitals')) {
            return;
        }
        foreach (DB::table('hospitals')->get(['id', 'modules_enabled']) as $h) {
            $enabled = json_decode($h->modules_enabled ?? '[]', true);
            if (! is_array($enabled) || empty($enabled)) {
                continue; // empty = every module already on
            }
            if (! in_array('ophthalmology', $enabled, true)) {
                $enabled[] = 'ophthalmology';
                DB::table('hospitals')->where('id', $h->id)
                    ->update(['modules_enabled' => json_encode(array_values($enabled))]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eye_exams');
        Schema::dropIfExists('eye_treatments');
        Schema::dropIfExists('eye_procedures');
        DB::table('users')->where('email', 'eye@haztech.in')->delete();
        DB::table('staff')->where('email', 'eye@haztech.in')->delete();
    }
};
