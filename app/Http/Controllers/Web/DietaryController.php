<?php

namespace App\Http\Controllers\Web;

use App\Modules\Dietary\Models\DietOrder;
use App\Modules\Dietary\Models\NutritionAssessment;
use App\Modules\Dietary\Models\TherapeuticDiet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DietaryController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();

        // Optional patient focus (e.g. "Consult" from an appointment) → the view
        // pre-opens the nutrition assessment for this patient.
        $focusPatient = null;
        if ($request->filled('patient')) {
            $focusPatient = \App\Modules\Patient\Models\Patient::where('hospital_id', $hid)
                ->find($request->query('patient'));
        }

        $diets = TherapeuticDiet::where('hospital_id', $hid)->orderBy('name')->get();

        $orders = DietOrder::where('hospital_id', $hid)
            ->with(['patient:id,name,phone', 'diet:id,name,code'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->latest('start_date')->limit(200)->get();

        $assessments = NutritionAssessment::where('hospital_id', $hid)
            ->with('patient:id,name,phone')->latest('created_at')->limit(100)->get();

        // Kitchen diet census: active orders tallied by diet (what the kitchen must produce).
        $census = DietOrder::where('diet_orders.hospital_id', $hid)->where('diet_orders.status', 'active')
            ->join('therapeutic_diets', 'diet_orders.diet_id', '=', 'therapeutic_diets.id')
            ->selectRaw('therapeutic_diets.name as diet, therapeutic_diets.code as code, diet_orders.texture, count(*) as qty')
            ->groupBy('therapeutic_diets.name', 'therapeutic_diets.code', 'diet_orders.texture')
            ->orderByDesc('qty')->get();

        $counts = [
            'active'   => DietOrder::where('hospital_id', $hid)->where('status', 'active')->count(),
            'npo'      => DietOrder::where('hospital_id', $hid)->where('status', 'active')->where('route', 'npo')->count(),
            'tube'     => DietOrder::where('hospital_id', $hid)->where('status', 'active')->whereIn('route', ['ng_tube', 'peg'])->count(),
            'high_risk' => NutritionAssessment::where('hospital_id', $hid)->where('risk', 'high')->count(),
        ];

        return view('dietary.index', compact('diets', 'orders', 'assessments', 'census', 'counts', 'focusPatient'));
    }

    public function orderDiet(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'           => 'required|uuid',
            'diet_id'              => 'required|uuid',
            'ward'                 => 'nullable|string|max:120',
            'texture'              => 'required|in:' . implode(',', array_keys(TherapeuticDiet::TEXTURES)),
            'route'                => 'required|in:' . implode(',', array_keys(DietOrder::ROUTES)),
            'fluid_restriction_ml' => 'nullable|integer|min:0|max:10000',
            'kcal_target'          => 'nullable|integer|min:0|max:6000',
            'protein_target_g'     => 'nullable|integer|min:0|max:400',
            'restrictions'         => 'nullable|string|max:500',
            'special_instructions' => 'nullable|string|max:500',
            'start_date'           => 'required|date',
            'end_date'             => 'nullable|date|after_or_equal:start_date',
        ]);

        DietOrder::create(array_merge($v, [
            'hospital_id'     => $hid,
            'status'          => 'active',
            'ordered_by_name' => Auth::user()->name,
        ]));

        return back()->with('success', 'Diet order placed.');
    }

    public function discontinueOrder(string $id)
    {
        $hid = $this->hid();
        DietOrder::where('hospital_id', $hid)->where('id', $id)
            ->update(['status' => 'discontinued', 'end_date' => today()]);

        return back()->with('success', 'Diet order discontinued.');
    }

    public function storeAssessment(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'     => 'required|uuid',
            'tool'           => 'required|in:' . implode(',', array_keys(NutritionAssessment::TOOLS)),
            'score'          => 'nullable|integer|min:0|max:20',
            'risk'           => 'required|in:' . implode(',', array_keys(NutritionAssessment::RISKS)),
            'weight_kg'      => 'nullable|numeric|min:0|max:400',
            'height_cm'      => 'nullable|numeric|min:0|max:260',
            'diagnosis'      => 'nullable|string|max:500',
            'plan'           => 'nullable|string|max:1000',
            'follow_up_date' => 'nullable|date',
        ]);

        $bmi = null;
        if (! empty($v['weight_kg']) && ! empty($v['height_cm']) && $v['height_cm'] > 0) {
            $m = $v['height_cm'] / 100;
            $bmi = round($v['weight_kg'] / ($m * $m), 2);
        }

        NutritionAssessment::create(array_merge($v, [
            'hospital_id'      => $hid,
            'bmi'              => $bmi,
            'assessed_by_name' => Auth::user()->name,
        ]));

        return back()->with('success', 'Nutrition assessment saved.');
    }

    public function storeDiet(Request $request)
    {
        $hid = $this->hid();
        $v = $this->validateDiet($request);
        $v['hospital_id'] = $hid;
        $v['is_active'] = true;
        TherapeuticDiet::create($v);

        return back()->with('success', 'Therapeutic diet added to the catalogue.');
    }

    public function updateDiet(Request $request, string $id)
    {
        $hid = $this->hid();
        $diet = TherapeuticDiet::where('hospital_id', $hid)->findOrFail($id);
        $v = $this->validateDiet($request);
        $v['is_active'] = (bool) $request->input('is_active', false);
        $diet->update($v);

        return back()->with('success', 'Diet updated.');
    }

    public function destroyDiet(string $id)
    {
        $hid = $this->hid();
        TherapeuticDiet::where('hospital_id', $hid)->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Diet deactivated.');
    }

    private function validateDiet(Request $request): array
    {
        return $request->validate([
            'code'              => 'required|string|max:20',
            'name'              => 'required|string|max:150',
            'category'          => 'required|in:' . implode(',', array_keys(TherapeuticDiet::CATEGORIES)),
            'default_texture'   => 'required|in:' . implode(',', array_keys(TherapeuticDiet::TEXTURES)),
            'indications'       => 'nullable|string|max:500',
            'restrictions'      => 'nullable|string|max:500',
            'default_kcal'      => 'nullable|integer|min:0|max:6000',
            'default_protein_g' => 'nullable|integer|min:0|max:400',
        ]);
    }
}
