<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Asset\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
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
        $vendors = Vendor::where('is_active', true)
            ->withCount('assets')
            ->orderBy('name')
            ->get();

        return view('admin.assets.vendors', compact('vendors'));
    }

    public function store(Request $request)
    {
        $hid = $this->hid();
        $data = $this->validateVendor($request);
        $data['hospital_id'] = $hid;

        Vendor::create($data);

        return redirect()->route('web.admin.vendors.index')->with('success', 'Vendor added.');
    }

    public function update(Request $request, string $id)
    {
        $this->hid();
        $vendor = Vendor::findOrFail($id);
        $vendor->update($this->validateVendor($request));

        return redirect()->route('web.admin.vendors.index')->with('success', 'Vendor updated.');
    }

    public function destroy(string $id)
    {
        $this->hid();
        Vendor::where('id', $id)->update(['is_active' => false]);

        return redirect()->route('web.admin.vendors.index')->with('success', 'Vendor removed.');
    }

    private function validateVendor(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:500',
            'service_type'   => 'nullable|string|max:150',
        ]);
    }
}
