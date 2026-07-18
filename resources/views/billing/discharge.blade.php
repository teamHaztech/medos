<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Discharge Summary - {{ $encounter->encounter_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; padding: 24px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; border-bottom: 3px solid #334155; padding-bottom: 16px; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 700; margin-bottom: 2px; }
        .header p { font-size: 12px; color: #64748b; }
        .doc-title { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: 2px; color: #334155; margin-bottom: 20px; text-transform: uppercase; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #3b82f6; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 13px; }
        .info-grid .item { display: flex; gap: 6px; }
        .info-grid .label { color: #64748b; font-weight: 500; min-width: 120px; }
        .info-grid .value { font-weight: 500; }
        .content-block { font-size: 13px; color: #334155; white-space: pre-wrap; }
        .med-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .med-table th { text-align: left; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; padding: 6px 8px; border-bottom: 2px solid #e2e8f0; }
        .med-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .investigation-list { list-style: none; font-size: 13px; }
        .investigation-list li { padding: 4px 0; padding-left: 12px; position: relative; }
        .investigation-list li::before { content: '-'; position: absolute; left: 0; color: #64748b; }
        .signature { margin-top: 48px; text-align: right; }
        .signature .line { width: 200px; border-top: 1px solid #94a3b8; margin-left: auto; margin-bottom: 4px; }
        .signature .name { font-weight: 600; font-size: 14px; }
        .signature .dept { font-size: 12px; color: #64748b; }
        .print-btn { display: block; margin: 0 auto 20px; padding: 10px 32px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .print-btn:hover { background: #2563eb; }

        @media print {
            .print-btn { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body>
    @php
        $patient = $encounter->patient;
        $doctor = $encounter->doctor;
        $soapNotes = $encounter->soap_notes ?? [];
        $intakeData = $encounter->intake_data ?? [];
        $diagnosisCodes = $encounter->diagnosis_codes ?? [];
        $encounterStatus = is_object($encounter->status) ? $encounter->status->value : $encounter->status;
        $encounterType = is_object($encounter->type) ? $encounter->type->value : $encounter->type;
        $age = $patient?->date_of_birth ? $patient->date_of_birth->age : ($patient?->age_approximate ?? '');

        $pharmacyOrders = $orders->where('type', 'pharmacy');
        $labOrders = $orders->where('type', 'lab');
        $imagingOrders = $orders->where('type', 'imaging');
    @endphp

    <button class="print-btn" onclick="window.print()">Print Discharge Summary</button>

    <div class="header">
        <h1>{{ $hospital->name ?? 'Hospital' }}</h1>
        <p>{{ $hospital->address ?? '' }}{{ $hospital->city ? ', ' . $hospital->city : '' }} | Phone: {{ $hospital->phone ?? '' }}</p>
    </div>

    <div class="doc-title">Discharge Summary</div>

    {{-- Patient Info --}}
    <div class="section">
        <div class="section-title">Patient Information</div>
        <div class="info-grid">
            <div class="item"><span class="label">Patient:</span> <span class="value">{{ $patient->name ?? '' }}</span></div>
            <div class="item"><span class="label">Phone:</span> <span class="value">{{ $patient->phone ?? '' }}</span></div>
            <div class="item"><span class="label">Age:</span> <span class="value">{{ $age }}</span></div>
            <div class="item"><span class="label">Gender:</span> <span class="value">{{ ucfirst($patient->gender ?? '') }}</span></div>
            @if($patient?->abha_number)
            <div class="item"><span class="label">{{ \App\Modules\Core\Services\RegionService::healthIdLabel() }}:</span> <span class="value">{{ \App\Modules\Core\Services\RegionService::formatHealthId($patient->abha_number) }}</span></div>
            @endif
        </div>
    </div>

    {{-- Encounter Info --}}
    <div class="section">
        <div class="section-title">Encounter Details</div>
        <div class="info-grid">
            <div class="item"><span class="label">Encounter #:</span> <span class="value">{{ $encounter->encounter_number }}</span></div>
            @if($doctor)
            <div class="item"><span class="label">Consulting Doctor:</span> <span class="value">Dr. {{ $doctor->name ?? '' }} ({{ $doctor->department ?? '' }})</span></div>
            @else
            <div class="item"><span class="label">Type:</span> <span class="value">Self-booked (no consultation)</span></div>
            @endif
            <div class="item"><span class="label">Date of Visit:</span> <span class="value">{{ $encounter->created_at->format('M d, Y') }}</span></div>
            <div class="item"><span class="label">Date of Discharge:</span> <span class="value">{{ $encounter->updated_at->format('M d, Y') }}</span></div>
        </div>
    </div>

    {{-- Chief Complaint --}}
    @if(!empty($intakeData['chief_complaint']))
    <div class="section">
        <div class="section-title">Chief Complaint</div>
        <div class="content-block">{{ $intakeData['chief_complaint'] }}</div>
    </div>
    @endif

    {{-- Diagnosis --}}
    @if(!empty($diagnosisCodes))
    <div class="section">
        <div class="section-title">Diagnosis</div>
        <div class="content-block">
            @foreach($diagnosisCodes as $code)
                {{ is_array($code) ? (($code['code'] ?? '') . ' - ' . ($code['description'] ?? $code['name'] ?? '')) : $code }}
            @if(!$loop->last)<br>@endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- SOAP Notes / Treatment Given --}}
    @if(!empty($soapNotes))
    <div class="section">
        <div class="section-title">Treatment Given</div>
        <div class="content-block">
            @if(!empty($soapNotes['subjective']))
<strong>Subjective:</strong> {{ $soapNotes['subjective'] }}

@endif
            @if(!empty($soapNotes['objective']))
<strong>Objective:</strong> {{ $soapNotes['objective'] }}

@endif
            @if(!empty($soapNotes['assessment']))
<strong>Assessment:</strong> {{ $soapNotes['assessment'] }}

@endif
            @if(!empty($soapNotes['plan']))
<strong>Plan:</strong> {{ $soapNotes['plan'] }}
@endif
        </div>
    </div>
    @endif

    {{-- Prescriptions at Discharge --}}
    @if($pharmacyOrders->count() > 0)
    <div class="section">
        <div class="section-title">Prescriptions at Discharge</div>
        <table class="med-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medication</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Timing</th>
                </tr>
            </thead>
            <tbody>
                @php $medIndex = 1; @endphp
                @foreach($pharmacyOrders as $order)
                    @foreach($order->items ?? [] as $med)
                    <tr>
                        <td>{{ $medIndex++ }}</td>
                        <td>{{ $med['name'] ?? '' }} {{ $med['dosage'] ?? '' }}</td>
                        <td>{{ $med['frequency'] ?? '' }}</td>
                        <td>{{ $med['duration'] ?? '' }}</td>
                        <td>{{ ucfirst($med['timing'] ?? '') }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Investigations --}}
    @if($labOrders->count() > 0 || $imagingOrders->count() > 0)
    <div class="section">
        <div class="section-title">Investigations</div>
        <ul class="investigation-list">
            @foreach($labOrders as $order)
                @foreach($order->items ?? [] as $test)
                <li>
                    {{ $test['name'] ?? $test['test_name'] ?? 'Lab Test' }}:
                    @if(!empty($order->results))
                        @php
                            $testResults = collect($order->results)->firstWhere('test_name', $test['name'] ?? '');
                        @endphp
                        {{ $testResults ? ($testResults['value'] ?? 'Completed') : 'Pending' }}
                    @else
                        Pending
                    @endif
                </li>
                @endforeach
            @endforeach
            @foreach($imagingOrders as $order)
                @foreach($order->items ?? [] as $test)
                <li>
                    {{ $test['name'] ?? $test['test_name'] ?? 'Imaging' }}:
                    @if(!empty($order->results))
                        Completed
                    @else
                        Pending
                    @endif
                </li>
                @endforeach
            @endforeach
        </ul>
    </div>
    @endif

    {{-- Follow-up --}}
    @if($encounter->follow_up_date)
    <div class="section">
        <div class="section-title">Follow-up</div>
        <div class="content-block">{{ $encounter->follow_up_date->format('M d, Y') }}</div>
    </div>
    @endif

    {{-- Advice --}}
    @if(!empty($encounter->follow_up_notes) || !empty($soapNotes['plan']))
    <div class="section">
        <div class="section-title">Advice</div>
        <div class="content-block">{{ $encounter->follow_up_notes ?? $soapNotes['plan'] ?? '' }}</div>
    </div>
    @endif

    {{-- Billing Summary --}}
    @if($bills->count() > 0)
    @php $mainBill = $bills->first(); @endphp
    <div class="section">
        <div class="section-title">Billing Summary</div>
        <div class="info-grid">
            <div class="item"><span class="label">Bill #:</span> <span class="value">{{ $mainBill->bill_number }}</span></div>
            <div class="item"><span class="label">Total:</span> <span class="value">{{ \App\Modules\Core\Services\RegionService::formatMoney($mainBill->total_amount) }}</span></div>
            <div class="item"><span class="label">Paid:</span> <span class="value">{{ \App\Modules\Core\Services\RegionService::formatMoney($mainBill->amount_paid ?? 0) }}</span></div>
            <div class="item"><span class="label">Status:</span> <span class="value">{{ ucfirst(is_object($mainBill->payment_status) ? $mainBill->payment_status->value : $mainBill->payment_status) }}</span></div>
        </div>
    </div>
    @endif

    <div class="signature">
        <div class="line"></div>
        @if($doctor)
        <div class="name">Dr. {{ $doctor->name ?? '' }}</div>
        <div class="dept">{{ $doctor->department ?? '' }}{{ $doctor?->specialization ? ' - ' . $doctor->specialization : '' }}</div>
        @else
        <div class="name">{{ $hospital->name ?? 'Hospital' }}</div>
        <div class="dept">Authorised Signatory</div>
        @endif
    </div>
</body>
</html>
