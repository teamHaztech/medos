<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
                continue;
            }
            if (! in_array('voice_calls', $enabled, true)) {
                $enabled[] = 'voice_calls';
                DB::table('hospitals')->where('id', $h->id)->update(['modules_enabled' => json_encode($enabled)]);
            }
        }
    }

    public function down(): void {}
};
