<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Analytics\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminDashboardController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    /**
     * Real-time KPIs: patients today, avg wait time, automation rate,
     * revenue today, queue depths, active doctors.
     */
    public function overview(Request $request): JsonResponse
    {
        $date = $request->has('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $kpis = $this->analyticsService->getDashboardKPIs($date);

        return response()->json([
            'success' => true,
            'data'    => $kpis,
            'message' => 'Dashboard overview retrieved successfully.',
        ]);
    }

    /**
     * Patient count by hour (for heatmap visualization).
     */
    public function patientFlow(Request $request): JsonResponse
    {
        $date = $request->has('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $flow = $this->analyticsService->getPatientFlowByHour($date);

        return response()->json([
            'success' => true,
            'data'    => $flow,
            'message' => 'Patient flow data retrieved successfully.',
        ]);
    }

    /**
     * Per-doctor stats: patients/day, avg consultation time, avg rating, no-show rate.
     */
    public function doctorPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to'   => 'sometimes|date|after_or_equal:from',
        ]);

        $from = $request->has('from') ? Carbon::parse($request->input('from')) : Carbon::today()->subDays(30);
        $to = $request->has('to') ? Carbon::parse($request->input('to')) : Carbon::today();

        $performance = $this->analyticsService->getDoctorPerformance($from, $to);

        return response()->json([
            'success' => true,
            'data'    => $performance,
            'message' => 'Doctor performance data retrieved successfully.',
        ]);
    }

    /**
     * AI metrics: conversations handled, escalation rate, avg response time,
     * intent distribution, language distribution.
     */
    public function aiPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to'   => 'sometimes|date|after_or_equal:from',
        ]);

        $from = $request->has('from') ? Carbon::parse($request->input('from')) : Carbon::today()->subDays(30);
        $to = $request->has('to') ? Carbon::parse($request->input('to')) : Carbon::today();

        $metrics = $this->analyticsService->getAIMetrics($from, $to);

        return response()->json([
            'success' => true,
            'data'    => $metrics,
            'message' => 'AI performance metrics retrieved successfully.',
        ]);
    }

    /**
     * GET hospital configuration (departments, doctors, schedules, modules).
     */
    public function configuration(Request $request): JsonResponse
    {
        $hospital = $request->user()->staff?->hospital;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital context not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'hospital'    => $hospital,
                'departments' => $hospital->departments ?? [],
                'staff'       => $hospital->staff()->where('is_active', true)->get(),
                'settings'    => $hospital->settings ?? [],
                'modules'     => [
                    'ai_receptionist' => true,
                    'whatsapp'        => ! empty(config('medos.whatsapp.meta_token')),
                    'insurance'       => config('medos.insurance.auto_verify', false),
                    'triage_ml'       => config('medos.triage.ml_enabled', false),
                ],
                'languages'   => config('medos.languages'),
                'scheduling'  => config('medos.scheduling'),
            ],
            'message' => 'Configuration retrieved successfully.',
        ]);
    }

    /**
     * PUT update hospital configuration.
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                => 'sometimes|string|max:255',
            'settings'            => 'sometimes|array',
            'settings.*'          => 'nullable',
            'scheduling'          => 'sometimes|array',
            'departments'         => 'sometimes|array',
            'departments.*.name'  => 'required_with:departments|string|max:255',
        ]);

        $hospital = $request->user()->staff?->hospital;

        if (! $hospital) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Hospital context not found.',
            ], 404);
        }

        if (isset($validated['name'])) {
            $hospital->name = $validated['name'];
        }

        if (isset($validated['settings'])) {
            $hospital->settings = array_merge($hospital->settings ?? [], $validated['settings']);
        }

        $hospital->save();

        return response()->json([
            'success' => true,
            'data'    => $hospital->fresh(),
            'message' => 'Configuration updated successfully.',
        ]);
    }
}
