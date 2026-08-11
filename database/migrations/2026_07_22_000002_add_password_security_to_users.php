<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Password-security columns on users:
 *   - password_changed_at : when the user last set their own password
 *   - must_change_password : force a password change at next login (set by admin
 *     create / password reset). Existing seeded/demo accounts are NOT backfilled,
 *     so the quick-login demo keeps working.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password_changed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $table) {
            foreach (['password_changed_at', 'must_change_password'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
