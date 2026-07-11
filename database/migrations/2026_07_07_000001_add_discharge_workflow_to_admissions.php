<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'discharge_status')) {
                $table->string('discharge_status')->default('in_care')->after('status');
            }
            if (! Schema::hasColumn('admissions', 'discharge_initiated_at')) {
                $table->dateTime('discharge_initiated_at')->nullable()->after('discharge_status');
            }
            if (! Schema::hasColumn('admissions', 'billing_cleared_at')) {
                $table->dateTime('billing_cleared_at')->nullable()->after('discharge_initiated_at');
            }
            if (! Schema::hasColumn('admissions', 'pharmacy_cleared_at')) {
                $table->dateTime('pharmacy_cleared_at')->nullable()->after('billing_cleared_at');
            }
            if (! Schema::hasColumn('admissions', 'nursing_cleared_at')) {
                $table->dateTime('nursing_cleared_at')->nullable()->after('pharmacy_cleared_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            foreach (['discharge_status', 'discharge_initiated_at', 'billing_cleared_at', 'pharmacy_cleared_at', 'nursing_cleared_at'] as $col) {
                if (Schema::hasColumn('admissions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
