<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->datetime('sample_collected_at')->nullable()->after('completed_at');
            $table->uuid('sample_collected_by')->nullable()->after('sample_collected_at');
            $table->uuid('verified_by')->nullable()->after('sample_collected_by');
            $table->datetime('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['sample_collected_at', 'sample_collected_by', 'verified_by', 'verified_at']);
        });
    }
};
