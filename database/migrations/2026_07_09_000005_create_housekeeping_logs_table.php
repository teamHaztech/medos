<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('housekeeping_logs')) {
            return;
        }

        Schema::create('housekeeping_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id')->index();
            $table->string('location');
            $table->string('category', 30)->default('other'); // cleanliness|waste|linen|consumable|item_missing|equipment|non_compliance|other
            $table->text('description');
            $table->string('priority', 20)->default('medium'); // low|medium|high
            $table->string('status', 20)->default('open');     // open|in_progress|closed
            $table->string('reported_by_name')->nullable();
            $table->string('assigned_to_name')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('housekeeping_logs');
    }
};
