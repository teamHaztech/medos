<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            // Allowed: whatsapp, sms, email, push, dashboard
            $table->string('channel');
            // e.g. appointment_reminder, queue_update, lab_ready, prescription_ready
            $table->string('type');
            // JSON: {template, variables, rendered}
            $table->json('content');
            // Allowed: queued, sent, delivered, read, failed
            $table->string('status')->default('queued');
            $table->string('external_id')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('patient_id');
            $table->index('staff_id');
            $table->index('channel');
            $table->index('type');
            $table->index('status');
            $table->index(['hospital_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
