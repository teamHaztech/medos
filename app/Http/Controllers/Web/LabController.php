<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LabController extends Controller
{
    public function dashboard()
    {
        $hospitalId = Auth::user()->hospital_id;

        $orders = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereIn('status', ['ordered', 'accepted', 'in_progress', 'completed'])
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderBy('created_at')
            ->with(['patient', 'orderedBy'])
            ->get();

        return view('lab.dashboard', compact('orders'));
    }

    public function collectSample(string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;

        $order->update([
            'status' => 'in_progress',
            'sample_collected_at' => now(),
            'sample_collected_by' => $staffId,
        ]);

        return response()->json(['success' => true]);
    }

    public function showResults(string $id)
    {
        $order = Order::with('patient')->findOrFail($id);

        return view('lab.enter-results', compact('order'));
    }

    public function saveResults(Request $request, string $id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'results' => $request->input('results'),
        ]);

        return redirect()->back()->with('success', 'Results saved successfully.');
    }

    public function verify(string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;

        $order->update([
            'status' => 'completed',
            'verified_by' => $staffId,
            'verified_at' => now(),
            'completed_at' => now(),
        ]);

        // Notify patient — lab results ready
        $testNames = collect($order->items ?? [])->pluck('name')->implode(', ');
        \App\Modules\Core\Services\WhatsAppNotifier::labResultsReady($order->patient_id, $testNames ?: 'Lab tests');

        return response()->json(['success' => true]);
    }
}
