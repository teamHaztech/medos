<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->datetime('discharged_at')->nullable()->after('follow_up_notes');
            $table->text('discharge_notes')->nullable()->after('discharged_at');
        });
    }

    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropColumn(['discharged_at', 'discharge_notes']);
        });
    }
};
