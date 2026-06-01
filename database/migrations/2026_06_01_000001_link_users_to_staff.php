<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill users.staff_id from staff.user_id.
     *
     * Doctors created via Super Admin "+ Add Staff" (and the Healthway importer)
     * only set staff.user_id, never users.staff_id. Since User::staff() resolves
     * through users.staff_id, those doctors logged in to an empty Doctor Console.
     * Link them up so the console works.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'staff_id') || ! Schema::hasColumn('staff', 'user_id')) {
            return;
        }

        DB::table('staff')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->chunkById(200, function ($staffRows) {
                foreach ($staffRows as $s) {
                    DB::table('users')
                        ->where('id', $s->user_id)
                        ->whereNull('staff_id')
                        ->update(['staff_id' => $s->id]);
                }
            });
    }

    public function down(): void
    {
        // No-op: the link is harmless to keep and reversing it could re-break logins.
    }
};
