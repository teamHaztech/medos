<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('phone');
            $table->boolean('phone_verified')->default(false);
            $table->string('name');
            $table->string('email')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->integer('age_approximate')->nullable();
            // Allowed: male, female, other, unknown
            $table->string('gender')->default('unknown');
            $table->string('blood_group')->nullable();
            $table->string('language_preference')->default('en');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            // JSON: structured medical history
            $table->json('medical_history')->nullable();
            // JSON: array of known allergies
            $table->json('allergies')->nullable();
            // JSON: array of current medications
            $table->json('current_medications')->nullable();
            // JSON (encrypted via cast): policy_number, insurer, member_id, etc.
            $table->json('insurance_details')->nullable();
            // JSON: any additional metadata
            $table->json('metadata')->nullable();
            // Allowed: whatsapp, voice, kiosk, web, walk_in, admin
            $table->string('created_via')->default('walk_in');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['hospital_id', 'phone']);
            $table->index('hospital_id');
            $table->index('phone');
            $table->index('name');
            $table->index('created_via');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
