<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add lab-specific fields to orders
        if (!Schema::hasColumn('orders', 'sample_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('sample_id')->nullable()->after('notes');
                $table->string('sample_type')->nullable()->after('sample_id'); // blood, urine, swab, stool, csf
                $table->string('container_type')->nullable()->after('sample_type'); // EDTA, plain, citrate, urine_cup
                $table->string('collection_location')->nullable()->after('container_type'); // ward, opd, home
                $table->string('lab_status')->nullable()->after('collection_location'); // collected, transported, received, processing, result_entry, verified, released
                $table->datetime('transported_at')->nullable()->after('lab_status');
                $table->datetime('received_at')->nullable()->after('transported_at');
                $table->datetime('processing_at')->nullable()->after('received_at');
                $table->datetime('result_entered_at')->nullable()->after('processing_at');
                $table->datetime('released_at')->nullable()->after('result_entered_at');
                $table->uuid('released_by')->nullable()->after('released_at');
                $table->boolean('has_critical')->default(false)->after('released_by');
                $table->boolean('critical_acknowledged')->default(false)->after('has_critical');
                $table->uuid('critical_acknowledged_by')->nullable()->after('critical_acknowledged');
                $table->datetime('critical_acknowledged_at')->nullable()->after('critical_acknowledged_by');
                $table->uuid('assigned_to')->nullable()->after('critical_acknowledged_at');
            });
        }

        // Add reference ranges and critical values to available_tests
        if (!Schema::hasColumn('available_tests', 'unit')) {
            Schema::table('available_tests', function (Blueprint $table) {
                $table->string('unit')->nullable()->after('category'); // mg/dL, g/dL, cells/mcL
                $table->string('method')->nullable()->after('unit'); // photometry, immunoassay, etc
                $table->string('sample_type')->nullable()->after('method'); // blood, urine, etc
                $table->string('container_type')->nullable()->after('sample_type'); // EDTA, plain, etc
                $table->json('reference_ranges')->nullable()->after('container_type');
                // Structure: {"male": {"min": 13, "max": 17}, "female": {"min": 12, "max": 15}, "child": {...}}
                $table->json('critical_values')->nullable()->after('reference_ranges');
                // Structure: {"low": 5, "high": 20}
                $table->integer('tat_minutes')->nullable()->after('critical_values'); // target TAT in minutes
                $table->text('interpretation_template')->nullable()->after('tat_minutes');
            });
        }

        // Sample events log (lifecycle tracking)
        if (!Schema::hasTable('sample_events')) {
            Schema::create('sample_events', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->string('event'); // ordered, collected, transported, received, processing, result_entry, verified, released
                $table->uuid('performed_by')->nullable();
                $table->text('notes')->nullable();
                $table->string('delay_reason')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'event']);
                $table->index('hospital_id');
            });
        }

        // Critical alert logs
        if (!Schema::hasTable('critical_alert_logs')) {
            Schema::create('critical_alert_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
                $table->string('test_name');
                $table->string('value');
                $table->string('critical_type'); // high, low
                $table->string('threshold');
                $table->uuid('notified_doctor_id')->nullable();
                $table->boolean('acknowledged')->default(false);
                $table->uuid('acknowledged_by')->nullable();
                $table->datetime('acknowledged_at')->nullable();
                $table->text('action_taken')->nullable();
                $table->timestamps();

                $table->index(['hospital_id', 'acknowledged']);
                $table->index('order_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_alert_logs');
        Schema::dropIfExists('sample_events');

        if (Schema::hasColumn('orders', 'sample_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn([
                    'sample_id', 'sample_type', 'container_type', 'collection_location',
                    'lab_status', 'transported_at', 'received_at', 'processing_at',
                    'result_entered_at', 'released_at', 'released_by',
                    'has_critical', 'critical_acknowledged', 'critical_acknowledged_by',
                    'critical_acknowledged_at', 'assigned_to',
                ]);
            });
        }

        if (Schema::hasColumn('available_tests', 'unit')) {
            Schema::table('available_tests', function (Blueprint $table) {
                $table->dropColumn([
                    'unit', 'method', 'sample_type', 'container_type',
                    'reference_ranges', 'critical_values', 'tat_minutes', 'interpretation_template',
                ]);
            });
        }
    }
};
