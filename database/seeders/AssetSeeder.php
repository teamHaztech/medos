<?php

namespace Database\Seeders;

use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetMaintenanceLog;
use App\Modules\Asset\Models\AssetWarranty;
use App\Modules\Asset\Models\Vendor;
use App\Modules\Core\Models\Hospital;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $hospital = Hospital::where('name', 'City Care Hospital')->first() ?? Hospital::first();
        if (! $hospital) {
            $this->command?->warn('AssetSeeder: no hospital found, skipping.');
            return;
        }

        $hid = $hospital->id;
        config(['medos.current_hospital_id' => $hid]);

        // Idempotent — don't duplicate or clobber real data.
        if (Asset::where('hospital_id', $hid)->exists()) {
            $this->command?->info('AssetSeeder: assets already present, skipping.');
            return;
        }

        $medtronic = Vendor::create(['hospital_id' => $hid, 'name' => 'Medtronic India', 'contact_person' => 'R. Iyer', 'phone' => '+91 80 4000 1000', 'email' => 'service@medtronic.example', 'service_type' => 'Biomedical']);
        $draeger   = Vendor::create(['hospital_id' => $hid, 'name' => 'Draeger Medical', 'contact_person' => 'S. Khan', 'phone' => '+91 22 5000 2000', 'email' => 'support@draeger.example', 'service_type' => 'Anesthesia/Ventilation']);
        $skanray   = Vendor::create(['hospital_id' => $hid, 'name' => 'Skanray Technologies', 'contact_person' => 'A. Rao', 'phone' => '+91 821 600 3000', 'email' => 'amc@skanray.example', 'service_type' => 'Monitors/Imaging']);

        $now = now();

        $rows = [
            ['OT Table Hydraulic', 'OT Table', 'OTT-001', 'Maquet 1150', 'Maquet', 'OT', 'OT-1', $skanray->id, 'active', 'amc', $now->copy()->addDays(20), '24/7 onsite within 48h'],
            ['Anesthesia Workstation', 'Anesthesia Machine', 'ANS-220', 'Fabius GS', 'Draeger', 'OT', 'OT-1', $draeger->id, 'active', 'cmc', $now->copy()->addDays(75), 'Comprehensive incl. spares'],
            ['ICU Ventilator', 'Ventilator', 'VEN-330', 'Puritan PB980', 'Medtronic', 'ICU', 'ICU-3', $medtronic->id, 'under_maintenance', 'amc', $now->copy()->addDays(5), 'Quarterly PM'],
            ['Multipara Monitor', 'Patient Monitor', 'MON-410', 'Star80', 'Skanray', 'OT', 'OT-2', $skanray->id, 'active', 'manufacturer', $now->copy()->subDays(10), 'Manufacturer warranty (expired)'],
            ['Electrosurgical Unit', 'Electrosurgical Cautery', 'CAU-512', 'ForceTriad', 'Medtronic', 'OT', 'OT-2', $medtronic->id, 'active', null, null, null],
            ['Surgical Light LED', 'OT Light', 'LIT-615', 'PowerLED 700', 'Maquet', 'OT', 'OT-1', null, 'active', 'cmc', $now->copy()->addDays(150), 'CMC 3-year'],
        ];

        foreach ($rows as [$name, $type, $sn, $model, $mfr, $dept, $loc, $vendorId, $status, $wType, $wEnd, $terms]) {
            $asset = Asset::create([
                'hospital_id'   => $hid,
                'asset_name'    => $name,
                'asset_type'    => $type,
                'serial_number' => $sn,
                'model'         => $model,
                'manufacturer'  => $mfr,
                'department'    => $dept,
                'location'      => $loc,
                'purchase_date' => $now->copy()->subMonths(rand(8, 40))->toDateString(),
                'purchase_cost' => rand(150, 1800) * 1000,
                'vendor_id'     => $vendorId,
                'status'        => $status,
            ]);

            if ($wType && $wEnd) {
                AssetWarranty::create([
                    'hospital_id'                 => $hid,
                    'asset_id'                    => $asset->id,
                    'warranty_type'               => $wType,
                    'start_date'                  => $now->copy()->subYear()->toDateString(),
                    'end_date'                    => $wEnd->toDateString(),
                    'vendor_contact'              => 'Service desk',
                    'terms'                       => $terms,
                    'reminder_days_before_expiry' => 30,
                    'is_active'                   => true,
                ]);
            }

            // A couple of maintenance logs; one with an upcoming/overdue next-due date.
            AssetMaintenanceLog::create([
                'hospital_id'      => $hid,
                'asset_id'         => $asset->id,
                'maintenance_type' => 'preventive',
                'performed_by'     => 'Biomedical Dept',
                'date'             => $now->copy()->subMonths(2)->toDateString(),
                'cost'             => rand(2, 20) * 1000,
                'next_due_date'    => $now->copy()->addDays(rand(-5, 40))->toDateString(),
                'notes'            => 'Routine preventive maintenance.',
            ]);
        }

        $this->command?->info('AssetSeeder: seeded ' . count($rows) . ' OT assets with warranties + maintenance.');
    }
}
