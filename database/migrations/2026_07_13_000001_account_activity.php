<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only security log of account activity (login / logout / failed login,
        // and other notable actions). Nullable hospital_id so super-admin and
        // failed-login (unknown user) rows are supported.
        if (! Schema::hasTable('account_activity')) {
            Schema::create('account_activity', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable()->index();
                $table->string('user_name')->nullable();
                $table->string('user_email')->nullable();
                $table->string('role', 40)->nullable();
                $table->uuid('hospital_id')->nullable()->index();
                $table->string('hospital_name')->nullable();
                $table->string('action', 40); // login | logout | failed_login | ...
                $table->string('description')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        // Quick-glance last-login on the user record.
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable();
                }
                if (! Schema::hasColumn('users', 'last_login_ip')) {
                    $table->string('last_login_ip', 64)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_activity');
        foreach (['last_login_at', 'last_login_ip'] as $c) {
            if (Schema::hasColumn('users', $c)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
