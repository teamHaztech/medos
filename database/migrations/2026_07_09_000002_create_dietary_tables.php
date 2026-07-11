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
        if (! Schema::hasTable('meals')) {
            Schema::create('meals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->string('name');
                $table->string('diet_type', 40)->default('regular'); // regular|diabetic|soft|liquid|renal|high_protein|low_salt
                $table->text('constituents')->nullable();
                $table->integer('calories')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('meal_orders')) {
            Schema::create('meal_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->uuid('meal_id');
                $table->date('scheduled_date')->index();
                $table->string('slot', 20)->default('lunch'); // breakfast|lunch|dinner|snack
                $table->string('status', 20)->default('ordered'); // ordered|prepared|delivered|cancelled
                $table->string('ordered_by_name')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        $hospitalId = DB::table('users')->where('email', 'admin@haztech.in')->value('hospital_id')
            ?? DB::table('hospitals')->where('is_active', true)->value('id');
        if ($hospitalId && DB::table('meals')->where('hospital_id', $hospitalId)->count() === 0) {
            $now = now();
            $seed = [
                ['Regular Diet', 'regular', 'Rice, dal, vegetables, roti, curd', 2000, 150],
                ['Diabetic Diet', 'diabetic', 'Low-GI grains, salad, lean protein, no sugar', 1600, 180],
                ['Soft Diet', 'soft', 'Khichdi, mashed vegetables, soup, custard', 1500, 160],
                ['Liquid Diet', 'liquid', 'Clear soups, juices, milk, broth', 1000, 140],
                ['Renal Diet', 'renal', 'Low-potassium, low-phosphorus, controlled protein', 1800, 200],
                ['High-Protein Diet', 'high_protein', 'Eggs, chicken, paneer, pulses, milk', 2200, 220],
            ];
            foreach ($seed as [$name, $type, $const, $cal, $price]) {
                DB::table('meals')->insert([
                    'id'           => (string) Str::uuid(),
                    'hospital_id'  => $hospitalId,
                    'name'         => $name,
                    'diet_type'    => $type,
                    'constituents' => $const,
                    'calories'     => $cal,
                    'price'        => $price,
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_orders');
        Schema::dropIfExists('meals');
    }
};
