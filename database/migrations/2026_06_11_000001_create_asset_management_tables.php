<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vendors / service providers (referenced by assets + warranties).
        if (! Schema::hasTable('vendors')) {
            Schema::create('vendors', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->string('name');
                $table->string('contact_person')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('address')->nullable();
                $table->string('service_type')->nullable(); // e.g. Biomedical, IT, Civil
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('name');
                $table->index(['hospital_id', 'is_active']);
            });
        }

        // Assets — hospital equipment (OT/ICU/Ward etc.).
        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->string('asset_name');
                $table->string('asset_type')->nullable();   // OT Table, Ventilator, Monitor...
                $table->string('serial_number')->nullable();
                $table->string('model')->nullable();
                $table->string('manufacturer')->nullable();
                $table->string('department')->nullable();    // OT, ICU, Ward...
                $table->string('location')->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('purchase_cost', 12, 2)->nullable();
                $table->foreignUuid('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('status')->default('active'); // active, under_maintenance, decommissioned
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('asset_name');
                $table->index('serial_number');
                $table->index(['hospital_id', 'status']);
                $table->index(['hospital_id', 'department']);
                $table->index(['hospital_id', 'asset_type']);
            });
        }

        // Warranties / AMC / CMC contracts per asset.
        if (! Schema::hasTable('asset_warranties')) {
            Schema::create('asset_warranties', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('warranty_type')->default('manufacturer'); // manufacturer, amc, cmc
                $table->date('start_date')->nullable();
                $table->date('end_date');
                $table->string('vendor_contact')->nullable();
                $table->text('terms')->nullable();
                $table->string('document_path')->nullable();
                $table->unsignedSmallInteger('reminder_days_before_expiry')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['asset_id', 'is_active']);
                $table->index(['hospital_id', 'end_date']);
            });
        }

        // Maintenance logs per asset.
        if (! Schema::hasTable('asset_maintenance_logs')) {
            Schema::create('asset_maintenance_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('maintenance_type')->default('preventive'); // preventive, corrective
                $table->string('performed_by')->nullable();
                $table->date('date');
                $table->decimal('cost', 12, 2)->nullable();
                $table->date('next_due_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['asset_id', 'date']);
                $table->index(['hospital_id', 'next_due_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenance_logs');
        Schema::dropIfExists('asset_warranties');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('vendors');
    }
};
