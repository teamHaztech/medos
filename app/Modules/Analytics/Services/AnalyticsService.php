<?php

namespace App\Modules\Analytics\Services;

use App\Modules\AIReceptionist\Models\Conversation;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\QueueEntry;
use App\Modules\Billing\Models\Bill;
use App\Modules\Core\Enums\EncounterStatus;
use App\Modules\Core\Models\Staff;
use App\Modules\Core\Services\BaseModuleService;
use App\Modules\Core\Services\HospitalContext;
use App\Modules\Insurance\Models\InsuranceTransaction;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService extends BaseModuleService
{
    public function __construct(HospitalContext $hospitalContext)
    {
        parent::__construct($hospitalContext);
    }

    // ---------------------------------------------------------------
    // Dashboard KPIs
    // ---------------------------------------------------------------

    /**
     * Aggregate today's KPIs for the admin dashboard.
     */
    public function getDashboardKPIs(Carbon $date): array
    {
        $hospitalId = $this->requireHospital();
        $dateStr = $date->toDateString();

        // Patients today
        $patientsToday = Encounter::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->distinct('patient_id')
            ->count('patient_id');

        // Average wait time (SQLite-compatible)
        $waitAppts = Appointment::where('hospital_id', $hospitalId)
            ->whereDate('slot_start', $dateStr)
            ->whereNotNull('check_in_time')
            ->whereNotNull('consultation_start_time')
            ->get(['check_in_time', 'consultation_start_time']);

        $avgWaitTime = $waitAppts->count() > 0
            ? $waitAppts->avg(fn ($a) => Carbon::parse($a->consultation_start_time)->diffInMinutes(Carbon::parse($a->check_in_time)))
            : 0;

        // Automation rate (AI conversations vs total encounters)
        $totalEncounters = Encounter::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->count();

        $aiHandled = Conversation::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->where('handled_by_ai', true)
            ->count();

        $totalConversations = Conversation::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->count();

        $automationRate = $totalConversations > 0
            ? round(($aiHandled / $totalConversations) * 100, 1)
            : 0;

        // Revenue today
        $revenueToday = Bill::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->sum('total_amount');

        // Queue depth
        $queueDepth = QueueEntry::whereHas('appointment', function ($q) use ($hospitalId) {
                $q->where('hospital_id', $hospitalId);
            })
            ->whereNull('called_at')
            ->count();

        // Active doctors
        $activeDoctors = Staff::where('hospital_id', $hospitalId)
            ->where('is_active', true)
            ->where('role', 'doctor')
            ->count();

        // Completed encounters
        $completedCount = Encounter::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->where('status', EncounterStatus::Completed)
            ->count();

        return [
            'date'             => $dateStr,
            'patients_today'   => $patientsToday,
            'avg_wait_minutes' => round((float) ($avgWaitTime ?? 0), 1),
            'automation_rate'  => $automationRate,
            'revenue_today'    => round((float) $revenueToday, 2),
            'queue_depth'      => $queueDepth,
            'active_doctors'   => $activeDoctors,
            'completed'        => $completedCount,
            'total_encounters' => $totalEncounters,
        ];
    }

    // ---------------------------------------------------------------
    // Patient Flow
    // ---------------------------------------------------------------

    /**
     * Hourly patient counts for the given date.
     */
    public function getPatientFlowByHour(Carbon $date): array
    {
        $hospitalId = $this->requireHospital();
        $dateStr = $date->toDateString();

        // SQLite-compatible: strftime('%H', ...) instead of HOUR()
        $hourlyData = Encounter::where('hospital_id', $hospitalId)
            ->whereDate('created_at', $dateStr)
            ->selectRaw("cast(strftime('%H', created_at) as integer) as hour, COUNT(*) as count")
            ->groupByRaw("strftime('%H', created_at)")
            ->orderByRaw("strftime('%H', created_at)")
            ->pluck('count', 'hour')
            ->toArray();

        // Fill all 24 hours
        $flow = [];
        for ($h = 0; $h < 24; $h++) {
            $flow[] = [
                'hour'  => $h,
                'label' => sprintf('%02d:00', $h),
                'count' => $hourlyData[$h] ?? 0,
            ];
        }

        return [
            'date' => $dateStr,
            'data' => $flow,
            'peak_hour' => collect($flow)->sortByDesc('count')->first(),
        ];
    }

    // ---------------------------------------------------------------
    // Doctor Performance
    // ---------------------------------------------------------------

    /**
     * Per-doctor metrics within the date range.
     */
    public function getDoctorPerformance(Carbon $from, Carbon $to): array
    {
        $hospitalId = $this->requireHospital();

        $doctors = Staff::where('hospital_id', $hospitalId)
            ->where('role', 'doctor')
            ->where('is_active', true)
            ->get();

        $dayCount = max($from->diffInDays($to), 1);

        return $doctors->map(function (Staff $doctor) use ($from, $to, $dayCount) {
            $encounters = Encounter::where('doctor_id', $doctor->id)
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->get();

            $totalPatients = $encounters->count();
            $completed = $encounters->where('status', EncounterStatus::Completed)->count();

            // Average consultation time
            $appointments = Appointment::where('doctor_id', $doctor->id)
                ->whereBetween('consultation_start_time', [$from->startOfDay(), $to->endOfDay()])
                ->whereNotNull('consultation_start_time')
                ->whereNotNull('consultation_end_time')
                ->get();

            $avgDuration = $appointments->avg(fn ($apt) => $apt->calculateActualDuration()) ?? 0;

            // No-show rate
            $totalAppointments = Appointment::where('doctor_id', $doctor->id)
                ->whereBetween('slot_start', [$from->startOfDay(), $to->endOfDay()])
                ->count();

            $noShows = Appointment::where('doctor_id', $doctor->id)
                ->whereBetween('slot_start', [$from->startOfDay(), $to->endOfDay()])
                ->where('status', 'no_show')
                ->count();

            $noShowRate = $totalAppointments > 0
                ? round(($noShows / $totalAppointments) * 100, 1)
                : 0;

            // Average rating from patient feedback metadata
            $avgRating = $this->calculateDoctorRating($doctor->id, $from, $to);

            return [
                'doctor_id'             => $doctor->id,
                'name'                  => $doctor->full_name,
                'specialty'             => $doctor->specialty,
                'department'            => $doctor->department,
                'total_patients'        => $totalPatients,
                'patients_per_day'      => round($totalPatients / $dayCount, 1),
                'completed'             => $completed,
                'avg_consultation_mins' => round($avgDuration, 1),
                'no_show_rate'          => $noShowRate,
                'avg_rating'            => $avgRating,
            ];
        })->toArray();
    }

    // ---------------------------------------------------------------
    // AI Metrics
    // ---------------------------------------------------------------

    /**
     * AI automation metrics from conversations.
     */
    public function getAIMetrics(Carbon $from, Carbon $to): array
    {
        $hospitalId = $this->requireHospital();

        $conversations = Conversation::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $total = $conversations->count();
        $escalated = $conversations->whereNotNull('escalation_reason')->count();
        $completed = $conversations->whereNotNull('ended_at')->count();

        $escalationRate = $total > 0
            ? round(($escalated / $total) * 100, 1)
            : 0;

        // Average response time (time from conversation start to end)
        $avgDuration = $conversations
            ->filter(fn ($c) => $c->started_at && $c->ended_at)
            ->avg(fn ($c) => $c->started_at->diffInSeconds($c->ended_at));

        // Language distribution
        $languageDistribution = $conversations
            ->groupBy('language')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // State distribution (approximate intent distribution)
        $stateDistribution = $conversations
            ->groupBy('state')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Channel distribution
        $channelDistribution = $conversations
            ->groupBy(fn ($c) => $c->channel?->value ?? 'unknown')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return [
            'period'                => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'total_conversations'   => $total,
            'completed'             => $completed,
            'escalated'             => $escalated,
            'escalation_rate'       => $escalationRate,
            'avg_duration_seconds'  => round((float) ($avgDuration ?? 0)),
            'language_distribution' => $languageDistribution,
            'state_distribution'    => $stateDistribution,
            'channel_distribution'  => $channelDistribution,
        ];
    }

    // ---------------------------------------------------------------
    // Insurance Analytics
    // ---------------------------------------------------------------

    /**
     * Insurance analytics: approval rates, denial reasons, time-to-payment by insurer.
     */
    public function getInsuranceAnalytics(Carbon $from, Carbon $to): array
    {
        $hospitalId = $this->requireHospital();

        $transactions = InsuranceTransaction::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->get();

        $total = $transactions->count();
        $approved = $transactions->where('status', 'approved')->count();
        $denied = $transactions->where('status', 'denied')->count();
        $pending = $transactions->where('status', 'pending')->count();

        $approvalRate = $total > 0 ? round(($approved / $total) * 100, 1) : 0;

        // Group by insurer
        $byInsurer = $transactions->groupBy('provider_name')->map(function ($group, $insurer) {
            $insurerTotal = $group->count();
            $insurerApproved = $group->where('status', 'approved')->count();

            return [
                'insurer'       => $insurer,
                'total'         => $insurerTotal,
                'approved'      => $insurerApproved,
                'denied'        => $group->where('status', 'denied')->count(),
                'approval_rate' => $insurerTotal > 0 ? round(($insurerApproved / $insurerTotal) * 100, 1) : 0,
                'total_amount'  => $group->sum('approved_amount'),
            ];
        })->values()->toArray();

        return [
            'period'        => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total'         => $total,
            'approved'      => $approved,
            'denied'        => $denied,
            'pending'       => $pending,
            'approval_rate' => $approvalRate,
            'by_insurer'    => $byInsurer,
        ];
    }

    // ---------------------------------------------------------------
    // Revenue Breakdown
    // ---------------------------------------------------------------

    /**
     * Revenue breakdown by department, doctor, or insurer.
     */
    public function getRevenueBreakdown(Carbon $from, Carbon $to, ?string $groupBy = 'department'): array
    {
        $hospitalId = $this->requireHospital();

        $bills = Bill::where('hospital_id', $hospitalId)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->with(['encounter.doctor'])
            ->get();

        $totalRevenue = $bills->sum('amount_paid');
        $totalBilled = $bills->sum('total_amount');
        $totalOutstanding = $bills->sum('balance_due');
        $insuranceCovered = $bills->sum('insurance_covered');

        $breakdown = [];

        switch ($groupBy) {
            case 'doctor':
                $breakdown = $bills->groupBy(fn ($b) => $b->encounter?->doctor?->full_name ?? 'Unassigned')
                    ->map(fn ($group, $key) => [
                        'label'       => $key,
                        'billed'      => round($group->sum('total_amount'), 2),
                        'collected'   => round($group->sum('amount_paid'), 2),
                        'outstanding' => round($group->sum('balance_due'), 2),
                        'count'       => $group->count(),
                    ])
                    ->values()
                    ->toArray();
                break;

            case 'insurer':
                $breakdown = $bills->groupBy(fn ($b) => $b->encounter?->patient?->insurance_provider ?? 'Self-Pay')
                    ->map(fn ($group, $key) => [
                        'label'            => $key,
                        'billed'           => round($group->sum('total_amount'), 2),
                        'collected'        => round($group->sum('amount_paid'), 2),
                        'insurance_amount' => round($group->sum('insurance_covered'), 2),
                        'count'            => $group->count(),
                    ])
                    ->values()
                    ->toArray();
                break;

            case 'department':
            default:
                $breakdown = $bills->groupBy(fn ($b) => $b->encounter?->doctor?->department ?? 'General')
                    ->map(fn ($group, $key) => [
                        'label'       => $key,
                        'billed'      => round($group->sum('total_amount'), 2),
                        'collected'   => round($group->sum('amount_paid'), 2),
                        'outstanding' => round($group->sum('balance_due'), 2),
                        'count'       => $group->count(),
                    ])
                    ->values()
                    ->toArray();
                break;
        }

        return [
            'period'             => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'group_by'           => $groupBy,
            'total_revenue'      => round($totalRevenue, 2),
            'total_billed'       => round($totalBilled, 2),
            'total_outstanding'  => round($totalOutstanding, 2),
            'insurance_covered'  => round($insuranceCovered, 2),
            'breakdown'          => $breakdown,
        ];
    }

    // ---------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------

    /**
     * Calculate average doctor rating from patient feedback metadata.
     */
    private function calculateDoctorRating(string $doctorId, Carbon $from, Carbon $to): ?float
    {
        $patients = Patient::whereHas('encounters', function ($q) use ($doctorId, $from, $to) {
            $q->where('doctor_id', $doctorId)
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()]);
        })->get();

        $ratings = [];

        foreach ($patients as $patient) {
            $feedbackList = $patient->metadata['feedback'] ?? [];

            foreach ($feedbackList as $feedback) {
                if (isset($feedback['rating'])) {
                    $feedbackDate = Carbon::parse($feedback['date'] ?? '2000-01-01');

                    if ($feedbackDate->between($from, $to)) {
                        $ratings[] = $feedback['rating'];
                    }
                }
            }
        }

        return count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : null;
    }
}
