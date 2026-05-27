<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Order;
use App\Modules\Patient\Models\Patient;
use App\Modules\Core\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LabController extends Controller
{
    public function dashboard(Request $request)
    {
        $hospitalId = Auth::user()->hospital_id;
        $dateFilter = $request->get('date', 'today');

        $query = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereIn('status', ['ordered', 'accepted', 'in_progress', 'completed']);

        if ($dateFilter === 'today') {
            $query->whereDate('created_at', today());
        } elseif ($dateFilter === 'week') {
            $query->where('created_at', '>=', now()->startOfWeek());
        }

        $orders = $query
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 0 WHEN priority = 'urgent' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'ordered' THEN 0 WHEN status = 'accepted' THEN 1 WHEN status = 'in_progress' THEN 2 ELSE 3 END")
            ->orderBy('created_at')
            ->with(['patient', 'orderedBy', 'collectedBy', 'verifiedBy'])
            ->get()
            ->map(function ($order) {
                // Calculate TAT
                $order->tat_minutes = $order->created_at ? now()->diffInMinutes($order->created_at) : 0;
                $order->tat_display = $this->formatTat($order->tat_minutes);
                $order->tat_color = $this->tatColor($order);
                return $order;
            });

        // Stats
        $todayBase = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereDate('created_at', today());

        $stats = [
            'total' => (clone $todayBase)->count(),
            'pending' => (clone $todayBase)->whereIn('status', ['ordered', 'accepted'])->count(),
            'in_progress' => (clone $todayBase)->where('status', 'in_progress')->count(),
            'completed' => (clone $todayBase)->where('status', 'completed')->count(),
            'stat_urgent' => (clone $todayBase)->whereIn('priority', ['stat', 'urgent'])->whereNotIn('status', ['completed'])->count(),
            'critical' => (clone $todayBase)->where('has_critical', true)->where('critical_acknowledged', false)->count(),
        ];

        // Avg TAT for completed orders today
        $completedToday = Order::where('hospital_id', $hospitalId)
            ->whereIn('type', ['lab', 'imaging'])
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->whereNotNull('completed_at')
            ->get();

        $avgTat = 0;
        if ($completedToday->count() > 0) {
            $totalMinutes = $completedToday->sum(fn($o) => Carbon::parse($o->created_at)->diffInMinutes($o->completed_at));
            $avgTat = round($totalMinutes / $completedToday->count());
        }
        $stats['avg_tat'] = $this->formatTat($avgTat);

        if ($request->wantsJson()) {
            return response()->json(compact('orders', 'stats'));
        }

        return view('lab.dashboard', compact('orders', 'stats', 'dateFilter'));
    }

    public function collectSample(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;

        // Generate sample ID: LAB-YYYYMMDD-XXXXX
        $todayCount = Order::where('hospital_id', $order->hospital_id)
            ->whereIn('type', ['lab', 'imaging'])
            ->whereNotNull('sample_id')
            ->whereDate('created_at', today())
            ->count();
        $sampleId = 'LAB-' . now()->format('Ymd') . '-' . str_pad($todayCount + 1, 5, '0', STR_PAD_LEFT);

        // Determine sample type from test items
        $items = $order->items ?? [];
        $sampleType = $request->input('sample_type', $this->guessSampleType($items));
        $containerType = $request->input('container_type', $this->guessContainerType($sampleType));

        $order->update([
            'status' => 'in_progress',
            'sample_id' => $sampleId,
            'sample_type' => $sampleType,
            'container_type' => $containerType,
            'collection_location' => $request->input('location', 'opd'),
            'lab_status' => 'collected',
            'sample_collected_at' => now(),
            'sample_collected_by' => $staffId,
        ]);

        $this->logEvent($order, 'collected', $staffId);

        return response()->json(['success' => true, 'sample_id' => $sampleId]);
    }

    public function updateLabStatus(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;
        $newStatus = $request->input('lab_status');

        $allowed = ['transported', 'received', 'processing', 'result_entry'];
        if (!in_array($newStatus, $allowed)) {
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $updates = ['lab_status' => $newStatus];
        $timestampField = $newStatus . '_at';
        if (in_array($timestampField, ['transported_at', 'received_at', 'processing_at'])) {
            $updates[$timestampField] = now();
        }

        $order->update($updates);
        $this->logEvent($order, $newStatus, $staffId, $request->input('notes'));

        return response()->json(['success' => true]);
    }

    public function showResults(string $id)
    {
        $order = Order::with(['patient', 'orderedBy'])->findOrFail($id);

        // Get reference ranges from available_tests catalog
        $testNames = collect($order->items ?? [])->pluck('name')->toArray();
        $testCatalog = DB::table('available_tests')
            ->whereIn('name', $testNames)
            ->get(['name', 'unit', 'reference_ranges', 'critical_values'])
            ->keyBy('name');

        // Get patient's previous results for these tests (trend comparison)
        $previousOrders = Order::where('patient_id', $order->patient_id)
            ->whereIn('type', ['lab', 'imaging'])
            ->where('status', 'completed')
            ->where('id', '!=', $order->id)
            ->whereNotNull('results')
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get(['items', 'results', 'completed_at']);

        $previousResults = [];
        foreach ($previousOrders as $prev) {
            $prevResults = $prev->results ?? [];
            foreach ($prevResults as $r) {
                $testName = $r['test_name'] ?? '';
                if ($testName && !isset($previousResults[$testName])) {
                    $previousResults[$testName] = [
                        'value' => $r['value'] ?? '-',
                        'date' => Carbon::parse($prev->completed_at)->format('d M Y'),
                    ];
                }
            }
        }

        return view('lab.enter-results', compact('order', 'testCatalog', 'previousResults'));
    }

    public function saveResults(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $results = $request->input('results', []);

        // Check for critical values
        $hasCritical = false;
        $testNames = collect($order->items ?? [])->pluck('name')->toArray();
        $testCatalog = DB::table('available_tests')
            ->whereIn('name', $testNames)
            ->get(['name', 'critical_values'])
            ->keyBy('name');

        foreach ($results as &$result) {
            $testName = $result['test_name'] ?? '';
            $value = is_numeric($result['value'] ?? '') ? floatval($result['value']) : null;
            $catalog = $testCatalog[$testName] ?? null;

            if ($value !== null && $catalog) {
                $criticals = is_string($catalog->critical_values) ? json_decode($catalog->critical_values, true) : ($catalog->critical_values ?? []);
                if (!empty($criticals)) {
                    $critLow = $criticals['low'] ?? null;
                    $critHigh = $criticals['high'] ?? null;

                    if ($critLow !== null && $value < $critLow) {
                        $result['critical'] = 'low';
                        $hasCritical = true;
                        $this->logCriticalAlert($order, $testName, $result['value'], 'low', $critLow);
                    } elseif ($critHigh !== null && $value > $critHigh) {
                        $result['critical'] = 'high';
                        $hasCritical = true;
                        $this->logCriticalAlert($order, $testName, $result['value'], 'high', $critHigh);
                    }
                }
            }
        }

        $order->update([
            'results' => $results,
            'has_critical' => $hasCritical,
            'lab_status' => 'result_entry',
            'result_entered_at' => now(),
        ]);

        $this->logEvent($order, 'result_entry', Auth::user()->staff?->id);

        $msg = 'Results saved.';
        if ($hasCritical) {
            $msg .= ' ⚠️ CRITICAL VALUES DETECTED — doctor has been notified.';
        }

        return redirect()->back()->with('success', $msg);
    }

    public function verify(string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;

        // Block verify if critical values not acknowledged
        if ($order->has_critical && !$order->critical_acknowledged) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot verify — critical values must be acknowledged by a doctor first.',
            ], 422);
        }

        $order->update([
            'status' => 'completed',
            'lab_status' => 'verified',
            'verified_by' => $staffId,
            'verified_at' => now(),
            'completed_at' => now(),
            'released_at' => now(),
            'released_by' => $staffId,
        ]);

        $this->logEvent($order, 'verified', $staffId);
        $this->logEvent($order, 'released', $staffId);

        // Notify patient
        $testNames = collect($order->items ?? [])->pluck('name')->implode(', ');
        \App\Modules\Core\Services\WhatsAppNotifier::labResultsReady($order->patient_id, $testNames ?: 'Lab tests');

        return response()->json(['success' => true]);
    }

    public function acknowledgeCritical(Request $request, string $id)
    {
        $order = Order::findOrFail($id);
        $staffId = Auth::user()->staff?->id;

        $order->update([
            'critical_acknowledged' => true,
            'critical_acknowledged_by' => $staffId,
            'critical_acknowledged_at' => now(),
        ]);

        // Update alert logs
        DB::table('critical_alert_logs')
            ->where('order_id', $order->id)
            ->where('acknowledged', false)
            ->update([
                'acknowledged' => true,
                'acknowledged_by' => $staffId,
                'acknowledged_at' => now(),
                'action_taken' => $request->input('action', 'Acknowledged and reviewed'),
            ]);

        $this->logEvent($order, 'critical_acknowledged', $staffId, $request->input('action'));

        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function logEvent(Order $order, string $event, ?string $staffId, ?string $notes = null): void
    {
        try {
            DB::table('sample_events')->insert([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'hospital_id' => $order->hospital_id,
                'event' => $event,
                'performed_by' => $staffId,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Don't fail the main action if logging fails
        }
    }

    private function logCriticalAlert(Order $order, string $testName, string $value, string $type, $threshold): void
    {
        try {
            DB::table('critical_alert_logs')->insert([
                'id' => Str::uuid()->toString(),
                'order_id' => $order->id,
                'hospital_id' => $order->hospital_id,
                'patient_id' => $order->patient_id,
                'test_name' => $testName,
                'value' => $value,
                'critical_type' => $type,
                'threshold' => (string) $threshold,
                'notified_doctor_id' => $order->ordered_by,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    private function guessSampleType(array $items): string
    {
        $names = strtolower(implode(' ', array_column($items, 'name')));
        if (str_contains($names, 'urine') || str_contains($names, 'urinalysis')) return 'urine';
        if (str_contains($names, 'stool') || str_contains($names, 'occult')) return 'stool';
        if (str_contains($names, 'swab') || str_contains($names, 'culture')) return 'swab';
        if (str_contains($names, 'csf') || str_contains($names, 'spinal')) return 'csf';
        if (str_contains($names, 'sputum')) return 'sputum';
        return 'blood';
    }

    private function guessContainerType(string $sampleType): string
    {
        return match ($sampleType) {
            'blood' => 'EDTA',
            'urine' => 'urine_cup',
            'stool' => 'stool_container',
            'swab' => 'swab_tube',
            'csf' => 'sterile_tube',
            'sputum' => 'sputum_cup',
            default => 'plain',
        };
    }

    private function formatTat(int $minutes): string
    {
        if ($minutes < 60) return $minutes . 'm';
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours . 'h ' . ($mins > 0 ? $mins . 'm' : '');
    }

    private function tatColor(Order $order): string
    {
        if (in_array(is_object($order->status) ? $order->status->value : $order->status, ['completed'])) return 'green';
        $minutes = $order->tat_minutes ?? 0;
        $priority = is_object($order->priority) ? $order->priority->value : ($order->priority ?? 'routine');

        $thresholds = match ($priority) {
            'stat' => [30, 60],    // yellow at 30m, red at 60m
            'urgent' => [60, 120], // yellow at 1h, red at 2h
            default => [120, 240], // yellow at 2h, red at 4h
        };

        if ($minutes >= $thresholds[1]) return 'red';
        if ($minutes >= $thresholds[0]) return 'yellow';
        return 'green';
    }
}
