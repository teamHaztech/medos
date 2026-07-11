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
        if (! Schema::hasTable('pathway_templates')) {
            Schema::create('pathway_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('name');
                $table->string('category', 30)->default('medical'); // medical|surgical|obstetric|pediatric|emergency|other
                $table->json('steps')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('patient_pathways')) {
            Schema::create('patient_pathways', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('template_id');
                $table->string('status', 20)->default('active'); // active|completed|discontinued
                $table->json('completed_steps')->nullable();      // array of completed step indices
                $table->text('notes')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hospitalId && DB::table('pathway_templates')->where('hospital_id', $hospitalId)->count() === 0) {
            $now = now();
            $seed = [
                ['Chest Pain Pathway', 'emergency', ['Triage & ECG within 10 min', 'Cardiac enzymes', 'Risk stratification', 'Cardiology review', 'Disposition / admit']],
                ['Appendicectomy Pathway', 'surgical', ['Pre-op assessment', 'Consent', 'Surgery', 'Post-op monitoring', 'Diet advance', 'Discharge planning']],
                ['Vaginal Birth Pathway', 'obstetric', ['Admission assessment', 'Labour monitoring (partogram)', 'Delivery', 'Post-partum care', 'Newborn assessment', 'Discharge']],
                ['Diabetes Mellitus Type 2 Pathway', 'medical', ['Baseline labs (HbA1c)', 'Dietician referral', 'Start medication', 'Patient education', 'Follow-up in 4 weeks']],
            ];
            foreach ($seed as [$name, $cat, $steps]) {
                DB::table('pathway_templates')->insert([
                    'id'          => (string) Str::uuid(),
                    'hospital_id' => $hospitalId,
                    'name'        => $name,
                    'category'    => $cat,
                    'steps'       => json_encode($steps),
                    'is_active'   => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_pathways');
        Schema::dropIfExists('pathway_templates');
    }
};
