<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Link a bill to its insurance claim + snapshot the insurer/policy on the bill.
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                if (! Schema::hasColumn('bills', 'insurance_transaction_id')) {
                    $table->uuid('insurance_transaction_id')->nullable()->after('insurance_covered')->index();
                }
                if (! Schema::hasColumn('bills', 'insurer_name')) {
                    $table->string('insurer_name')->nullable()->after('insurance_transaction_id');
                }
                if (! Schema::hasColumn('bills', 'policy_number')) {
                    $table->string('policy_number')->nullable()->after('insurer_name');
                }
            });
        }

        // Relax insurance_transactions columns the model/service don't always supply,
        // so any transaction-create path stops hitting NOT NULL violations.
        if (Schema::hasTable('insurance_transactions')) {
            Schema::table('insurance_transactions', function (Blueprint $table) {
                $table->string('insurer_name')->nullable()->change();
                $table->string('policy_number')->nullable()->change();
                $table->string('member_id')->nullable()->change();
                $table->json('request_payload')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['insurance_transaction_id', 'insurer_name', 'policy_number'] as $c) {
            if (Schema::hasColumn('bills', $c)) {
                Schema::table('bills', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
