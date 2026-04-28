<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->nullable()->constrained('encounters')->nullOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('doctor_id')->constrained('staff')->cascadeOnDelete();
            $table->dateTime('slot_start');
            $table->dateTime('slot_end');
            $table->integer('predicted_duration_minutes');
            $table->integer('actual_duration_minutes')->nullable();
            // Allowed: scheduled, confirmed, checked_in, in_progress, completed, no_show, cancelled, rescheduled
            $table->string('status')->default('scheduled');
            $table->integer('queue_position')->nullable();
            $table->dateTime('check_in_time')->nullable();
            $table->dateTime('consultation_start_time')->nullable();
            $table->dateTime('consultation_end_time')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignUuid('rescheduled_from')->nullable()->constrained('appointments')->nullOnDelete();
            // Allowed: ai, manual, kiosk, web
            $table->string('booking_source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('status');
            $table->index('slot_start');
            $table->index(['hospital_id', 'doctor_id', 'slot_start']);
            $table->index(['hospital_id', 'status']);
            $table->index(['doctor_id', 'slot_start', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
