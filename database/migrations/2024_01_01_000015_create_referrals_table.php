<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('from_doctor_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('to_doctor_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('urgency')->default('normal'); // emergency, priority, normal
            $table->text('reason')->nullable();
            $table->string('complaint')->nullable();
            $table->string('preferred_date')->nullable();
            $table->string('preferred_time')->nullable();
            $table->string('status')->default('pending'); // pending, accepted, scheduled, completed, declined
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['to_doctor_id', 'status']);
            $table->index(['from_doctor_id']);
            $table->index('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
