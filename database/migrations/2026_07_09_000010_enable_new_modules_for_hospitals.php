<?php

use App\Modules\Core\Support\ModuleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The clinical/ops modules built this cycle are now gated by `module:<key>`.
 * Hospitals already carry an explicit modules_enabled list (so "empty = all on"
 * does not apply), so append the new keys to keep those modules accessible by
 * default — the super admin can then turn any of them off per hospital.
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
