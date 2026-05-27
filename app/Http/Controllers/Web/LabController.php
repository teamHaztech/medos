<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LabController extends Controller
{
    public function dashboard(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;

        // Date filter: default today, allow 'all' or specific date
        $dateFilter = $request->get('date', 'today');

        $query = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereIn('status', ['ordered', 'accepted', 'in_progress', 'completed']);

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        } elseif ($dateFilter !== 'all') {
            $query->whereDate('created_at', $dateFilter);
        }

        $orders = $query
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'ordered' THEN 0 WHEN status = 'accepted' THEN 1 WHEN status = 'in_progress' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->with(['patient', 'orderedBy', 'collectedBy', 'verifiedBy'])
            ->get();

        // Stats
        $todayAll = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereDate('created_at', today());

        $stats = [
            'pending' => (clone $todayAll)->whereIn('status', ['ordered', 'accepted'])->count(),
            'in_progress' => (clone $todayAll)->where('status', 'in_progress')->count(),
            'completed' => (clone $todayAll)->where('status', 'completed')->count(),
            'stat_urgent' => (clone $todayAll)->whereIn('priority', ['stat', 'urgent'])->whereNotIn('status', ['completed'])->count(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'orders' => $orders,
                'stats' => $stats,
            ]);
        }

        return view('lab.dashboard', compact('orders', 'stats', 'dateFilter'));
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
        $order = Order::with(['patient', 'orderedBy'])->findOrFail($id);

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
