<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('bill_number')->unique();
            // JSON: array of {description, code, quantity, unit_price, total, category}
            $table->json('line_items');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('insurance_covered', 12, 2)->default(0);
            $table->decimal('patient_payable', 12, 2);
            // Allowed: pending, partial, paid, refunded, waived
            $table->string('payment_status')->default('pending');
            // Allowed: cash, upi, card, insurance, mixed
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index('hospital_id');
            $table->index('encounter_id');
            $table->index('patient_id');
            $table->index('payment_status');
            $table->index(['hospital_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
