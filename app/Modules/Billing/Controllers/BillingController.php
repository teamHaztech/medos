<?php

namespace App\Modules\Billing\Controllers;

use App\Modules\Billing\Models\Bill;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Patient\Models\Encounter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
    ) {}

    /**
     * Get bill details.
     *
     * GET /api/billing/{billId}
     */
    public function show(string $billId): JsonResponse
    {
        try {
            $bill = Bill::with(['patient', 'encounter'])->findOrFail($billId);

            $summary = $this->billingService->getBillSummary($bill);

            return response()->json([
                'success' => true,
                'data'    => [
                    'bill'    => $bill,
                    'summary' => $summary,
                ],
                'message' => 'Bill retrieved successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to retrieve bill: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Generate a bill for an encounter.
     *
     * POST /api/billing/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'encounter_id' => 'required|uuid|exists:encounters,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            $encounter = Encounter::findOrFail($request->input('encounter_id'));

            // Check if a bill already exists for this encounter
            $existingBill = Bill::where('encounter_id', $encounter->id)->first();
            if ($existingBill) {
                return response()->json([
                    'success' => false,
                    'data'    => ['bill_id' => $existingBill->id],
                    'message' => 'A bill already exists for this encounter.',
                ], 409);
            }

            $bill = $this->billingService->generateBill($encounter);

            return response()->json([
                'success' => true,
                'data'    => $bill->load(['patient', 'encounter']),
                'message' => 'Bill generated successfully.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to generate bill: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a line item/charge to a bill.
     *
     * POST /api/billing/{billId}/charges
     */
    public function addCharge(Request $request, string $billId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string|max:255',
            'code'        => 'required|string|max:50',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|string|in:consultation,laboratory,pharmacy,procedure,radiology,room,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            $bill = Bill::findOrFail($billId);

            $bill = $this->billingService->addCharge(
                $bill,
                $request->input('description'),
                $request->input('code'),
                (float) $request->input('amount'),
                $request->input('category'),
            );

            return response()->json([
                'success' => true,
                'data'    => $bill,
                'message' => 'Charge added successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to add charge: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process/record a payment for a bill.
     *
     * POST /api/billing/{billId}/payment
     */
    public function processPayment(Request $request, string $billId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'method'    => 'required|string|in:cash,card,upi,bank_transfer,insurance,cheque',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            $bill = Bill::findOrFail($billId);

            if ($bill->payment_status?->isSettled()) {
                return response()->json([
                    'success' => false,
                    'data'    => null,
                    'message' => 'This bill has already been settled.',
                ], 409);
            }

            $bill = $this->billingService->processPayment(
                $bill,
                $request->input('method'),
                $request->input('reference'),
            );

            return response()->json([
                'success' => true,
                'data'    => $bill,
                'message' => 'Payment recorded successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all bills for a patient.
     *
     * GET /api/billing/patient/{patientId}
     */
    public function patientBills(string $patientId): JsonResponse
    {
        $bills = Bill::where('patient_id', $patientId)
            ->with(['encounter'])
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $bills,
            'message' => 'Patient bills retrieved successfully.',
        ]);
    }

    /**
     * Get revenue analytics summary (admin only).
     *
     * GET /api/billing/revenue-summary
     */
    public function revenueSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from'       => 'required|date',
            'to'         => 'required|date|after_or_equal:from',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            $from = Carbon::parse($request->input('from'));
            $to = Carbon::parse($request->input('to'));

            $summary = $this->billingService->getRevenueSummary(
                $from,
                $to,
                $request->input('department'),
            );

            return response()->json([
                'success' => true,
                'data'    => $summary,
                'message' => 'Revenue summary retrieved successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to generate revenue summary: ' . $e->getMessage(),
            ], 500);
        }
    }
}
