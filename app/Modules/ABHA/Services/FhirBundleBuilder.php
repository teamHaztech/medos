<?php

namespace App\Modules\ABHA\Services;

use Illuminate\Support\Str;

/**
 * Builds FHIR R4 "document" Bundles in the shape ABDM expects for HIP data push.
 *
 * A document Bundle's first entry is always a Composition that indexes the
 * clinical resources; ABDM keys the HI-type off Composition.type (SNOMED CT).
 * This is a real, standards-structured builder (not a stub) — but the exact
 * profile/slice requirements per HI type should be reconciled against the ABDM
 * sandbox's FHIR profiles once gateway access is granted.
 */
class FhirBundleBuilder
{
    /** ABDM HI type -> Composition.type SNOMED CT code + display. */
    private const COMPOSITION_TYPE = [
        'op_consultation'   => ['371530004', 'Clinical consultation report'],
        'discharge_summary' => ['373942005', 'Discharge summary'],
        'prescription'      => ['440545006', 'Prescription record'],
        'diagnostic_report' => ['721981007', 'Diagnostic studies report'],
        'lab_result'        => ['721981007', 'Diagnostic studies report'],
        'immunization'      => ['41000179103', 'Immunization record'],
        'wellness_record'   => ['419891008', 'Record artifact'],
    ];

    /** Legacy record-type aliases -> canonical HI type. */
    private const ALIASES = [
        'encounter'  => 'op_consultation',
        'lab_result' => 'diagnostic_report',
    ];

