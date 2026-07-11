<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'useful_life_years')) {
                $table->integer('useful_life_years')->nullable();
            }
            if (! Schema::hasColumn('assets', 'salvage_value')) {
                $table->decimal('salvage_value', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('assets')) {
            return;
        }
        Schema::table('assets', function (Blueprint $table) {
            foreach (['useful_life_years', 'salvage_value'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
