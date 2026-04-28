<?php

namespace App\Modules\Analytics\Controllers;

use App\Modules\Analytics\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    /**
     * GET combined dashboard data.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $date = $request->has('date')
            ? Carbon::parse($request->input('date'))
            : Carbon::today();

        $kpis = $this->analyticsService->getDashboardKPIs($date);
        $flow = $this->analyticsService->getPatientFlowByHour($date);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis'         => $kpis,
                'patient_flow' => $flow,
            ],
            'message' => 'Dashboard data retrieved successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Revenue
    // ---------------------------------------------------------------

    /**
     * GET revenue analytics with filters.
     */
    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => 'sometimes|date',
            'to'       => 'sometimes|date|after_or_equal:from',
            'group_by' => 'sometimes|string|in:department,doctor,insurer',
        ]);

        $from = $request->has('from') ? Carbon::parse($request->input('from')) : Carbon::today()->subDays(30);
        $to = $request->has('to') ? Carbon::parse($request->input('to')) : Carbon::today();
        $groupBy = $request->input('group_by', 'department');

        $data = $this->analyticsService->getRevenueBreakdown($from, $to, $groupBy);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Revenue analytics retrieved successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Insurance
    // ---------------------------------------------------------------

    /**
     * GET insurance analytics.
     */
    public function insurance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'sometimes|date',
            'to'   => 'sometimes|date|after_or_equal:from',
        ]);

        $from = $request->has('from') ? Carbon::parse($request->input('from')) : Carbon::today()->subDays(30);
        $to = $request->has('to') ? Carbon::parse($request->input('to')) : Carbon::today();

        $data = $this->analyticsService->getInsuranceAnalytics($from, $to);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Insurance analytics retrieved successfully.',
        ]);
    }

    // ---------------------------------------------------------------
    // Export
    // ---------------------------------------------------------------

    /**
     * GET export data as CSV (streamed).
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'type'     => 'required|string|in:revenue,patients,doctors,insurance',
            'from'     => 'sometimes|date',
            'to'       => 'sometimes|date|after_or_equal:from',
            'group_by' => 'sometimes|string|in:department,doctor,insurer',
        ]);

        $type = $request->input('type');
        $from = $request->has('from') ? Carbon::parse($request->input('from')) : Carbon::today()->subDays(30);
        $to = $request->has('to') ? Carbon::parse($request->input('to')) : Carbon::today();

        $filename = "medos_{$type}_export_{$from->format('Ymd')}_{$to->format('Ymd')}.csv";

        return response()->streamDownload(function () use ($type, $from, $to, $request) {
            $handle = fopen('php://output', 'w');

            match ($type) {
                'revenue'   => $this->exportRevenue($handle, $from, $to, $request->input('group_by', 'department')),
                'patients'  => $this->exportPatients($handle, $from, $to),
                'doctors'   => $this->exportDoctors($handle, $from, $to),
                'insurance' => $this->exportInsurance($handle, $from, $to),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    // ---------------------------------------------------------------
    // Private Export Helpers
    // ---------------------------------------------------------------

    private function exportRevenue($handle, Carbon $from, Carbon $to, string $groupBy): void
    {
        $data = $this->analyticsService->getRevenueBreakdown($from, $to, $groupBy);

        fputcsv($handle, ['Label', 'Billed', 'Collected', 'Outstanding', 'Count']);

        foreach ($data['breakdown'] as $row) {
            fputcsv($handle, [
                $row['label'],
                $row['billed'],
                $row['collected'],
                $row['outstanding'] ?? 0,
                $row['count'],
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['Total Revenue', $data['total_revenue']]);
        fputcsv($handle, ['Total Billed', $data['total_billed']]);
        fputcsv($handle, ['Total Outstanding', $data['total_outstanding']]);
    }

    private function exportPatients($handle, Carbon $from, Carbon $to): void
    {
        $kpis = $this->analyticsService->getDashboardKPIs($from);

        fputcsv($handle, ['Metric', 'Value']);
        fputcsv($handle, ['Date', $from->toDateString()]);
        fputcsv($handle, ['Patients Today', $kpis['patients_today']]);
        fputcsv($handle, ['Avg Wait (mins)', $kpis['avg_wait_minutes']]);
        fputcsv($handle, ['Automation Rate (%)', $kpis['automation_rate']]);
        fputcsv($handle, ['Completed', $kpis['completed']]);
        fputcsv($handle, ['Total Encounters', $kpis['total_encounters']]);
    }

    private function exportDoctors($handle, Carbon $from, Carbon $to): void
    {
        $data = $this->analyticsService->getDoctorPerformance($from, $to);

        fputcsv($handle, ['Doctor', 'Specialty', 'Department', 'Total Patients', 'Patients/Day', 'Avg Consultation (mins)', 'No-Show Rate (%)', 'Avg Rating']);

        foreach ($data as $doctor) {
            fputcsv($handle, [
                $doctor['name'],
                $doctor['specialty'],
                $doctor['department'],
                $doctor['total_patients'],
                $doctor['patients_per_day'],
                $doctor['avg_consultation_mins'],
                $doctor['no_show_rate'],
                $doctor['avg_rating'] ?? 'N/A',
            ]);
        }
    }

    private function exportInsurance($handle, Carbon $from, Carbon $to): void
    {
        $data = $this->analyticsService->getInsuranceAnalytics($from, $to);

        fputcsv($handle, ['Insurer', 'Total Claims', 'Approved', 'Denied', 'Approval Rate (%)', 'Total Amount']);

        foreach ($data['by_insurer'] as $insurer) {
            fputcsv($handle, [
                $insurer['insurer'],
                $insurer['total'],
                $insurer['approved'],
                $insurer['denied'],
                $insurer['approval_rate'],
                $insurer['total_amount'] ?? 0,
            ]);
        }

        fputcsv($handle, []);
        fputcsv($handle, ['Overall Approval Rate (%)', $data['approval_rate']]);
    }
}