    /**
     * @param string $recordType  one of the HI types (or a legacy alias)
     * @param array  $data        keys: patient[name,gender,dob,abha_address],
     *                            practitioner[name,hpr_id], organization[name,hfr_id],
     *                            title, date, and type-specific payload (medications[], observations[], text)
     */
    public function build(string $recordType, array $data): array
    {
        $type = self::ALIASES[$recordType] ?? $recordType;

        $patientRef      = 'urn:uuid:' . Str::uuid();
        $practitionerRef = 'urn:uuid:' . Str::uuid();
        $orgRef          = 'urn:uuid:' . Str::uuid();
        $compositionRef  = 'urn:uuid:' . Str::uuid();

        $date = $data['date'] ?? now()->toIso8601String();

        $entries = [];

        // 1) Composition MUST be the first entry of a document bundle.
        [$code, $display] = self::COMPOSITION_TYPE[$type] ?? ['419891008', 'Record artifact'];
        $clinical = $this->clinicalResources($type, $data);

        $sections = array_map(fn ($c) => [
            'title' => $c['_sectionTitle'] ?? $display,
            'code'  => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]],
            'entry' => [['reference' => $c['_ref']]],
        ], $clinical);

        $entries[] = $this->entry($compositionRef, [
            'resourceType' => 'Composition',
            'id'           => Str::uuid()->toString(),
            'status'       => 'final',
            'type'         => ['coding' => [['system' => 'http://snomed.info/sct', 'code' => $code, 'display' => $display]]],
            'subject'      => ['reference' => $patientRef],
            'date'         => $date,
            'author'       => [['reference' => $practitionerRef]],
            'title'        => $data['title'] ?? $display,
            'custodian'    => ['reference' => $orgRef],
            'section'      => $sections,
        ]);

        // 2) Referenced actors.
        $entries[] = $this->entry($patientRef, $this->patient($data['patient'] ?? []));
        $entries[] = $this->entry($practitionerRef, $this->practitioner($data['practitioner'] ?? []));
        $entries[] = $this->entry($orgRef, $this->organization($data['organization'] ?? []));

        // 3) The clinical resources themselves.
        foreach ($clinical as $c) {
            $entries[] = $this->entry($c['_ref'], $c['resource'], $patientRef);
        }

        return [
            'resourceType' => 'Bundle',
            'id'           => Str::uuid()->toString(),
            'type'         => 'document',
            'timestamp'    => now()->toIso8601String(),
            'meta'         => ['lastUpdated' => now()->toIso8601String()],
            'identifier'   => ['system' => 'https://medos.haztech.cloud/bundle', 'value' => (string) Str::uuid()],
            'entry'        => $entries,
        ];
    }

    private function entry(string $fullUrl, array $resource, ?string $patientRef = null): array
    {
        if ($patientRef && ! isset($resource['subject']) && in_array($resource['resourceType'], ['DiagnosticReport', 'MedicationRequest', 'Observation', 'Condition'], true)) {
            $resource['subject'] = ['reference' => $patientRef];
        }

        return ['fullUrl' => $fullUrl, 'resource' => $resource];
    }

    private function patient(array $p): array
    {
        $identifiers = [];
        if (! empty($p['abha_address'])) {
            $identifiers[] = ['system' => 'https://healthid.abdm.gov.in', 'value' => $p['abha_address']];
        }

        return [
            'resourceType' => 'Patient',
            'id'           => Str::uuid()->toString(),
            'identifier'   => $identifiers,
            'name'         => [['text' => $p['name'] ?? 'Patient']],
            'gender'       => strtolower($p['gender'] ?? 'unknown') ?: 'unknown',
            'birthDate'    => $p['dob'] ?? null,
        ];
    }

    private function practitioner(array $pr): array
    {
        $identifiers = [];
        if (! empty($pr['hpr_id'])) {
            $identifiers[] = ['system' => 'https://hpr.abdm.gov.in', 'value' => $pr['hpr_id']];
        }

        return [
            'resourceType' => 'Practitioner',
            'id'           => Str::uuid()->toString(),
            'identifier'   => $identifiers,
            'name'         => [['text' => $pr['name'] ?? 'Practitioner']],
        ];
    }

    private function organization(array $o): array
    {
        $identifiers = [];
        if (! empty($o['hfr_id'])) {
            $identifiers[] = ['system' => 'https://facility.abdm.gov.in', 'value' => $o['hfr_id']];
        }

        return [
            'resourceType' => 'Organization',
            'id'           => Str::uuid()->toString(),
            'identifier'   => $identifiers,
            'name'         => $o['name'] ?? 'Facility',
        ];
    }

    /**
     * Type-specific clinical resources. Each element: [_ref, _sectionTitle, resource].
     *
     * @return array<int, array{_ref:string,_sectionTitle:string,resource:array}>
     */
    private function clinicalResources(string $type, array $data): array
    {
        return match ($type) {
            'prescription' => $this->prescriptionResources($data),
            default        => $this->genericReportResources($type, $data),
        };
    }

    private function prescriptionResources(array $data): array
    {
        $out = [];
        foreach (($data['medications'] ?? []) as $med) {
            $name = is_array($med) ? ($med['name'] ?? '') : (string) $med;
            $out[] = [
                '_ref'          => 'urn:uuid:' . Str::uuid(),
                '_sectionTitle' => 'Prescription',
                'resource'      => [
                    'resourceType'      => 'MedicationRequest',
                    'id'                => Str::uuid()->toString(),
                    'status'            => 'active',
                    'intent'            => 'order',
                    'medicationCodeableConcept' => ['text' => $name],
                    'dosageInstruction' => [['text' => is_array($med) ? ($med['dosage'] ?? '') : '']],
                ],
            ];
        }
        if ($out === []) {
            $out[] = [
                '_ref'          => 'urn:uuid:' . Str::uuid(),
                '_sectionTitle' => 'Prescription',
                'resource'      => [
                    'resourceType' => 'MedicationRequest',
                    'id'           => Str::uuid()->toString(),
                    'status'       => 'active', 'intent' => 'order',
                    'medicationCodeableConcept' => ['text' => $data['text'] ?? 'Prescription'],
                ],
            ];
        }

        return $out;
    }

    private function genericReportResources(string $type, array $data): array
    {
        return [[
            '_ref'          => 'urn:uuid:' . Str::uuid(),
            '_sectionTitle' => ucwords(str_replace('_', ' ', $type)),
            'resource'      => [
                'resourceType' => 'DiagnosticReport',
                'id'           => Str::uuid()->toString(),
                'status'       => 'final',
                'code'         => ['text' => $data['title'] ?? ucwords(str_replace('_', ' ', $type))],
                'conclusion'   => $data['text'] ?? ($data['description'] ?? ''),
            ],
        ]];
    }
}
