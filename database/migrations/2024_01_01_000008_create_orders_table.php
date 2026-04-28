<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('ordered_by')->constrained('staff')->cascadeOnDelete();
            // Allowed: lab, pharmacy, imaging, procedure
            $table->string('type');
            // Allowed: ordered, accepted, in_progress, completed, dispensed, cancelled
            $table->string('status')->default('ordered');
            // JSON: array of {name, code, quantity, instructions, status, result}
            $table->json('items');
            // Allowed: routine, urgent, stat
            $table->string('priority')->default('routine');
            // JSON: aggregated results
            $table->json('results')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('encounter_id');
            $table->index('patient_id');
            $table->index('ordered_by');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index(['hospital_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
