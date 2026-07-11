<?php

namespace App\Http\Controllers\Web;

use App\Modules\Pathway\Models\PathwayTemplate;
use App\Modules\Pathway\Models\PatientPathway;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ClinicalPathwayController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index()
    {
        $hid = $this->hid();

        $templates = PathwayTemplate::where('hospital_id', $hid)->orderBy('name')->get();

        $enrollments = PatientPathway::where('hospital_id', $hid)
            ->with(['patient:id,name,phone', 'template:id,name,steps,category'])
            ->latest('created_at')->limit(150)->get();

        $base = PatientPathway::where('hospital_id', $hid);
        $counts = [
            'active'    => (clone $base)->where('status', 'active')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'templates' => PathwayTemplate::where('hospital_id', $hid)->where('is_active', true)->count(),
        ];

        return view('pathway.index', compact('templates', 'enrollments', 'counts'));
    }

    public function enroll(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'patient_id'  => 'required|uuid',
            'template_id' => 'required|uuid',
        ]);

        PatientPathway::create([
            'hospital_id'     => $hid,
            'patient_id'      => $v['patient_id'],
            'template_id'     => $v['template_id'],
            'status'          => 'active',
            'completed_steps' => [],
            'started_at'      => now(),
        ]);

        return back()->with('success', 'Patient enrolled on the pathway.');
    }

    public function toggleSteps(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'completed'   => 'nullable|array',
            'completed.*' => 'integer|min:0',
        ]);

        $pp = PatientPathway::where('hospital_id', $hid)->with('template')->findOrFail($id);
        $completed = array_values(array_unique(array_map('intval', $v['completed'] ?? [])));
        $total = count($pp->template?->steps ?? []);
        $done = $total > 0 && count($completed) >= $total;

        $pp->update([
            'completed_steps' => $completed,
            'status'          => $done ? 'completed' : 'active',
            'completed_at'    => $done ? now() : null,
        ]);

        return back()->with('success', 'Pathway progress updated.');
    }

    public function setStatus(Request $request, string $id)
    {
        $hid = $this->hid();
        $v = $request->validate(['status' => 'required|in:active,discontinued']);
        PatientPathway::where('hospital_id', $hid)->where('id', $id)->update(['status' => $v['status']]);

        return back()->with('success', 'Pathway ' . $v['status'] . '.');
    }

    public function storeTemplate(Request $request)
    {
        $hid = $this->hid();
        $v = $request->validate([
            'name'       => 'required|string|max:150',
            'category'   => 'required|in:' . implode(',', array_keys(PathwayTemplate::CATEGORIES)),
            'steps_text' => 'required|string|max:5000',
        ]);
        PathwayTemplate::create([
            'hospital_id' => $hid,
            'name'        => $v['name'],
            'category'    => $v['category'],
            'steps'       => $this->parseSteps($v['steps_text']),
            'is_active'   => true,
        ]);

        return back()->with('success', 'Pathway template created.');
    }

    public function updateTemplate(Request $request, string $id)
    {
        $hid = $this->hid();
        $template = PathwayTemplate::where('hospital_id', $hid)->findOrFail($id);
        $v = $request->validate([
            'name'       => 'required|string|max:150',
            'category'   => 'required|in:' . implode(',', array_keys(PathwayTemplate::CATEGORIES)),
            'steps_text' => 'required|string|max:5000',
            'is_active'  => 'nullable|boolean',
        ]);
        $template->update([
            'name'      => $v['name'],
            'category'  => $v['category'],
            'steps'     => $this->parseSteps($v['steps_text']),
            'is_active' => (bool) ($v['is_active'] ?? false),
        ]);

        return back()->with('success', 'Pathway template updated.');
    }

    public function destroyTemplate(string $id)
    {
        $hid = $this->hid();
        PathwayTemplate::where('hospital_id', $hid)->where('id', $id)->update(['is_active' => false]);

        return back()->with('success', 'Pathway template deactivated.');
    }

    private function parseSteps(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));
    }
}
