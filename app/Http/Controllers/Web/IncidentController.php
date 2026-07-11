<?php

namespace App\Http\Controllers\Web;

use App\Modules\Quality\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class IncidentController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        $query = Incident::where('hospital_id', $hid)->with('patient:id,name');
        foreach (['status', 'severity', 'category'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        $incidents = $query->latest('occurred_at')->latest('created_at')->limit(200)->get();

        $base = Incident::where('hospital_id', $hid);
        $counts = [
            'open'    => (clone $base)->whereIn('status', ['reported', 'under_review'])->count(),
            'serious' => (clone $base)->whereIn('severity', ['major', 'sentinel'])->where('status', '!=', 'closed')->count(),
            'closed'  => (clone $base)->where('status', 'closed')->count(),
            'total'   => (clone $base)->count(),
        ];

        return view('quality.incidents', compact('incidents', 'counts'));
    }

    public function store(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'occurred_at'      => 'nullable|date',
            'department'       => 'nullable|string|max:120',
            'category'         => 'required|in:' . implode(',', array_keys(Incident::CATEGORIES)),
            'severity'         => 'required|in:' . implode(',', array_keys(Incident::SEVERITIES)),
            'patient_id'       => 'nullable|uuid',
            'description'      => 'required|string|max:2000',
            'immediate_action' => 'nullable|string|max:2000',
        ]);

        Incident::create([
            'hospital_id'      => $hid,
            'incident_no'      => 'INC-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'reported_by_name' => Auth::user()->name,
            'occurred_at'      => $v['occurred_at'] ?? now(),
            'department'       => $v['department'] ?? null,
            'category'         => $v['category'],
            'severity'         => $v['severity'],
            'patient_id'       => $v['patient_id'] ?? null,
            'description'      => $v['description'],
            'immediate_action' => $v['immediate_action'] ?? null,
            'status'           => 'reported',
        ]);

        return back()->with('success', 'Incident reported.');
    }

    public function update(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'status'           => 'required|in:' . implode(',', array_keys(Incident::STATUSES)),
            'severity'         => 'required|in:' . implode(',', array_keys(Incident::SEVERITIES)),
            'assigned_to_name' => 'nullable|string|max:120',
            'capa'             => 'nullable|string|max:2000',
        ]);
        Incident::where('hospital_id', $hid)->where('id', $id)->update($v);

        return back()->with('success', 'Incident updated.');
    }
}
