<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Order;
use App\Modules\Pharmacy\Models\PharmacyStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Polled by the dashboard every few seconds so new prescriptions appear
        // without a manual reload.
        if ($request->wantsJson()) {
            return response()->json(compact('orders'));
        }

        return view('pharmacy.dashboard', compact('orders'));
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
