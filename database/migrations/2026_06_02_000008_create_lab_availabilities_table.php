<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lab_availabilities')) {
            return;
        }

        Schema::create('lab_availabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            // schedule: { monday: [{start,end}, ...], ... } — same shape as staff.schedule
            $table->json('schedule')->nullable();
            $table->integer('slot_duration')->default(15);   // minutes per slot
            $table->integer('capacity')->default(4);          // patients per slot
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('hospital_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_availabilities');
    }
};
