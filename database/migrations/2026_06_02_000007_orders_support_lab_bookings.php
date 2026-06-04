<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow lab orders booked directly by a patient (no doctor, no encounter)
     * and optionally carrying a scheduled time.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'scheduled_for')) {
                $table->dateTime('scheduled_for')->nullable()->after('priority');
            }
            if (! Schema::hasColumn('orders', 'booking_source')) {
                $table->string('booking_source')->nullable()->after('scheduled_for');
            }
        });

        // Self-booked lab orders have no doctor and no encounter.
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('ordered_by')->nullable()->change();
            $table->uuid('encounter_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['scheduled_for', 'booking_source'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
