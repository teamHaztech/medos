<?php

use App\Modules\Billing\Services\ChargeCapture;
use App\Modules\Core\Models\Order;
use App\Modules\Patient\Models\Encounter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill bills for lab tests that patients self-booked through the AI
 * receptionist BEFORE the billing fix. The old chat path created lab/imaging/
 * procedure orders with encounter_id = null and never captured a charge, so
 * they never produced a bill (unlike doctor-ordered labs, consultations, and
 * pharmacy). This walks every orphan self-booked lab order — an order with no
 * encounter — reconstructs its booking (grouped by the LAB-xxxx token), attaches
 * a lightweight walk-in encounter, captures the test charges, and compiles a
 * payable bill. The exact thing ChatController::handleLabConfirm now does live.
 *
 * Idempotent: only orders with encounter_id = null are touched, and once
 * processed they carry an encounter_id, so a re-run (deploy.php) skips them.
 * ChargeCapture::captureOrder is itself idempotent per (source, source_ref), so
 * any charges an API self-booking already posted are updated in place, not
 * duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'encounter_id')
            || ! Schema::hasTable('charge_items') || ! Schema::hasTable('bills')) {
            return;
        }

        $cc = app(ChargeCapture::class);

        $orphans = Order::query()
            ->withoutGlobalScopes()
            ->whereIn('type', ['lab', 'imaging', 'procedure'])
            ->whereNull('encounter_id')
            ->whereNotNull('patient_id')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'cancelled');
            })
            ->get();

        // Group by booking so lab + imaging ordered together share one bill.
        // Key on the token (notes) when present, else the order's own id.
        $groups = $orphans->groupBy(fn (Order $o) => $o->hospital_id . '|' . $o->patient_id . '|' . ($o->notes ?: $o->id));

        foreach ($groups as $orders) {
            try {
                DB::transaction(function () use ($orders, $cc) {
                    /** @var Order $first */
                    $first = $orders->first();

                    $encounter = Encounter::create([
                        'id'               => Str::uuid()->toString(),
                        'hospital_id'      => $first->hospital_id,
                        'patient_id'       => $first->patient_id,
                        'encounter_number' => 'ENC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
                        'type'             => 'consultation',
                        'status'           => 'completed',
                        'channel'          => 'walk_in',
                        'intake_data'      => ['source' => 'lab_backfill'],
                    ]);

                    foreach ($orders as $order) {
                        $order->encounter_id = $encounter->id;
                        $order->save();
                        $cc->captureOrder($order, 'Self-booked (backfill)');
                    }

                    $cc->compileBill($encounter, 'Self-booked (backfill)');
                });
            } catch (\Throwable $e) {
                Log::warning('[LabBillBackfill] group failed: ' . $e->getMessage(), [
                    'order_ids' => $orders->pluck('id')->all(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-reversible: the created encounters/bills become normal billing
        // records once patients are charged. Leave them in place.
    }
};
