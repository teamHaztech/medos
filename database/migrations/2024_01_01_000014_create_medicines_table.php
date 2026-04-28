<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('category')->nullable(); // antibiotic, painkiller, antacid, etc.
            $table->string('default_dosage')->nullable(); // 500mg, 10mg
            $table->string('default_frequency')->nullable(); // OD, BD, TDS
            $table->string('default_duration')->nullable(); // 5 days
            $table->string('default_timing')->nullable(); // After food
            $table->string('form')->nullable(); // tablet, capsule, syrup, injection, cream, drops
            $table->boolean('is_global')->default(false); // true = available to all hospitals
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('category');
            $table->index(['hospital_id', 'is_active']);
        });

        Schema::create('available_tests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable(); // internal code
            $table->string('type'); // lab, imaging, procedure
            $table->string('category')->nullable(); // blood, urine, xray, ultrasound, ct, mri
            $table->decimal('price', 10, 2)->default(0);
            $table->string('turnaround_time')->nullable(); // 2 hours, same day, next day
            $table->text('instructions')->nullable(); // fasting required, etc.
            $table->boolean('is_global')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index(['type', 'is_active']);
            $table->index(['hospital_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('available_tests');
        Schema::dropIfExists('medicines');
    }
};
