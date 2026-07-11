<?php

namespace App\Http\Controllers\Web;

use App\Modules\Housekeeping\Models\HousekeepingLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class HousekeepingController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        $query = HousekeepingLog::where('hospital_id', $hid);
        foreach (['status', 'priority', 'category'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        $logs = $query->latest('created_at')->limit(200)->get();

        $base = HousekeepingLog::where('hospital_id', $hid);
        $counts = [
            'open'        => (clone $base)->where('status', 'open')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'high'        => (clone $base)->where('priority', 'high')->where('status', '!=', 'closed')->count(),
            'closed'      => (clone $base)->where('status', 'closed')->count(),
        ];

        return view('housekeeping.index', compact('logs', 'counts'));
    }

    public function store(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'location'    => 'required|string|max:150',
            'category'    => 'required|in:' . implode(',', array_keys(HousekeepingLog::CATEGORIES)),
            'priority'    => 'required|in:' . implode(',', array_keys(HousekeepingLog::PRIORITIES)),
            'description' => 'required|string|max:1000',
        ]);

        HousekeepingLog::create([
            'hospital_id'      => $hid,
            'location'         => $v['location'],
            'category'         => $v['category'],
            'priority'         => $v['priority'],
            'description'      => $v['description'],
            'status'           => 'open',
            'reported_by_name' => Auth::user()->name,
        ]);

        return back()->with('success', 'Item logged.');
    }

    public function update(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'status'           => 'required|in:' . implode(',', array_keys(HousekeepingLog::STATUSES)),
            'priority'         => 'required|in:' . implode(',', array_keys(HousekeepingLog::PRIORITIES)),
            'assigned_to_name' => 'nullable|string|max:120',
            'closure_notes'    => 'nullable|string|max:1000',
        ]);

        $log = HousekeepingLog::where('hospital_id', $hid)->findOrFail($id);
        $data = $v;
        $data['closed_at'] = $v['status'] === 'closed' ? ($log->closed_at ?? now()) : null;
        $log->update($data);

        return back()->with('success', 'Item updated.');
    }
}
