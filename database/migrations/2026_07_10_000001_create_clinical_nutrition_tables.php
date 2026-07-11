<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Real clinical-nutrition model: a therapeutic-diet catalogue, physician/dietitian
 * DIET ORDERS per patient (texture, route incl. NPO/tube-feed, kcal/protein targets,
 * restrictions), and dietitian NUTRITION ASSESSMENTS (MUST/NRS-2002 screening +
 * anthropometry). Replaces the earlier meals/meal_orders MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('meal_orders');
        Schema::dropIfExists('meals');

        if (! Schema::hasTable('therapeutic_diets')) {
            Schema::create('therapeutic_diets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('code', 20);
                $table->string('name');
                $table->string('category', 30)->default('therapeutic'); // regular|soft|liquid|therapeutic|enteral|npo
                $table->string('default_texture', 30)->default('regular');
                $table->text('indications')->nullable();
                $table->text('restrictions')->nullable();
                $table->integer('default_kcal')->nullable();
                $table->integer('default_protein_g')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('diet_orders')) {
            Schema::create('diet_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('diet_id');
                $table->uuid('admission_id')->nullable()->index();  // ward diet census
                $table->string('ward')->nullable();
                $table->string('texture', 30)->default('regular');
                $table->string('route', 20)->default('oral');       // oral|ng_tube|peg|npo
                $table->integer('fluid_restriction_ml')->nullable();
                $table->integer('kcal_target')->nullable();
                $table->integer('protein_target_g')->nullable();
                $table->text('restrictions')->nullable();           // allergies / religious / other
                $table->text('special_instructions')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('status', 20)->default('active');    // active|discontinued
                $table->string('ordered_by_name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('nutrition_assessments')) {
            Schema::create('nutrition_assessments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->string('tool', 20)->default('MUST');        // MUST|NRS-2002|SGA
                $table->integer('score')->nullable();
                $table->string('risk', 20)->nullable();             // low|medium|high
                $table->decimal('weight_kg', 6, 2)->nullable();
                $table->decimal('height_cm', 6, 2)->nullable();
                $table->decimal('bmi', 5, 2)->nullable();
                $table->text('diagnosis')->nullable();
                $table->text('plan')->nullable();
                $table->date('follow_up_date')->nullable();
                $table->string('assessed_by_name')->nullable();
                $table->timestamps();
            });
        }

        $hid = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hid && DB::table('therapeutic_diets')->where('hospital_id', $hid)->count() === 0) {
            $now = now();
            // code, name, category, texture, indications, restrictions, kcal, protein
            $seed = [
                ['REG',   'Regular / Normal', 'regular', 'regular', 'No dietary restriction', null, 2000, 60],
                ['SOFT',  'Soft Diet', 'soft', 'soft', 'Post-op, difficulty chewing', 'No hard/raw foods', 1800, 55],
                ['MSOFT', 'Mechanical Soft / Dysphagia (minced-moist)', 'soft', 'minced_moist', 'Dysphagia, dentition issues', 'IDDSI Level 5', 1700, 55],
                ['PUREE', 'Pureed / Dysphagia', 'soft', 'pureed', 'Severe dysphagia', 'IDDSI Level 4; SLT review', 1600, 50],
                ['FLIQ',  'Full Liquid', 'liquid', 'liquid', 'Transitional, post-op', 'Liquids only', 1400, 45],
                ['CLIQ',  'Clear Liquid', 'liquid', 'clear_liquid', 'Pre/post-procedure, bowel rest', 'Clear fluids only', 800, 10],
                ['DM',    'Diabetic (consistent-carbohydrate)', 'therapeutic', 'regular', 'Diabetes mellitus', 'Controlled CHO, no added sugar', 1600, 65],
                ['RENAL', 'Renal (low K / low PO4 / controlled protein)', 'therapeutic', 'regular', 'CKD / dialysis', 'Low potassium, low phosphorus, Na 2 g', 1800, 50],
                ['CARD',  'Cardiac / Low-Sodium (2 g Na)', 'therapeutic', 'regular', 'CHF, hypertension', 'Sodium <2 g/day, low sat-fat', 1800, 60],
                ['LRES',  'Low-Residue / Low-Fibre', 'therapeutic', 'regular', 'IBD flare, post-bowel-surgery', 'Fibre <10 g/day', 1800, 60],
                ['HPHC',  'High-Protein High-Calorie', 'therapeutic', 'regular', 'Wound healing, malnutrition', null, 2500, 100],
                ['LOWFAT','Low-Fat', 'therapeutic', 'regular', 'Pancreatitis, gallbladder', 'Fat <40 g/day', 1700, 60],
                ['NPO',   'NPO (Nil by mouth)', 'npo', 'regular', 'Pre-op, aspiration risk, ileus', 'Nothing by mouth', 0, 0],
                ['ENT',   'Enteral / Tube Feed', 'enteral', 'liquid', 'Unable to feed orally', 'Formula per dietitian', 1800, 70],
            ];
            foreach ($seed as [$code, $name, $cat, $tex, $ind, $res, $kcal, $pro]) {
                DB::table('therapeutic_diets')->insert([
                    'id' => (string) Str::uuid(), 'hospital_id' => $hid, 'code' => $code, 'name' => $name,
                    'category' => $cat, 'default_texture' => $tex, 'indications' => $ind, 'restrictions' => $res,
                    'default_kcal' => $kcal, 'default_protein_g' => $pro, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_assessments');
        Schema::dropIfExists('diet_orders');
        Schema::dropIfExists('therapeutic_diets');
    }
};
