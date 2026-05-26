<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow staff to work at multiple hospitals
        Schema::create('staff_hospital', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('role')->default('doctor'); // role at THIS hospital
            $table->string('department')->nullable();
            $table->json('schedule')->nullable(); // hospital-specific schedule
            $table->integer('consultation_duration_default')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['staff_id', 'hospital_id']);
            $table->index('hospital_id');
        });

        // Allow users to access multiple hospitals
        Schema::create('user_hospital', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('role')->default('doctor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'hospital_id']);
            $table->index('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hospital');
        Schema::dropIfExists('staff_hospital');
    }
};
