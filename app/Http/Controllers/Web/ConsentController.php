<?php

namespace App\Http\Controllers\Web;

use App\Modules\Consent\Models\ConsentForm;
use App\Modules\Consent\Models\PatientConsent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ConsentController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index()
    {
        $hid = $this->hid();

        $forms = ConsentForm::where('hospital_id', $hid)->orderBy('name')->get();

        $records = PatientConsent::where('hospital_id', $hid)
            ->with(['patient:id,name,phone', 'form:id,name,requires_witness'])
            ->latest('created_at')->limit(100)->get();

        $base = PatientConsent::where('hospital_id', $hid);
        $total = (clone $base)->count();
        $signed = (clone $base)->where('status', 'signed')->count();
        $counts = [
            'pending'    => (clone $base)->where('status', 'pending')->count(),
            'signed'     => $signed,
            'declined'   => (clone $base)->where('status', 'declined')->count(),
            'completion' => $total ? (int) round($signed / $total * 100) : 0,
        ];

        return view('consent.index', compact('forms', 'records', 'counts'));
    }

    public function request(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'      => 'required|uuid',
            'consent_form_id' => 'required|uuid',
            'notes'           => 'nullable|string|max:500',
        ]);

        PatientConsent::create([
            'hospital_id'     => $hid,
            'patient_id'      => $v['patient_id'],
            'consent_form_id' => $v['consent_form_id'],
            'status'          => 'pending',
            'notes'           => $v['notes'] ?? null,
        ]);

        return back()->with('success', 'Consent requested — pending signature.');
    }

    public function sign(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'signed_by_name' => 'required|string|max:120',
            'relationship'   => 'required|in:' . implode(',', array_keys(PatientConsent::RELATIONSHIPS)),
            'witness_name'   => 'nullable|string|max:120',
        ]);
        $consent = PatientConsent::where('hospital_id', $hid)->findOrFail($id);
        $consent->update([
            'status'         => 'signed',
            'signed_by_name' => $v['signed_by_name'],
            'relationship'   => $v['relationship'],
            'witness_name'   => $v['witness_name'] ?? null,
            'signed_at'      => now(),
        ]);

        return back()->with('success', 'Consent signed and recorded.');
    }

    public function setStatus(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'status' => 'required|in:pending,declined,withdrawn',
        ]);
        PatientConsent::where('hospital_id', $hid)->where('id', $id)->update(['status' => $v['status']]);

        return back()->with('success', 'Consent marked ' . $v['status'] . '.');
    }

    public function storeForm(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'name'             => 'required|string|max:150',
            'category'         => 'required|in:' . implode(',', array_keys(ConsentForm::CATEGORIES)),
            'content'          => 'nullable|string|max:5000',
            'requires_witness' => 'nullable|boolean',
        ]);
        $v['hospital_id'] = $hid;
        $v['requires_witness'] = (bool) ($v['requires_witness'] ?? false);
        $v['is_active'] = true;
        ConsentForm::create($v);

        return back()->with('success', 'Consent form added.');
    }

    public function updateForm(Request $request, string $id)
    {
        $hid = $this->hid();
        $form = ConsentForm::where('hospital_id', $hid)->findOrFail($id);
        $v = $request->validate([
            'name'             => 'required|string|max:150',
            'category'         => 'required|in:' . implode(',', array_keys(ConsentForm::CATEGORIES)),
            'content'          => 'nullable|string|max:5000',
            'requires_witness' => 'nullable|boolean',
            'is_active'        => 'nullable|boolean',
        ]);
        $v['requires_witness'] = (bool) ($v['requires_witness'] ?? false);
        $v['is_active'] = (bool) ($v['is_active'] ?? false);
        $form->update($v);

        return back()->with('success', 'Consent form updated.');
    }

    public function destroyForm(string $id)
    {
        $hid = $this->hid();
        ConsentForm::where('hospital_id', $hid)->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Consent form deactivated.');
    }
}
