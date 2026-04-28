<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            // Polymorphic user type: App\Models\User, App\Models\Staff, system, etc.
            $table->string('user_type')->nullable();
            $table->uuid('user_id')->nullable();
            // Action performed: created, updated, deleted, viewed, exported, login, etc.
            $table->string('action');
            // Entity class: App\Models\Patient, App\Models\Encounter, etc.
            $table->string('entity_type')->nullable();
            $table->uuid('entity_id')->nullable();
            // JSON: previous values before change
            $table->json('old_values')->nullable();
            // JSON: new values after change
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            // JSON: additional context (request_id, source, etc.)
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('hospital_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('created_at');
            $table->index(['hospital_id', 'entity_type', 'entity_id']);
            $table->index(['hospital_id', 'user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
