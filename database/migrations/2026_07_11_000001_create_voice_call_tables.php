<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_call_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id');
            $table->boolean('is_enabled')->default(false);
            $table->string('provider')->nullable();
            $table->json('provider_config')->nullable();
            $table->json('phone_numbers')->nullable();
            $table->text('greeting_message')->nullable();
            $table->string('default_language')->default('en');
            $table->json('supported_languages')->default('["en","hi"]');
            $table->string('voice_name')->nullable();
            $table->integer('max_concurrent_calls')->default(5);
            $table->boolean('auto_callback_enabled')->default(true);
            $table->integer('auto_callback_delay_minutes')->default(5);
            $table->json('business_hours')->nullable();
            $table->text('after_hours_message')->nullable();
            $table->string('emergency_transfer_number')->nullable();
            $table->string('ai_model')->default('claude-haiku-4-5-20251001');
            $table->decimal('ai_temperature', 2, 1)->default(0.3);
            $table->timestamps();

            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
            $table->unique('hospital_id');
        });

        Schema::create('voice_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id');
            $table->string('call_sid')->nullable()->index();
            $table->string('direction');
            $table->string('caller_number');
            $table->string('called_number');
            $table->uuid('patient_id')->nullable();
            $table->string('status')->default('ringing');
            $table->boolean('ai_handled')->default(true);
            $table->string('transferred_to')->nullable();
            $table->string('transfer_reason')->nullable();
            $table->string('language_detected')->nullable();
            $table->string('language_used')->nullable();
            $table->string('call_purpose')->nullable();
            $table->string('sentiment')->nullable();
            $table->decimal('ai_confidence', 3, 2)->nullable();
            $table->uuid('appointment_id')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->integer('ring_duration_seconds')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->string('recording_url')->nullable();
            $table->decimal('cost_amount', 8, 4)->nullable();
            $table->string('cost_currency')->default('INR');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('set null');
            $table->index(['hospital_id', 'status']);
            $table->index(['hospital_id', 'created_at']);
            $table->index('hospital_id');
        });

        Schema::create('voice_call_transcripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voice_call_id');
            $table->string('speaker');
            $table->text('content');
            $table->string('language')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->integer('timestamp_ms');
            $table->timestamp('created_at')->nullable();

            $table->foreign('voice_call_id')->references('id')->on('voice_calls')->onDelete('cascade');
            $table->index('voice_call_id');
        });

        Schema::create('voice_call_callbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id');
            $table->string('caller_number');
            $table->uuid('original_call_id')->nullable();
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->dateTime('next_attempt_at')->nullable()->index();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
            $table->foreign('original_call_id')->references('id')->on('voice_calls')->onDelete('set null');
            $table->index('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_call_callbacks');
        Schema::dropIfExists('voice_call_transcripts');
        Schema::dropIfExists('voice_calls');
        Schema::dropIfExists('voice_call_settings');
    }
};
