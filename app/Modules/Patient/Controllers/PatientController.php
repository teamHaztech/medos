<?php

namespace App\Modules\Patient\Controllers;

use App\Modules\Patient\Models\Patient;
use App\Modules\Patient\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    public function __construct(
        private PatientService $patientService,
    ) {}

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    /**
     * List patients (search by name/phone, paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'   => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Patient::where('hospital_id', $request->user()->hospital_id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $patients = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $patients,
            'message' => 'Patients retrieved successfully.',
        ]);
    }

    /**
     * Create a new patient (from admin, not AI flow).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'              => 'required|string|max:255',
            'last_name'               => 'required|string|max:255',
            'phone'                   => 'required|string|max:20',
            'email'                   => 'nullable|email|max:255',
            'date_of_birth'           => 'nullable|date|before:today',
            'gender'                  => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address'                 => 'nullable|string|max:500',
            'national_id'             => 'nullable|string|max:50',
            'blood_type'              => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'insurance_provider'      => 'nullable|string|max:255',
            'insurance_policy_number' => 'nullable|string|max:100',
            'allergies'               => 'nullable|array',
            'current_medications'     => 'nullable|array',
            'emergency_contact_name'  => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'preferred_language'      => 'nullable|string|max:5',
        ]);

        $validated['hospital_id'] = $request->user()->hospital_id;

        $patient = Patient::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $patient,
            'message' => 'Patient created successfully.',
        ], 201);
    }

    /**
     * Get patient details with recent encounters.
     */
    public function show(string $id): JsonResponse
    {
        $patient = Patient::with([
            'encounters' => fn ($q) => $q->latest()->limit(10),
            'encounters.doctor',
            'appointments' => fn ($q) => $q->latest()->limit(5),
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $patient,
            'message' => 'Patient retrieved successfully.',
        ]);
    }

    /**
     * Update patient info.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'first_name'              => 'sometimes|string|max:255',
            'last_name'               => 'sometimes|string|max:255',
            'phone'                   => 'sometimes|string|max:20',
            'email'                   => 'nullable|email|max:255',
            'date_of_birth'           => 'nullable|date|before:today',
            'gender'                  => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address'                 => 'nullable|string|max:500',
            'national_id'             => 'nullable|string|max:50',
            'blood_type'              => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'insurance_provider'      => 'nullable|string|max:255',
            'insurance_policy_number' => 'nullable|string|max:100',
            'allergies'               => 'nullable|array',
            'current_medications'     => 'nullable|array',
            'emergency_contact_name'  => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'preferred_language'      => 'nullable|string|max:5',
        ]);

        $patient->update($validated);

        return response()->json([
            'success' => true,
            'data'    => $patient->fresh(),
            'message' => 'Patient updated successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Search & Timeline
    // ---------------------------------------------------------------

    /**
     * Search by phone number (exact match) -- used by AI to find returning patients.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $patient = Patient::where('hospital_id', $request->user()->hospital_id)
            ->where('phone', $request->input('phone'))
            ->first();

        if (! $patient) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'No patient found with this phone number.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $patient,
            'message' => 'Patient found.',
        ]);
    }

    /**
     * List patient's encounters.
     */
    public function encounters(string $id): JsonResponse
    {
        $patient = Patient::findOrFail($id);

        $encounters = $patient->encounters()
            ->with(['doctor'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $encounters,
            'message' => 'Patient encounters retrieved successfully.',
        ]);
    }

    /**
     * Full patient timeline (encounters, appointments, bills, conversations merged and sorted by date).
     */
    public function timeline(string $id): JsonResponse
    {
        $timeline = $this->patientService->getTimeline($id);

        return response()->json([
            'success' => true,
            'data'    => $timeline,
            'message' => 'Patient timeline retrieved successfully.',
        ]);
    }
}
