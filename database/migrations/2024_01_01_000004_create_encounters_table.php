<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('doctor_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('encounter_number')->unique();
            // Allowed: consultation, follow_up, emergency, procedure, lab_only, pharmacy_only
            $table->string('type');
            // Allowed: initiated, intake_complete, triaged, booked, checked_in, in_progress, completed, cancelled, no_show
            $table->string('status')->default('initiated');
            // Allowed: whatsapp, voice, kiosk, web, walk_in
            $table->string('channel');
            // JSON: {chief_complaint, duration, severity_indicators, structured_symptoms}
            $table->json('intake_data')->nullable();
            $table->decimal('triage_score', 3, 2)->nullable();
            // Allowed: emergency, urgent, semi_urgent, routine
            $table->string('triage_classification')->nullable();
            // JSON: AI reasoning for triage decision
            $table->json('triage_reasoning')->nullable();
            // JSON (encrypted via cast): {subjective, objective, assessment, plan}
            $table->json('soap_notes')->nullable();
            // JSON: array of ICD-10 codes
            $table->json('diagnosis_codes')->nullable();
            $table->foreignUuid('referral_to')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('status');
            $table->index('type');
            $table->index('channel');
            $table->index(['hospital_id', 'status']);
            $table->index(['hospital_id', 'patient_id']);
            $table->index(['doctor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
