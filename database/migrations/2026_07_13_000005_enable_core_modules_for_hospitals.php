<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lab / Pharmacy / Inpatient became toggleable modules with route gates. Hospitals
 * that already have an explicit (non-empty) modules_enabled list would otherwise
 * have these newly-gated modules treated as OFF, so back-fill them as enabled.
 * Hospitals with an empty list are unaffected (empty = everything on).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('hospitals')) {
            return;
        }

        $add = ['lab', 'pharmacy', 'inpatient'];

        foreach (DB::table('hospitals')->get(['id', 'modules_enabled']) as $h) {
            $enabled = is_string($h->modules_enabled) ? json_decode($h->modules_enabled, true) : $h->modules_enabled;
            if (! is_array($enabled) || count($enabled) === 0) {
                continue; // empty = all modules on; nothing to do
            }
            $merged = array_values(array_unique(array_merge($enabled, $add)));
            DB::table('hospitals')->where('id', $h->id)->update([
                'modules_enabled' => json_encode($merged),
                'updated_at'      => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive: leave the modules enabled.
    }
};
