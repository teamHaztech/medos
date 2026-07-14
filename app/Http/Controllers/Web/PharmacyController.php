<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\RevenueInsights;
use App\Modules\Core\Models\Order;
use App\Modules\Pharmacy\Models\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PharmacyController extends Controller
{
    public function dashboard(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        $orders = Order::where('hospital_id', $hospitalId)
            ->where('type', 'pharmacy')
            ->whereIn('status', ['ordered', 'accepted', 'dispensed'])
            ->with(['patient', 'orderedBy'])
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        $pending = $orders->whereIn('status', ['ordered', 'accepted']);
        $stats = [
            'pending'         => $pending->count(),
            'urgent'          => $pending->whereIn('priority', ['stat', 'urgent'])->count(),
            'dispensed_today' => Order::where('hospital_id', $hospitalId)
                ->where('type', 'pharmacy')->where('status', 'dispensed')
                ->whereDate('updated_at', today())->count(),
            'low_stock'       => PharmacyStock::where('hospital_id', $hospitalId)
                ->where('quantity_available', '<=', 10)->count(),
        ];

        // Polled by the dashboard every few seconds so new prescriptions appear
        // without a manual reload.
        if ($request->wantsJson()) {
            return response()->json(compact('orders', 'stats'));
        }

        return view('pharmacy.dashboard', compact('orders', 'stats'));
    }

    /**
     * Pharmacy business insights — revenue, best-selling medicines and dispensing
     * volume over a day / week / month / year, plus a live inventory valuation.
     * Every figure is derived from the charge-capture ledger (charge_items, source
     * = pharmacy) — the same rows that flow onto patient bills — so the numbers
     * reconcile with billing rather than being estimates.
     */
    public function insights(Request $request, RevenueInsights $insights)
    {
        $hospitalId = Auth::user()->hospital_id;
        $period     = $request->get('period', 'month');
        $sources    = ['pharmacy'];

        $r = $insights->range($period);

        // Revenue + volume for the window and the equal-length previous window.
        $now  = $insights->totals($hospitalId, $sources, $r['start'], $r['end']);
        $prev = $insights->totals($hospitalId, $sources, $r['prevStart'], $r['prevEnd']);

        // Dispensed prescriptions (whole orders) — distinct from medicine lines.
        $rxThis = Order::where('hospital_id', $hospitalId)
            ->where('type', 'pharmacy')->where('status', 'dispensed')
            ->whereBetween('completed_at', [$r['start'], $r['end']])->count();
        $rxPrev = Order::where('hospital_id', $hospitalId)
            ->where('type', 'pharmacy')->where('status', 'dispensed')
            ->whereBetween('completed_at', [$r['prevStart'], $r['prevEnd']])->count();

        $kpis = [
            'revenue'        => $now['revenue'],
            'revenue_change' => RevenueInsights::pctChange($now['revenue'], $prev['revenue']),
            'units'          => $now['units'],
            'units_change'   => RevenueInsights::pctChange($now['units'], $prev['units']),
            'rx'             => $rxThis,
            'rx_change'      => RevenueInsights::pctChange($rxThis, $rxPrev),
            'avg_rx'         => $rxThis > 0 ? round($now['revenue'] / $rxThis, 2) : 0.0,
        ];

        $trend = $insights->trend($hospitalId, $sources, $r['start'], $r['end'], $r['granularity'], $r['labelFormat']);

        // Best sellers: every dispensed medicine line, ranked two ways.
        $items      = $insights->items($hospitalId, $sources, $r['start'], $r['end']);
        $byRevenue  = $items->sortByDesc('revenue')->take(10)->values();
        $byVolume   = $items->sortByDesc('units')->take(10)->values();

        // Live inventory valuation + risk (current snapshot, not window-scoped).
        $inventory = $this->inventorySnapshot($hospitalId);

        return view('pharmacy.insights', [
            'period'      => $period,
            'periodLabel' => $r['label'],
            'kpis'        => $kpis,
            'trend'       => $trend,
            'byRevenue'   => $byRevenue,
            'byVolume'    => $byVolume,
            'inventory'   => $inventory,
        ]);
    }

    /** Current stock valuation (at cost + retail) and stock-risk counts/lists. */
    private function inventorySnapshot(string $hospitalId): array
    {
        if (! Schema::hasTable('pharmacy_stock')) {
            return ['cost_value' => 0, 'retail_value' => 0, 'low' => 0, 'out' => 0, 'expiring' => collect()];
        }

        $agg = PharmacyStock::where('hospital_id', $hospitalId)
            ->selectRaw('
                COALESCE(SUM(quantity_available * purchase_price),0) as cost_value,
                COALESCE(SUM(quantity_available * selling_price),0)  as retail_value,
                SUM(CASE WHEN quantity_available > 0 AND quantity_available <= 10 THEN 1 ELSE 0 END) as low,
                SUM(CASE WHEN quantity_available <= 0 THEN 1 ELSE 0 END) as out
            ')->first();

        // Batches expiring within 90 days that still hold stock — money at risk.
        $expiring = PharmacyStock::where('pharmacy_stock.hospital_id', $hospitalId)
            ->where('pharmacy_stock.quantity_available', '>', 0)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [today()->toDateString(), today()->addDays(90)->toDateString()])
            ->join('medicines', 'pharmacy_stock.medicine_id', '=', 'medicines.id')
            ->select('medicines.name as medicine_name', 'pharmacy_stock.batch_number',
                'pharmacy_stock.quantity_available', 'pharmacy_stock.expiry_date')
            ->orderBy('pharmacy_stock.expiry_date')
            ->limit(8)
            ->get();

        return [
            'cost_value'   => round((float) ($agg->cost_value ?? 0), 2),
            'retail_value' => round((float) ($agg->retail_value ?? 0), 2),
            'low'          => (int) ($agg->low ?? 0),
            'out'          => (int) ($agg->out ?? 0),
            'expiring'     => $expiring,
        ];
    }

    public function dispense(string $id)
    {
        $order = Order::findOrFail($id);

        $status = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
        if ($status === 'dispensed') {
            return response()->json(['success' => true, 'already' => true]);
        }

        $this->decrementStockFor($order);

        $order->update([
            'status' => 'dispensed',
            'completed_at' => now(),
        ]);

        // Capture the dispensed medicines as pharmacy charges at selling price, then
        // fold them into the visit's running bill. Non-fatal — a billing hiccup must
        // never block dispensing.
        try {
            $cc = app(\App\Modules\Billing\Services\ChargeCapture::class);
            $cc->capturePharmacyDispense($order, Auth::user()->name);
            if ($order->encounter_id) {
                $encounter = \App\Modules\Patient\Models\Encounter::find($order->encounter_id);
                if ($encounter) {
                    $cc->compileBill($encounter, Auth::user()->name);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[Pharmacy] dispense charge capture failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    /** Draw the dispensed medicines down from inventory (FEFO across batches). */
    private function decrementStockFor(Order $order): void
    {
        try {
            $items = is_array($order->items) ? $order->items : (json_decode($order->items ?? '[]', true) ?: []);
            foreach ($items as $item) {
                $name = is_array($item) ? ($item['name'] ?? null) : null;
                if (! $name) {
                    continue;
                }
                $qty = (int) (is_array($item) ? ($item['quantity'] ?? $item['qty'] ?? 1) : 1);
                $qty = max(1, $qty);

                $medicineId = DB::table('medicines')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->value('id');
                if (! $medicineId) {
                    continue;
                }

                // First-expiry-first-out: consume soonest-expiring available batches first.
                $batches = PharmacyStock::where('hospital_id', $order->hospital_id)
                    ->where('medicine_id', $medicineId)
                    ->where('quantity_available', '>', 0)
                    ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                    ->get();

                foreach ($batches as $batch) {
                    if ($qty <= 0) {
                        break;
                    }
                    $take = min($qty, (int) $batch->quantity_available);
                    $batch->update(['quantity_available' => $batch->quantity_available - $take]);
                    $qty -= $take;
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('[Pharmacy] stock decrement on dispense failed: ' . $e->getMessage());
        }
    }

    public function stock()
    {
        $hospitalId = Auth::user()->hospital_id;

        $stocks = PharmacyStock::where('pharmacy_stock.hospital_id', $hospitalId)
            ->join('medicines', 'pharmacy_stock.medicine_id', '=', 'medicines.id')
            ->select('pharmacy_stock.*', 'medicines.name as medicine_name', 'medicines.generic_name', 'medicines.form')
            ->orderBy('medicines.name')
            ->get();

        $medicines = DB::table('medicines')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'generic_name', 'form']);

        return view('pharmacy.stock', compact('stocks', 'medicines'));
    }

    public function addStock(Request $request)
    {
        $request->validate([
            'medicine_id' => 'required|exists:medicines,id',
            'batch_number' => 'required|string',
            'expiry_date' => 'required|date',
            'quantity_total' => 'required|integer|min:1',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'supplier' => 'required|string',
        ]);

        PharmacyStock::create([
            'hospital_id' => Auth::user()->hospital_id,
            'medicine_id' => $request->medicine_id,
            'batch_number' => $request->batch_number,
            'expiry_date' => $request->expiry_date,
            'quantity_total' => $request->quantity_total,
            'quantity_available' => $request->quantity_total,
            'purchase_price' => $request->purchase_price,
            'selling_price' => $request->selling_price,
            'supplier' => $request->supplier,
            'received_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Stock added successfully.');
    }

    public function updateStock(Request $request, string $id)
    {
        $stock = PharmacyStock::findOrFail($id);

        $stock->update([
            'quantity_available' => $request->input('quantity_available', $stock->quantity_available),
            'selling_price' => $request->input('selling_price', $stock->selling_price),
        ]);

        return redirect()->back()->with('success', 'Stock updated successfully.');
    }
}
