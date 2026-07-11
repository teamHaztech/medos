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
        if (! Schema::hasTable('consent_forms')) {
            Schema::create('consent_forms', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('name');
                $table->string('category', 40)->default('general'); // general|surgical|anesthesia|procedure|admission|blood|research
                $table->text('content')->nullable();
                $table->boolean('requires_witness')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('patient_consents')) {
            Schema::create('patient_consents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('consent_form_id');
                $table->string('status', 20)->default('pending'); // pending|signed|declined|withdrawn
                $table->string('signed_by_name')->nullable();
                $table->string('relationship', 30)->nullable(); // self|guardian|spouse|parent|next_of_kin
                $table->string('witness_name')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hospitalId && DB::table('consent_forms')->where('hospital_id', $hospitalId)->count() === 0) {
            $now = now();
            $seed = [
                ['General Treatment Consent', 'general', false],
                ['Surgical / Operation Consent', 'surgical', true],
                ['Anesthesia Consent', 'anesthesia', false],
                ['Admission Consent', 'admission', false],
                ['Blood Transfusion Consent', 'blood', true],
                ['HIV Test Consent', 'procedure', false],
            ];
            foreach ($seed as [$name, $cat, $witness]) {
                DB::table('consent_forms')->insert([
                    'id'               => (string) Str::uuid(),
                    'hospital_id'      => $hospitalId,
                    'name'             => $name,
                    'category'         => $cat,
                    'content'          => 'I hereby give my informed consent for the above.',
                    'requires_witness' => $witness,
                    'is_active'        => true,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_consents');
        Schema::dropIfExists('consent_forms');
    }
};
