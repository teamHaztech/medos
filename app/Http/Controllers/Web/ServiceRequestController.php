<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Asset\Models\Asset;
use App\Modules\Asset\Models\AssetMaintenanceLog;
use App\Modules\Asset\Models\AssetServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ServiceRequestController extends Controller
{
    private function hid(): string
    {
        $hid = Auth::user()->hospital_id;
        config(['medos.current_hospital_id' => $hid]);

        return $hid;
    }

    /** Tickets list with status / priority filters. */
    public function index(Request $request)
    {
        $this->hid();

        $query = AssetServiceRequest::with('asset');
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        $tickets = $query
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
            ->orderByDesc('reported_at')
            ->get();

        $filters  = $request->only(['status', 'priority']);
        $statuses = AssetServiceRequest::STATUSES;
        $priorities = AssetServiceRequest::PRIORITIES;

        return view('admin.assets.tickets', compact('tickets', 'filters', 'statuses', 'priorities'));
    }

    /** Raise a ticket against an asset. */
    public function store(Request $request, string $assetId)
    {
        $hid = $this->hid();
        $asset = Asset::findOrFail($assetId);

        $v = $request->validate([
            'issue'       => 'required|string|max:1000',
            'priority'    => 'required|in:low,normal,high,critical',
            'reported_by' => 'nullable|string|max:255',
        ]);

        AssetServiceRequest::create([
            'hospital_id' => $hid,
            'asset_id'    => $asset->id,
            'reported_by' => ($v['reported_by'] ?? null) ?: (Auth::user()->name ?? 'Staff'),
            'reported_at' => now(),
            'issue'       => $v['issue'],
            'priority'    => $v['priority'],
            'status'      => 'open',
        ]);

        return redirect()->back()->with('success', 'Service request logged.');
    }

    /**
     * Update a ticket's status/assignment. When it moves to resolved/closed,
     * stamp resolved_at and auto-create a corrective maintenance log.
     */
    public function update(Request $request, string $id)
    {
        $this->hid();
        $ticket = AssetServiceRequest::findOrFail($id);

        $v = $request->validate([
            'status'           => 'required|in:open,in_progress,resolved,closed',
            'assigned_to'      => 'nullable|string|max:255',
            'priority'         => 'nullable|in:low,normal,high,critical',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $wasOpen = $ticket->isOpen();
        $nowResolved = in_array($v['status'], ['resolved', 'closed'], true);

        $ticket->fill([
            'status'           => $v['status'],
            'assigned_to'      => $v['assigned_to'] ?? $ticket->assigned_to,
            'priority'         => $v['priority'] ?? $ticket->priority,
            'resolution_notes' => $v['resolution_notes'] ?? $ticket->resolution_notes,
        ]);

        if ($nowResolved && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();

            // Auto-log a corrective maintenance entry for the fix.
            AssetMaintenanceLog::create([
                'hospital_id'      => $ticket->hospital_id,
                'asset_id'         => $ticket->asset_id,
                'maintenance_type' => 'corrective',
                'performed_by'     => $ticket->assigned_to ?: 'Service team',
                'date'             => now()->toDateString(),
                'notes'            => 'Resolved service request: ' . ($ticket->resolution_notes ?: $ticket->issue),
            ]);
        }

        $ticket->save();

        return redirect()->back()->with('success', 'Service request updated.');
    }
}
