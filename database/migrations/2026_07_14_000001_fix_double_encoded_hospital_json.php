<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair hospitals whose `config` / `modules_enabled` were double-encoded.
 *
 * `SuperAdminController::storeHospital()` used to pass `json_encode([...])` into
 * `Hospital::create()`, but the model already casts those columns to 'array' and
 * encodes them once — so the value was stored as a JSON string of a JSON string.
 * On read the cast decodes one level and returns a STRING, which then blew up
 * `in_array()` in AppServiceProvider / `Hospital::isModuleEnabled()` with
 * "Argument #2 must be of type array, string given" — a 500 on every page for
 * that hospital's users.
 *
 * This unwraps any over-encoded value back to a single encode. Idempotent: a
 * correctly-encoded value decodes straight to an array and is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hospitals')) {
            return;
        }

        foreach (DB::table('hospitals')->get(['id', 'config', 'modules_enabled']) as $h) {
            $updates = [];

            foreach (['config', 'modules_enabled'] as $col) {
                $raw = $h->$col;
                if (! is_string($raw) || $raw === '') {
                    continue;
                }

                $decoded = json_decode($raw, true);
                $wasOverEncoded = is_string($decoded);

                // Keep unwrapping until we reach the underlying array (or give up).
                $guard = 0;
                while (is_string($decoded) && $guard++ < 5) {
                    $decoded = json_decode($decoded, true);
                }

                if ($wasOverEncoded && is_array($decoded)) {
                    $updates[$col] = json_encode($decoded);
                }
            }

            if (! empty($updates)) {
                DB::table('hospitals')->where('id', $h->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // No-op: this only repairs corrupted data.
    }
};
