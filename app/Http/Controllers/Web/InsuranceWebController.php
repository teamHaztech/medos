<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Services\RevenueInsights;
use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Services\ChargeCapture;
use App\Modules\Insurance\Models\InsuranceTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Bill-centric insurance claims: file a claim against a bill, track its status, and
 * apply an approved amount as coverage (reducing the patient's payable via the same
 * ledger math as the rest of billing). Payer network calls remain pluggable/stubbed.
 */
class InsuranceWebController extends Controller
{
    private function hid(): string
    {
        return Auth::user()->hospital_id;
    }

    public function index(Request $request)
    {
        $hid = $this->hid();
        $claims = InsuranceTransaction::where('hospital_id', $hid)
            ->whereIn('type', ['claim_submission', 'pre_authorization'])
            ->with(['patient', 'bill'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')->paginate(30);

        return view('billing.claims', compact('claims'));
    }

    /**
     * Insurance claims insights — the claim funnel (filed → approved / denied /
     * pending), approval & realization rates, claimed vs approved amounts, per-payer
     * performance and claim turnaround, over a day / week / month / year. Off the
     * insurance_transactions ledger (claim + pre-auth records).
     */
    public function insights(Request $request, RevenueInsights $insights)
    {
        $hid    = $this->hid();
        $period = $request->get('period', 'month');
        $r      = $insights->range($period);

        $cols = ['status', 'requested_amount', 'approved_amount', 'insurer_name', 'submitted_at', 'responded_at', 'created_at'];
        $base = fn ($from, $to) => InsuranceTransaction::where('hospital_id', $hid)
            ->whereIn('type', ['claim_submission', 'pre_authorization'])
            ->whereBetween('created_at', [$from, $to]);

        $claims = (clone $base($r['start'], $r['end']))->get($cols);

        $approved = $claims->where('status', 'approved');
        $denied   = $claims->where('status', 'denied');
        $pending  = $claims->whereNotIn('status', ['approved', 'denied']);
        $resolved = $approved->count() + $denied->count();

        $claimedAmt  = round((float) $claims->sum('requested_amount'), 2);
        $approvedAmt = round((float) $approved->sum('approved_amount'), 2);

        // Previous window (for deltas).
        $prevClaims   = (clone $base($r['prevStart'], $r['prevEnd']))->get(['status', 'requested_amount', 'approved_amount']);
        $filedPrev    = $prevClaims->count();
        $claimedPrev  = (float) $prevClaims->sum('requested_amount');
        $approvedPrev = (float) $prevClaims->where('status', 'approved')->sum('approved_amount');

        // Turnaround (submitted → responded), in days.
        $responded = $claims->filter(fn ($c) => $c->submitted_at && $c->responded_at);
        $avgTat = $responded->count()
            ? round($responded->avg(fn ($c) => Carbon::parse($c->submitted_at)->diffInDays(Carbon::parse($c->responded_at))), 1)
            : 0;

        $kpis = [
            'filed'            => $claims->count(),
            'filed_change'     => RevenueInsights::pctChange($claims->count(), $filedPrev),
            'approval_rate'    => $resolved > 0 ? (int) round($approved->count() / $resolved * 100) : 0,
            'claimed'          => $claimedAmt,
            'claimed_change'   => RevenueInsights::pctChange($claimedAmt, $claimedPrev),
            'approved_amt'     => $approvedAmt,
            'approved_change'  => RevenueInsights::pctChange($approvedAmt, $approvedPrev),
            'realization'      => $claimedAmt > 0 ? (int) round($approvedAmt / $claimedAmt * 100) : 0,
            'avg_tat_days'     => $avgTat,
        ];

        $funnel = [
            'filed'    => $claims->count(),
            'approved' => $approved->count(),
            'denied'   => $denied->count(),
            'pending'  => $pending->count(),
        ];

        $trend = $insights->series($claims, fn ($c) => $c->created_at, $r['start'], $r['end'], $r['granularity'], $r['labelFormat']);

        $byInsurer = $claims->groupBy(fn ($c) => $c->insurer_name ?: 'Unknown')
            ->map(fn ($g, $k) => (object) [
                'insurer'  => $k,
                'count'    => $g->count(),
                'claimed'  => round((float) $g->sum('requested_amount'), 2),
                'approved' => round((float) $g->where('status', 'approved')->sum('approved_amount'), 2),
            ])->sortByDesc('claimed')->values();

        return view('billing.claims-insights', [
            'period'      => $period,
            'periodLabel' => $r['label'],
            'kpis'        => $kpis,
            'funnel'      => $funnel,
            'trend'       => $trend,
            'byInsurer'   => $byInsurer,
        ]);
    }

    /** File an insurance claim against a bill. */
    public function fileClaim(Request $request, string $billId)
    {
        $hid = $this->hid();
        $bill = Bill::where('hospital_id', $hid)->findOrFail($billId);

        $v = $request->validate([
            'insurer_code'     => 'nullable|string|max:60',
            'insurer_name'     => 'required|string|max:150',
            'policy_number'    => 'required|string|max:100',
            'member_id'        => 'nullable|string|max:100',
            'requested_amount' => 'required|numeric|min:0',
        ]);

        if ($bill->insurance_transaction_id) {
            return back()->with('error', 'A claim has already been filed for this bill.');
        }

        $claim = InsuranceTransaction::create([
            'id'               => Str::uuid()->toString(),
            'hospital_id'      => $hid,
            'encounter_id'     => $bill->encounter_id,
            'patient_id'       => $bill->patient_id,
            'insurer_code'     => $v['insurer_code'] ?? Str::slug($v['insurer_name']),
            'insurer_name'     => $v['insurer_name'],
            'policy_number'    => $v['policy_number'],
            'member_id'        => $v['member_id'] ?? null,
            'type'             => 'claim_submission',
            'status'           => 'submitted',
            'requested_amount' => (float) $v['requested_amount'],
            'request_payload'  => [
                'bill_number' => $bill->bill_number,
                'bill_total'  => (float) $bill->total_amount,
                'line_items'  => $bill->line_items,
                'filed_by'    => Auth::user()->name,
            ],
            'submitted_at'     => now(),
        ]);

        $bill->update([
            'insurance_transaction_id' => $claim->id,
            'insurer_name'             => $v['insurer_name'],
            'policy_number'            => $v['policy_number'],
        ]);

        return back()->with('success', 'Claim filed with ' . $v['insurer_name'] . ' for ' . $bill->bill_number . '.');
    }

    /** Record insurer approval and apply the approved amount as coverage on the bill. */
    public function approveClaim(Request $request, string $claimId, ChargeCapture $charges)
    {
        $hid = $this->hid();
        $claim = InsuranceTransaction::where('hospital_id', $hid)->findOrFail($claimId);

        $v = $request->validate([
            'approved_amount'      => 'required|numeric|min:0',
            'external_reference_id' => 'nullable|string|max:150',
        ]);

        $bill = $claim->bill;
        $approved = (float) $v['approved_amount'];
        if ($bill) {
            $approved = min($approved, (float) $bill->total_amount); // never over-cover
        }

        $claim->update([
            'status'                => 'approved',
            'approved_amount'       => $approved,
            'external_reference_id' => $v['external_reference_id'] ?? $claim->external_reference_id,
            'responded_at'          => now(),
        ]);

        // Apply coverage to the bill and recompute the patient's balance via the ledger.
        if ($bill) {
            $bill->update([
                'insurance_covered' => $approved,
                'patient_payable'   => max(0, round((float) $bill->total_amount - $approved, 2)),
            ]);
            $charges->recomputePayments($bill->fresh());
        }

        return back()->with('success', 'Claim approved — coverage of ' . number_format($approved, 2) . ' applied to the bill.');
    }

    public function denyClaim(Request $request, string $claimId)
    {
        $hid = $this->hid();
        $claim = InsuranceTransaction::where('hospital_id', $hid)->findOrFail($claimId);

        $v = $request->validate(['denial_reason' => 'required|string|max:500']);
        $claim->update([
            'status'        => 'denied',
            'denial_reason' => $v['denial_reason'],
            'responded_at'  => now(),
        ]);

        return back()->with('success', 'Claim marked denied.');
    }
}
