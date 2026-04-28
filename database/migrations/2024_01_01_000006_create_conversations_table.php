<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            // Allowed: whatsapp, voice, kiosk, web
            $table->string('channel');
            // Allowed: active, completed, escalated, abandoned
            $table->string('status')->default('active');
            // State machine state: greeting, intake, triage, booking, confirmation, etc.
            $table->string('current_state')->default('greeting');
            $table->string('language_detected')->default('en');
            // JSON: array of {role, content, timestamp, language, metadata}
            $table->json('messages')->nullable();
            // JSON: AI reasoning trace for debugging/auditing
            $table->json('ai_reasoning_trace')->nullable();
            $table->string('intent_detected')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->boolean('handled_by_ai')->default(true);
            $table->foreignUuid('escalated_to_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('patient_id');
            $table->index('encounter_id');
            $table->index('status');
            $table->index('channel');
            $table->index(['hospital_id', 'patient_id']);
            $table->index(['hospital_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
