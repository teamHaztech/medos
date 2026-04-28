<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('doctor_id')->constrained('staff')->cascadeOnDelete();
            $table->string('token_number');
            // Allowed: waiting, called, in_consultation, completed, skipped, left
            $table->string('status')->default('waiting');
            $table->decimal('priority_score', 5, 2)->default(0);
            $table->integer('estimated_wait_minutes')->nullable();
            $table->integer('actual_wait_minutes')->nullable();
            $table->integer('position');
            $table->dateTime('called_at')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('appointment_id');
            $table->index('patient_id');
            $table->index('doctor_id');
            $table->index('status');
            $table->index(['hospital_id', 'doctor_id', 'status']);
            $table->index(['hospital_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
