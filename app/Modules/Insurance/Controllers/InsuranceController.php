<?php

namespace App\Modules\Insurance\Controllers;

use App\Modules\Insurance\Models\InsuranceTransaction;
use App\Modules\Insurance\Services\InsuranceService;
use App\Modules\Patient\Models\Encounter;
use App\Modules\Patient\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class InsuranceController extends Controller
{
    public function __construct(
        protected InsuranceService $insuranceService,
    ) {}

    /**
     * Verify insurance eligibility for a patient.
     *
     * POST /api/insurance/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'patient_id'   => 'required|uuid|exists:patients,id',
            'insurer_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            $patient = Patient::findOrFail($request->input('patient_id'));

            $result = $this->insuranceService->verifyEligibility(
                $patient,
                $request->input('insurer_code'),
            );

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => $result['eligible']
                    ? 'Patient is eligible for insurance coverage.'
                    : 'Patient is not eligible for insurance coverage.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Eligibility verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit a pre-authorization request.
     *
     * POST /api/insurance/pre-auth
     */
    public function preAuth(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'encounter_id'    => 'required|uuid|exists:encounters,id',
            'diagnosis_codes' => 'required|array|min:1',
            'diagnosis_codes.*' => 'string',
            'procedure_codes' => 'required|array|min:1',
            'procedure_codes.*' => 'string',
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

            $transaction = $this->insuranceService->submitPreAuth(
                $encounter,
                $request->input('diagnosis_codes'),
                $request->input('procedure_codes'),
            );

            return response()->json([
                'success' => true,
                'data'    => $transaction,
                'message' => 'Pre-authorization submitted successfully.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Pre-authorization submission failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Submit a claim for an encounter.
     *
     * POST /api/insurance/claims
     */
    public function submitClaim(Request $request): JsonResponse
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

            $transaction = $this->insuranceService->submitClaim($encounter);

            return response()->json([
                'success' => true,
                'data'    => $transaction,
                'message' => 'Claim submitted successfully.',
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Claim submission failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the status of a claim/transaction.
     *
     * GET /api/insurance/claims/{transactionId}/status
     */
    public function claimStatus(string $transactionId): JsonResponse
    {
        try {
            $transaction = InsuranceTransaction::findOrFail($transactionId);

            $result = $this->insuranceService->checkClaimStatus($transaction);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'message' => 'Claim status retrieved successfully.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to retrieve claim status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List insurance transactions (filterable).
     *
     * GET /api/insurance/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $query = InsuranceTransaction::query();

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->filled('encounter_id')) {
            $query->where('encounter_id', $request->input('encounter_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('provider')) {
            $query->where('insurer_code', $request->input('provider'));
        }

        $transactions = $query->with(['patient', 'encounter'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data'    => $transactions,
            'message' => 'Transactions retrieved successfully.',
        ]);
    }
}
