<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            // Allowed: super_admin, hospital_admin, doctor, nurse, receptionist, pharmacist, lab_tech, billing_staff
            $table->string('role');
            $table->string('department')->nullable();
            $table->string('specialization')->nullable();
            $table->string('qualification')->nullable();
            // JSON: array of availability blocks e.g. [{day, start, end, slot_duration}]
            $table->json('schedule')->nullable();
            $table->integer('consultation_duration_default')->default(15); // minutes
            // JSON: ratings, avg_wait_time, patients_seen, etc.
            $table->json('performance_metrics')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('role');
            $table->index('department');
            $table->index('is_active');
            $table->index(['hospital_id', 'role']);
            $table->index(['hospital_id', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
