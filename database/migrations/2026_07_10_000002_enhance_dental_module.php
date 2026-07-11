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
        // 1. Dental procedure master / fee schedule.
        if (! Schema::hasTable('dental_procedures')) {
            Schema::create('dental_procedures', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('code', 20);
                $table->string('name');
                $table->string('category', 40)->default('general');
                $table->decimal('default_fee', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['hospital_id', 'code']);
            });
        }

        // 2. Enrich treatments: link to fee schedule, tooth surfaces, billing + performed date.
        Schema::table('dental_treatments', function (Blueprint $table) {
            if (! Schema::hasColumn('dental_treatments', 'procedure_id')) {
                $table->uuid('procedure_id')->nullable()->after('patient_id')->index();
            }
            if (! Schema::hasColumn('dental_treatments', 'surfaces')) {
                $table->string('surfaces', 12)->nullable()->after('tooth_number'); // e.g. MOD, DO
            }
            if (! Schema::hasColumn('dental_treatments', 'performed_date')) {
                $table->date('performed_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('dental_treatments', 'bill_id')) {
                $table->uuid('bill_id')->nullable()->after('cost')->index();
            }
        });

        // 3. Per-visit clinical record (the daily dental note).
        if (! Schema::hasTable('dental_visits')) {
            Schema::create('dental_visits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->date('visit_date');
                $table->string('chief_complaint')->nullable();
                $table->text('examination')->nullable();
                $table->text('procedures_done')->nullable();
                $table->text('advice')->nullable();
                $table->date('next_visit_date')->nullable();
                $table->string('dentist_name')->nullable();
                $table->timestamps();
            });
        }

        // 4. Seed a real fee schedule per hospital (guarded, idempotent).
        $procedures = [
            ['CONS', 'Consultation / examination', 'diagnostic', 300],
            ['XR-IOPA', 'X-ray — IOPA (intraoral)', 'diagnostic', 200],
            ['XR-OPG', 'X-ray — OPG / panoramic', 'diagnostic', 800],
            ['SCP', 'Scaling & polishing (full mouth)', 'preventive', 1200],
            ['FLUOR', 'Fluoride application', 'preventive', 600],
            ['COMP', 'Composite filling (tooth-coloured)', 'restorative', 1500],
            ['GIC', 'GIC filling', 'restorative', 900],
            ['AMAL', 'Amalgam filling', 'restorative', 800],
            ['RCT-A', 'Root canal treatment — anterior', 'endodontic', 3500],
            ['RCT-P', 'Root canal treatment — premolar', 'endodontic', 4500],
            ['RCT-M', 'Root canal treatment — molar', 'endodontic', 6000],
            ['POST', 'Post & core build-up', 'endodontic', 2500],
            ['CROWN-PFM', 'Crown — porcelain fused to metal', 'prosthetic', 5000],
            ['CROWN-ZR', 'Crown — zirconia', 'prosthetic', 9000],
            ['CROWN-MTL', 'Crown — full metal', 'prosthetic', 3500],
            ['EXT-S', 'Extraction — simple', 'surgical', 800],
            ['EXT-SUR', 'Extraction — surgical / impacted', 'surgical', 4000],
            ['FLAP', 'Flap surgery (per quadrant)', 'surgical', 6000],
            ['IMPL', 'Dental implant — single', 'prosthetic', 25000],
            ['DENT-C', 'Complete denture (per arch)', 'prosthetic', 12000],
            ['DENT-P', 'Removable partial denture', 'prosthetic', 8000],
            ['BLEACH', 'Teeth whitening / bleaching', 'cosmetic', 7000],
            ['PULP', 'Pulpotomy (pediatric)', 'pediatric', 1800],
            ['SSC', 'Stainless steel crown (pediatric)', 'pediatric', 2200],
        ];

        foreach (DB::table('hospitals')->pluck('id') as $hid) {
            if (DB::table('dental_procedures')->where('hospital_id', $hid)->exists()) {
                continue;
            }
            $now = now();
            $rows = [];
            foreach ($procedures as [$code, $name, $cat, $fee]) {
                $rows[] = [
                    'id' => Str::uuid()->toString(),
                    'hospital_id' => $hid,
                    'code' => $code,
                    'name' => $name,
                    'category' => $cat,
                    'default_fee' => $fee,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('dental_procedures')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_visits');
        Schema::dropIfExists('dental_procedures');
        foreach (['procedure_id', 'surfaces', 'performed_date', 'bill_id'] as $col) {
            if (Schema::hasColumn('dental_treatments', $col)) {
                Schema::table('dental_treatments', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
