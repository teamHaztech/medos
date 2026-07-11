<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dental_charts')) {
            Schema::create('dental_charts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->string('dentition', 20)->default('adult'); // adult|pediatric
                $table->json('tooth_status')->nullable();           // { "16": "caries", ... }
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dental_treatments')) {
            Schema::create('dental_treatments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hospital_id')->index();
                $table->uuid('patient_id')->index();
                $table->string('tooth_number', 10)->nullable();
                $table->string('procedure');
                $table->string('status', 20)->default('planned'); // planned|in_progress|completed
                $table->decimal('cost', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_treatments');
        Schema::dropIfExists('dental_charts');
    }
};
