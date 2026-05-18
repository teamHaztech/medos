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
    public function dashboard()
    {
        $hospitalId = Auth::user()->hospital_id;

        $orders = Order::where('hospital_id', $hospitalId)
            ->where('type', 'pharmacy')
            ->whereIn('status', ['ordered', 'accepted', 'dispensed'])
            ->with(['patient', 'orderedBy'])
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->get();

        return view('pharmacy.dashboard', compact('orders'));
    }

    public function dispense(string $id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'status' => 'dispensed',
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true]);
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
