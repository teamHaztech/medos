<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Inpatient\Models\Bed;
use App\Modules\Inpatient\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WardController extends Controller
{
    private function hid(): string
    {
        $hid = Auth::user()->hospital_id;
        config(['medos.current_hospital_id' => $hid]);

        return $hid;
    }

    public function index()
    {
        $this->hid();
        $wards = Ward::where('is_active', true)
            ->with(['beds' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        return view('ip.wards', compact('wards'));
    }

    public function storeWard(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'name'               => 'required|string|max:255',
            'ward_type'          => 'nullable|string|max:50',
            'daily_rate'         => 'nullable|numeric|min:0|max:1000000',
            'nursing_daily_rate' => 'nullable|numeric|min:0|max:1000000',
            'floor'              => 'nullable|string|max:50',
            'bed_count'          => 'nullable|integer|min:0|max:200',
            'bed_prefix'         => 'nullable|string|max:10',
        ]);

        $ward = Ward::create([
            'hospital_id'        => $hid,
            'name'               => $v['name'],
            'ward_type'          => $v['ward_type'] ?? null,
            'daily_rate'         => $v['daily_rate'] ?? 0,
            'nursing_daily_rate' => $v['nursing_daily_rate'] ?? 0,
            'floor'              => $v['floor'] ?? null,
        ]);

        // Optionally create N beds up front.
        $count = (int) ($v['bed_count'] ?? 0);
        $prefix = $v['bed_prefix'] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $v['name']), 0, 1)) ?: 'B';
        for ($i = 1; $i <= $count; $i++) {
            Bed::create([
                'hospital_id' => $hid,
                'ward_id'     => $ward->id,
                'bed_number'  => $prefix . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'status'      => 'available',
            ]);
        }

        return redirect()->route('web.ip.wards')->with('success', 'Ward "' . $ward->name . '" created' . ($count ? " with {$count} beds." : '.'));
    }

    public function updateWard(Request $request, string $id)
    {
        $this->hid();
        $ward = Ward::findOrFail($id);
        $ward->update($request->validate([
            'name'               => 'required|string|max:255',
            'ward_type'          => 'nullable|string|max:50',
            'daily_rate'         => 'nullable|numeric|min:0|max:1000000',
            'nursing_daily_rate' => 'nullable|numeric|min:0|max:1000000',
            'floor'              => 'nullable|string|max:50',
        ]));

        return redirect()->route('web.ip.wards')->with('success', 'Ward updated.');
    }

    public function destroyWard(string $id)
    {
        $this->hid();
        $ward = Ward::withCount(['beds as occupied' => fn ($q) => $q->where('status', 'occupied')])->findOrFail($id);
        if ($ward->occupied > 0) {
            return redirect()->route('web.ip.wards')->with('error', 'Cannot remove a ward with occupied beds.');
        }
        $ward->update(['is_active' => false]);
        Bed::where('ward_id', $ward->id)->update(['is_active' => false]);

        return redirect()->route('web.ip.wards')->with('success', 'Ward removed.');
    }

    public function storeBed(Request $request, string $wardId)
    {
        $hid = $this->hid();
        $ward = Ward::findOrFail($wardId);
        $v = $request->validate(['bed_number' => 'required|string|max:30']);

        Bed::create([
            'hospital_id' => $hid,
            'ward_id'     => $ward->id,
            'bed_number'  => $v['bed_number'],
            'status'      => 'available',
        ]);

        return redirect()->route('web.ip.wards')->with('success', 'Bed added.');
    }

    public function updateBed(Request $request, string $id)
    {
        $this->hid();
        $bed = Bed::findOrFail($id);
        $v = $request->validate(['status' => 'required|in:available,maintenance']);

        if ($bed->status === 'occupied') {
            return redirect()->route('web.ip.wards')->with('error', 'Bed is occupied — discharge/transfer the patient first.');
        }
        $bed->update(['status' => $v['status']]);

        return redirect()->route('web.ip.wards')->with('success', 'Bed updated.');
    }

    public function destroyBed(string $id)
    {
        $this->hid();
        $bed = Bed::findOrFail($id);
        if ($bed->status === 'occupied') {
            return redirect()->route('web.ip.wards')->with('error', 'Cannot remove an occupied bed.');
        }
        $bed->update(['is_active' => false]);

        return redirect()->route('web.ip.wards')->with('success', 'Bed removed.');
    }
}
