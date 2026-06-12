<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds sample OT asset-management data for City Care Hospital so the module is
 * populated on production (seeders don't auto-run on deploy). Idempotent: skips
 * if the hospital already has any assets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets') || ! Schema::hasTable('vendors')) {
            return;
        }

        $hospital = DB::table('hospitals')->where('name', 'City Care Hospital')->first()
            ?? DB::table('hospitals')->first();
        if (! $hospital) {
            return;
        }
        $hid = $hospital->id;

        // Idempotent — don't duplicate or clobber real data.
        if (DB::table('assets')->where('hospital_id', $hid)->exists()) {
            return;
        }

        $now = now();
        $uuid = fn () => (string) Str::uuid();

        // Vendors
        $medtronic = $uuid();
        $draeger   = $uuid();
        $skanray   = $uuid();
        DB::table('vendors')->insert([
            ['id' => $medtronic, 'hospital_id' => $hid, 'name' => 'Medtronic India', 'contact_person' => 'R. Iyer', 'phone' => '+91 80 4000 1000', 'email' => 'service@medtronic.example', 'address' => null, 'service_type' => 'Biomedical', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $draeger, 'hospital_id' => $hid, 'name' => 'Draeger Medical', 'contact_person' => 'S. Khan', 'phone' => '+91 22 5000 2000', 'email' => 'support@draeger.example', 'address' => null, 'service_type' => 'Anesthesia/Ventilation', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $skanray, 'hospital_id' => $hid, 'name' => 'Skanray Technologies', 'contact_person' => 'A. Rao', 'phone' => '+91 821 600 3000', 'email' => 'amc@skanray.example', 'address' => null, 'service_type' => 'Monitors/Imaging', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // [name, type, serial, model, mfr, dept, location, vendor, status, warrantyType, warrantyEndOffsetDays|null, terms, maintNextDueOffset]
        $rows = [
            ['OT Table Hydraulic', 'OT Table', 'OTT-001', 'Maquet 1150', 'Maquet', 'OT', 'OT-1', $skanray, 'active', 'amc', 20, '24/7 onsite within 48h', 25],
            ['Anesthesia Workstation', 'Anesthesia Machine', 'ANS-220', 'Fabius GS', 'Draeger', 'OT', 'OT-1', $draeger, 'active', 'cmc', 75, 'Comprehensive incl. spares', 40],
            ['ICU Ventilator', 'Ventilator', 'VEN-330', 'Puritan PB980', 'Medtronic', 'ICU', 'ICU-3', $medtronic, 'under_maintenance', 'amc', 5, 'Quarterly PM', -3],
            ['Multipara Monitor', 'Patient Monitor', 'MON-410', 'Star80', 'Skanray', 'OT', 'OT-2', $skanray, 'active', 'manufacturer', -10, 'Manufacturer warranty (expired)', 10],
            ['Electrosurgical Unit', 'Electrosurgical Cautery', 'CAU-512', 'ForceTriad', 'Medtronic', 'OT', 'OT-2', $medtronic, 'active', null, null, null, 15],
            ['Surgical Light LED', 'OT Light', 'LIT-615', 'PowerLED 700', 'Maquet', 'OT', 'OT-1', null, 'active', 'cmc', 150, 'CMC 3-year', 60],
        ];

        foreach ($rows as $i => $r) {
            [$name, $type, $sn, $model, $mfr, $dept, $loc, $vendorId, $status, $wType, $wOffset, $terms, $maintOffset] = $r;

            $assetId = $uuid();
            DB::table('assets')->insert([
                'id' => $assetId, 'hospital_id' => $hid,
                'asset_name' => $name, 'asset_type' => $type, 'serial_number' => $sn,
                'model' => $model, 'manufacturer' => $mfr, 'department' => $dept, 'location' => $loc,
                'purchase_date' => $now->copy()->subMonths(8 + $i * 4)->toDateString(),
                'purchase_cost' => (150 + $i * 250) * 1000,
                'vendor_id' => $vendorId, 'status' => $status, 'notes' => null,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($wType && $wOffset !== null) {
                DB::table('asset_warranties')->insert([
                    'id' => $uuid(), 'hospital_id' => $hid, 'asset_id' => $assetId,
                    'warranty_type' => $wType,
                    'start_date' => $now->copy()->subYear()->toDateString(),
                    'end_date' => $now->copy()->addDays($wOffset)->toDateString(),
                    'vendor_contact' => 'Service desk', 'terms' => $terms, 'document_path' => null,
                    'reminder_days_before_expiry' => 30, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            DB::table('asset_maintenance_logs')->insert([
                'id' => $uuid(), 'hospital_id' => $hid, 'asset_id' => $assetId,
                'maintenance_type' => 'preventive', 'performed_by' => 'Biomedical Dept',
                'date' => $now->copy()->subMonths(2)->toDateString(),
                'cost' => (2 + $i * 3) * 1000,
                'next_due_date' => $now->copy()->addDays($maintOffset)->toDateString(),
                'notes' => 'Routine preventive maintenance.',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $hospital = DB::table('hospitals')->where('name', 'City Care Hospital')->first()
            ?? DB::table('hospitals')->first();
        if (! $hospital) {
            return;
        }
        $hid = $hospital->id;

        $serials = ['OTT-001', 'ANS-220', 'VEN-330', 'MON-410', 'CAU-512', 'LIT-615'];
        // Cascades remove warranties + maintenance via FK on asset_id.
        DB::table('assets')->where('hospital_id', $hid)->whereIn('serial_number', $serials)->delete();
        DB::table('vendors')->where('hospital_id', $hid)
            ->whereIn('name', ['Medtronic India', 'Draeger Medical', 'Skanray Technologies'])->delete();
    }
};
