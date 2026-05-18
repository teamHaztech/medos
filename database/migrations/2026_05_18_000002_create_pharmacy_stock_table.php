<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignUuid('medicine_id')->constrained('medicines')->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->integer('quantity_total')->default(0);
            $table->integer('quantity_available')->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->datetime('received_at')->nullable();
            $table->timestamps();

            $table->index(['hospital_id', 'medicine_id']);
            $table->index(['medicine_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_stock');
    }
};
