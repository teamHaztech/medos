<?php

namespace App\Modules\ABHA\Controllers;

use App\Modules\ABHA\Services\ABHAService;
use App\Modules\ABHA\Services\ConsentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ABHAController extends Controller
{
    public function __construct(
        private readonly ABHAService $abhaService,
        private readonly ConsentService $consentService,
    ) {}

    /**
     * POST /abha/verify — Verify an ABHA number and return the profile.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'abha_number' => 'required|string',
        ]);

        $result = $this->abhaService->verifyAbha($request->input('abha_number'));

        return response()->json([
            'success' => $result['valid'],
            'data'    => $result['profile'],
            'message' => $result['valid'] ? 'ABHA number is valid.' : 'ABHA number could not be verified.',
        ]);
    }

    /**
     * POST /abha/link — Link an ABHA number to a patient.
     */
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id'  => 'required|string|uuid',
            'abha_number' => 'required|string',
        ]);

        $result = $this->abhaService->linkAbha(
            $request->input('patient_id'),
            $request->input('abha_number'),
        );

        return response()->json([
            'success' => $result['success'],
            'data'    => $result['profile'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 422);
    }

    /**
     * POST /abha/create — Create a new ABHA number.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'gender'        => 'required|string|in:M,F,O',
            'phone'         => 'required|string',
            'aadhaar'       => 'nullable|string|size:12',
        ]);

        $result = $this->abhaService->createAbha($request->only([
            'name', 'date_of_birth', 'gender', 'phone', 'aadhaar',
        ]));

        return response()->json([
            'success' => $result['success'],
            'data'    => [
                'abha_number'  => $result['abha_number'],
                'abha_address' => $result['abha_address'],
                'profile'      => $result['profile'],
            ],
            'message' => $result['success'] ? 'ABHA number created successfully.' : 'Failed to create ABHA number.',
        ], $result['success'] ? 201 : 500);
    }

    /**
     * GET /abha/patients/{patientId}/records — Get ABHA-linked health records.
     */
    public function records(string $patientId): JsonResponse
    {
        $records = $this->abhaService->getLinkedRecords($patientId);

        return response()->json([
            'success' => true,
            'data'    => $records,
            'message' => 'Health records retrieved.',
        ]);
    }

    /**
     * GET /abha/patients/{patientId}/consents — Get consent history for a patient.
     */
    public function consents(string $patientId): JsonResponse
    {
        $consents = $this->consentService->getConsentHistory($patientId);

        return response()->json([
            'success' => true,
            'data'    => $consents,
            'message' => 'Consent history retrieved.',
        ]);
    }

    /**
     * POST /abha/consents/grant — Grant a consent request.
     */
    public function grantConsent(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id'       => 'required|string|uuid',
            'granted_to_id'    => 'required|string|uuid',
            'granted_to_type'  => 'required|string|in:doctor,hospital,lab,insurance',
            'granted_to_name'  => 'required|string|max:255',
            'purpose'          => 'required|string|in:consultation,referral,insurance,lab_report,prescription',
            'data_scope'       => 'required|string|in:encounter,prescription,lab_results,full_history,billing',
            'expires_at'       => 'nullable|date|after:now',
        ]);

        $consent = $this->consentService->requestConsent(
            patientId: $request->input('patient_id'),
            grantedToId: $request->input('granted_to_id'),
            grantedToType: $request->input('granted_to_type'),
            grantedToName: $request->input('granted_to_name'),
            purpose: $request->input('purpose'),
            dataScope: $request->input('data_scope'),
            expiresAt: $request->filled('expires_at') ? Carbon::parse($request->input('expires_at')) : null,
        );

        // Auto-grant the consent (in production, patient would approve separately)
        $consent = $this->consentService->grantConsent($consent->id);

        return response()->json([
            'success' => true,
            'data'    => $consent,
            'message' => 'Consent granted successfully.',
        ], 201);
    }

    /**
     * POST /abha/consents/{consentId}/revoke — Revoke a consent.
     */
    public function revokeConsent(Request $request, string $consentId): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $consent = $this->consentService->revokeConsent($consentId, $request->input('reason'));

        return response()->json([
            'success' => true,
            'data'    => $consent,
            'message' => 'Consent revoked successfully.',
        ]);
    }
}
