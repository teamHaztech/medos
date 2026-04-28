<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\Hospital;
use App\Modules\Core\Services\RegionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function index()
    {
        $hospitals = Hospital::orderBy('country')->orderBy('name')->get();
        $regions = config('regions');
        return view('superadmin.index', compact('hospitals', 'regions'));
    }

    public function createHospital()
    {
        $regions = config('regions');
        return view('superadmin.hospital-form', ['hospital' => null, 'regions' => $regions]);
    }

    public function storeHospital(Request $request)
    {
        $v = $request->validate([
            'name'    => 'required|string|max:255',
            'slug'    => 'required|string|max:100|alpha_dash|unique:hospitals,slug',
            'country' => 'required|in:IN,AE',
            'city'    => 'required|string|max:100',
            'state'   => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:20',
            'email'   => 'nullable|email|max:255',
        ]);

        $region = RegionService::get($v['country']);

        Hospital::create([
            'id'                  => Str::uuid()->toString(),
            'name'                => $v['name'],
            'slug'                => $v['slug'],
            'country'             => $v['country'],
            'city'                => $v['city'],
            'state'               => $v['state'] ?? null,
            'address'             => $v['address'] ?? null,
            'phone'               => $v['phone'] ?? null,
            'email'               => $v['email'] ?? null,
            'config'              => json_encode([
                'departments' => [],
                'operating_hours' => ['open' => '08:00', 'close' => '21:00'],
            ]),
            'modules_enabled'     => json_encode(['ai_receptionist', 'whatsapp', 'triage', 'scheduling', 'queue', 'billing', 'analytics', 'engagement']),
            'subscription_plan'   => 'standard',
            'subscription_status' => 'active',
            'is_active'           => true,
        ]);

        return redirect()->route('web.superadmin.index')->with('success', 'Hospital "' . $v['name'] . '" created.');
    }

    public function editHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $regions = config('regions');
        return view('superadmin.hospital-form', compact('hospital', 'regions'));
    }

    public function updateHospital(Request $request, string $id)
    {
        $hospital = Hospital::findOrFail($id);

        $v = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => 'required|string|max:100|alpha_dash|unique:hospitals,slug,' . $id,
            'country'   => 'required|in:IN,AE',
            'city'      => 'required|string|max:100',
            'state'     => 'nullable|string|max:100',
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:20',
            'email'     => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $hospital->update([
            'name'      => $v['name'],
            'slug'      => $v['slug'],
            'country'   => $v['country'],
            'city'      => $v['city'],
            'state'     => $v['state'] ?? $hospital->state,
            'address'   => $v['address'] ?? $hospital->address,
            'phone'     => $v['phone'] ?? $hospital->phone,
            'email'     => $v['email'] ?? $hospital->email,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('web.superadmin.index')->with('success', 'Hospital updated.');
    }

    public function deleteHospital(string $id)
    {
        $hospital = Hospital::findOrFail($id);
        $hospital->update(['is_active' => false]);
        return redirect()->route('web.superadmin.index')->with('success', 'Hospital deactivated.');
    }
}
