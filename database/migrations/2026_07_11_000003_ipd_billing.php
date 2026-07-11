<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ward tariff (per-day room + nursing rate).
        if (Schema::hasTable('wards')) {
            Schema::table('wards', function (Blueprint $table) {
                if (! Schema::hasColumn('wards', 'daily_rate')) {
                    $table->decimal('daily_rate', 12, 2)->default(0)->after('ward_type');
                }
                if (! Schema::hasColumn('wards', 'nursing_daily_rate')) {
                    $table->decimal('nursing_daily_rate', 12, 2)->default(0)->after('daily_rate');
                }
            });
        }

        // Rate snapshot on the admission so later tariff changes don't rewrite history.
        if (Schema::hasTable('admissions')) {
            Schema::table('admissions', function (Blueprint $table) {
                if (! Schema::hasColumn('admissions', 'room_daily_rate')) {
                    $table->decimal('room_daily_rate', 12, 2)->default(0)->after('bed_id');
                }
                if (! Schema::hasColumn('admissions', 'nursing_daily_rate')) {
                    $table->decimal('nursing_daily_rate', 12, 2)->default(0)->after('room_daily_rate');
                }
                if (! Schema::hasColumn('admissions', 'bed_category')) {
                    $table->string('bed_category', 40)->nullable()->after('nursing_daily_rate');
                }
            });
        }

        // Link a bill to its admission (IPD final bill).
        if (Schema::hasTable('bills') && ! Schema::hasColumn('bills', 'admission_id')) {
            Schema::table('bills', function (Blueprint $table) {
                $table->uuid('admission_id')->nullable()->after('encounter_id')->index();
            });
        }

        // Seed default ward tariffs by type (only where unset).
        if (Schema::hasTable('wards')) {
            $rates = [
                'General'      => [1500, 300],
                'Semi-Private' => [2500, 400],
                'Private'      => [4000, 500],
                'ICU'          => [8000, 1000],
                'Maternity'    => [3000, 400],
                'Pediatric'    => [2000, 400],
                'Emergency'    => [2000, 500],
            ];
            foreach (DB::table('wards')->where(function ($q) {
                $q->whereNull('daily_rate')->orWhere('daily_rate', 0);
            })->get() as $ward) {
                [$room, $nursing] = $rates[$ward->ward_type] ?? [1500, 300];
                DB::table('wards')->where('id', $ward->id)->update([
                    'daily_rate'         => $room,
                    'nursing_daily_rate' => $nursing,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['daily_rate', 'nursing_daily_rate'] as $c) {
            if (Schema::hasColumn('wards', $c)) {
                Schema::table('wards', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        foreach (['room_daily_rate', 'nursing_daily_rate', 'bed_category'] as $c) {
            if (Schema::hasColumn('admissions', $c)) {
                Schema::table('admissions', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        if (Schema::hasColumn('bills', 'admission_id')) {
            Schema::table('bills', fn (Blueprint $t) => $t->dropColumn('admission_id'));
        }
    }
};
