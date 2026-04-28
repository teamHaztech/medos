<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('insurer_code');
            $table->string('insurer_name');
            $table->string('policy_number');
            $table->string('member_id');
            // Allowed: eligibility_check, pre_authorization, claim_submission, claim_follow_up
            $table->string('type');
            // Allowed: pending, submitted, approved, partially_approved, denied, resubmitted, paid, expired
            $table->string('status')->default('pending');
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            // JSON: array of ICD-10 diagnosis codes
            $table->json('diagnosis_codes')->nullable();
            // JSON: array of CPT/procedure codes
            $table->json('procedure_codes')->nullable();
            $table->decimal('requested_amount', 12, 2)->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('patient_copay', 12, 2)->nullable();
            $table->text('denial_reason')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->string('external_reference_id')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('encounter_id');
            $table->index('patient_id');
            $table->index('status');
            $table->index('type');
            $table->index('insurer_code');
            $table->index('policy_number');
            $table->index(['hospital_id', 'status']);
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_transactions');
    }
};
