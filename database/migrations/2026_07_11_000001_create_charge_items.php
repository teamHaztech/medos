<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('charge_items')) {
            return;
        }

        Schema::create('charge_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hospital_id')->index();
            $table->uuid('patient_id')->index();
            $table->uuid('encounter_id')->nullable()->index();
            $table->uuid('admission_id')->nullable()->index();
            $table->uuid('service_charge_id')->nullable();
            $table->uuid('bill_id')->nullable()->index();

            // registration|consultation|lab|imaging|pharmacy|procedure|room|nursing|consumable|other
            $table->string('source', 20);
            // Traceability + idempotency key for the originating event (order id, admission-day, etc.)
            $table->string('source_ref', 120)->nullable();

            $table->string('description');
            $table->string('code', 40)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->boolean('is_taxable')->default(false);

            $table->string('status', 12)->default('pending'); // pending|billed|cancelled
            $table->string('posted_by_name')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'patient_id', 'status']);
            // One charge per originating event — prevents double-billing on re-post.
            $table->unique(['hospital_id', 'source', 'source_ref'], 'charge_items_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_items');
    }
};
