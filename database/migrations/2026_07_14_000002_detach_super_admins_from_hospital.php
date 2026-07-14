<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A Super Admin operates at the platform level across every hospital, so it
 * should not be pinned to a single tenant. Detaching it (hospital_id = null)
 * removes the "operating here" marker and makes the account truly
 * hospital-agnostic. Hospital-scoped admin screens fall back to the first
 * active hospital via AdminWebController::effectiveHospitalId().
 *
 * Guarded + idempotent — safe to re-run via the browser deploy on prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'hospital_id')) {
            DB::table('users')
                ->where('role', 'super_admin')
                ->whereNotNull('hospital_id')
                ->update(['hospital_id' => null]);
        }
    }

    public function down(): void
    {
        // No-op: we don't know which hospital each super admin was previously
        // pinned to, and re-pinning is neither needed nor desirable.
    }
};
