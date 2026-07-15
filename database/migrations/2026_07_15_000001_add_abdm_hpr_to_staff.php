<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ABDM M1 readiness: store each practitioner's HPR (Healthcare Professional
 * Registry) identity on their staff record. Required so a HIP linkEncounter can
 * attribute a care context to a registered practitioner once ABDM is connected.
 * The facility-level HFR id + ABDM credentials live on Hospital.config['abdm'].
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'hpr_id')) {
                $table->string('hpr_id')->nullable()->after('specialization');
            }
            if (! Schema::hasColumn('staff', 'hpr_address')) {
                $table->string('hpr_address')->nullable()->after('hpr_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff')) {
            return;
        }
        Schema::table('staff', function (Blueprint $table) {
            foreach (['hpr_id', 'hpr_address'] as $col) {
                if (Schema::hasColumn('staff', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
