<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Decommission fields on assets.
        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                if (! Schema::hasColumn('assets', 'decommissioned_on')) {
                    $table->date('decommissioned_on')->nullable()->after('status');
                }
                if (! Schema::hasColumn('assets', 'decommission_reason')) {
                    $table->string('decommission_reason')->nullable()->after('decommissioned_on');
                }
                if (! Schema::hasColumn('assets', 'disposal_method')) {
                    $table->string('disposal_method')->nullable()->after('decommission_reason');
                }
            });
        }

        // Calibration records.
        if (! Schema::hasTable('asset_calibrations')) {
            Schema::create('asset_calibrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->date('calibrated_on')->nullable();
                $table->date('next_due_date')->nullable();
                $table->string('performed_by')->nullable();
                $table->string('result')->default('pass'); // pass, fail, adjusted
                $table->string('certificate_path')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('reminder_days_before_due')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['asset_id', 'is_active']);
                $table->index(['hospital_id', 'next_due_date']);
            });
        }

        // Service requests / breakdown tickets.
        if (! Schema::hasTable('asset_service_requests')) {
            Schema::create('asset_service_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('hospital_id')->constrained('hospitals')->cascadeOnDelete();
                $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('reported_by')->nullable();
                $table->dateTime('reported_at')->nullable();
                $table->text('issue');
                $table->string('priority')->default('normal');  // low, normal, high, critical
                $table->string('status')->default('open');       // open, in_progress, resolved, closed
                $table->string('assigned_to')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['hospital_id', 'status']);
                $table->index(['asset_id', 'status']);
            });
        }

        $this->seedSamples();
    }

    /** A few sample calibrations + one open ticket so the new sections show data. */
    private function seedSamples(): void
    {
        $hospital = DB::table('hospitals')->where('name', 'City Care Hospital')->first()
            ?? DB::table('hospitals')->first();
        if (! $hospital) {
            return;
        }
        $hid = $hospital->id;
        $assets = DB::table('assets')->where('hospital_id', $hid)->orderBy('asset_name')->get();
        if ($assets->isEmpty()) {
            return;
        }
        $now = now();
        $uuid = fn () => (string) Str::uuid();

        if (! DB::table('asset_calibrations')->where('hospital_id', $hid)->exists()) {
            foreach ($assets->take(3) as $i => $a) {
                DB::table('asset_calibrations')->insert([
                    'id' => $uuid(), 'hospital_id' => $hid, 'asset_id' => $a->id,
                    'calibrated_on' => $now->copy()->subMonths(5)->toDateString(),
                    'next_due_date' => $now->copy()->addDays([10, 45, 120][$i] ?? 60)->toDateString(),
                    'performed_by' => 'Biomedical Dept', 'result' => 'pass',
                    'reminder_days_before_due' => 30, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! DB::table('asset_service_requests')->where('hospital_id', $hid)->exists()) {
            $ventilator = $assets->firstWhere('serial_number', 'VEN-330') ?? $assets->first();
            DB::table('asset_service_requests')->insert([
                'id' => $uuid(), 'hospital_id' => $hid, 'asset_id' => $ventilator->id,
                'reported_by' => 'OT Nurse', 'reported_at' => $now->copy()->subDays(1),
                'issue' => 'Intermittent alarm and pressure reading fluctuation during use.',
                'priority' => 'high', 'status' => 'open', 'assigned_to' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_service_requests');
        Schema::dropIfExists('asset_calibrations');
        if (Schema::hasTable('assets')) {
            Schema::table('assets', function (Blueprint $table) {
                foreach (['decommissioned_on', 'decommission_reason', 'disposal_method'] as $col) {
                    if (Schema::hasColumn('assets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
