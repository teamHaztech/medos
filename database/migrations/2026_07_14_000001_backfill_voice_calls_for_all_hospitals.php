<?php

use App\Modules\Core\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voice AI Calls (and the other modules added this cycle) were only enabled for
 * hospitals that existed when 2026_07_09_000010 ran. Hospitals created since then
 * via super-admin got a curated default list that omitted `voice_calls`, so the
 * module was invisible for them ("only City Care can see AI Calls").
 *
 * Re-apply the back-fill for EVERY hospital so voice_calls + the other new modules
 * are enabled everywhere. Idempotent: merging already-present keys is a no-op, and
 * hospitals with an empty list already have every module on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('hospitals')) {
            return;
        }

        foreach (DB::table('hospitals')->get(['id', 'modules_enabled']) as $h) {
            $enabled = json_decode($h->modules_enabled ?? '[]', true);
            if (! is_array($enabled) || empty($enabled)) {
                continue; // empty = every module already on
            }

            $merged = array_values(array_unique(array_merge($enabled, ModuleCatalog::NEW_MODULE_KEYS)));
            if (count($merged) !== count($enabled)) {
                DB::table('hospitals')->where('id', $h->id)->update(['modules_enabled' => json_encode($merged)]);
            }
        }
    }

    public function down(): void
    {
        // No-op: leaving the modules enabled is harmless.
    }
};
