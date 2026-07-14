<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill the charge-capture ledger (charge_items) from existing bills for
 * every hospital, so the Billing Audit's department + GST panels reflect
 * historical bills instead of showing zeros. Idempotent — bills that already
 * have charge_items are skipped. See App\Modules\Billing\Services\BillLedgerBackfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('charge_items') || ! Schema::hasTable('bills')) {
            return;
        }

        \App\Modules\Billing\Services\BillLedgerBackfill::backfillAll();
    }

    public function down(): void
    {
        // Remove only the rows this backfill created.
        if (Schema::hasTable('charge_items')) {
            \Illuminate\Support\Facades\DB::table('charge_items')
                ->where('posted_by_name', 'Ledger backfill')
                ->delete();
        }
    }
};
