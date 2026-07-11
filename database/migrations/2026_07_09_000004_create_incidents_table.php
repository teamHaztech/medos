<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('incidents')) {
            return;
        }

        Schema::create('incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id')->index();
            $table->string('incident_no', 40)->index();
            $table->string('reported_by_name')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('department')->nullable();
            $table->string('category', 30)->default('other');   // fall|medication|near_miss|equipment|infection|security|documentation|other
            $table->string('severity', 20)->default('minor');   // minor|moderate|major|sentinel
            $table->uuid('patient_id')->nullable()->index();
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->text('capa')->nullable();                    // corrective & preventive action
            $table->string('assigned_to_name')->nullable();
            $table->string('status', 20)->default('reported');   // reported|under_review|action_taken|closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
