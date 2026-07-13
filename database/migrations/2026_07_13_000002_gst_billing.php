<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-service GST rate + HSN/SAC code (rate card is the source of truth).
        if (Schema::hasTable('service_charges')) {
            Schema::table('service_charges', function (Blueprint $table) {
                if (! Schema::hasColumn('service_charges', 'gst_rate')) {
                    $table->decimal('gst_rate', 5, 2)->default(0)->after('is_taxable');
                }
                if (! Schema::hasColumn('service_charges', 'hsn_sac')) {
                    $table->string('hsn_sac', 20)->nullable()->after('gst_rate');
                }
            });
        }

        // Carry the GST rate + HSN/SAC onto each captured charge line.
        if (Schema::hasTable('charge_items')) {
            Schema::table('charge_items', function (Blueprint $table) {
                if (! Schema::hasColumn('charge_items', 'gst_rate')) {
                    $table->decimal('gst_rate', 5, 2)->default(0)->after('is_taxable');
                }
                if (! Schema::hasColumn('charge_items', 'hsn_sac')) {
                    $table->string('hsn_sac', 20)->nullable()->after('gst_rate');
                }
            });
        }

        // CGST / SGST / IGST breakdown on the bill (tax_amount stays the total GST).
        if (Schema::hasTable('bills')) {
            Schema::table('bills', function (Blueprint $table) {
                foreach (['cgst_amount', 'sgst_amount', 'igst_amount'] as $col) {
                    if (! Schema::hasColumn('bills', $col)) {
                        $table->decimal($col, 12, 2)->default(0)->after('tax_amount');
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['gst_rate', 'hsn_sac'] as $c) {
            if (Schema::hasColumn('service_charges', $c)) {
                Schema::table('service_charges', fn (Blueprint $t) => $t->dropColumn($c));
            }
            if (Schema::hasColumn('charge_items', $c)) {
                Schema::table('charge_items', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
        foreach (['cgst_amount', 'sgst_amount', 'igst_amount'] as $c) {
            if (Schema::hasColumn('bills', $c)) {
                Schema::table('bills', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
