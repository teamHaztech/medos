<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('IN');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            // JSON config: operating_hours, departments, default_consultation_duration, etc.
            $table->json('config')->nullable();
            // JSON array of enabled module slugs e.g. ["appointments","pharmacy","lab","billing","insurance"]
            $table->json('modules_enabled')->nullable();
            $table->string('subscription_plan')->default('free');
            // Allowed: active, trial, suspended, cancelled
            $table->string('subscription_status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
