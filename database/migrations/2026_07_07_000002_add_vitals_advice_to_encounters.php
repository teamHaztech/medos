<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('encounters')) {
            return;
        }

        Schema::table('encounters', function (Blueprint $table) {
            if (! Schema::hasColumn('encounters', 'vitals')) {
                $table->json('vitals')->nullable()->after('soap_notes');
            }
            if (! Schema::hasColumn('encounters', 'patient_advice')) {
                $table->json('patient_advice')->nullable()->after('vitals');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('encounters')) {
            return;
        }

        Schema::table('encounters', function (Blueprint $table) {
            foreach (['vitals', 'patient_advice'] as $col) {
                if (Schema::hasColumn('encounters', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
