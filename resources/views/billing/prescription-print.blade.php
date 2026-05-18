<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prescription - {{ $encounter->encounter_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; padding: 24px; max-width: 700px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #334155; padding-bottom: 12px; margin-bottom: 16px; }
        .header-left h1 { font-size: 18px; font-weight: 700; }
        .header-left p { font-size: 12px; color: #64748b; }
        .header-right { text-align: right; font-size: 12px; color: #64748b; }
        .doctor-info { font-size: 13px; margin-bottom: 4px; }
        .doctor-info strong { font-weight: 600; }
        .date { font-size: 13px; color: #64748b; margin-bottom: 16px; }
        .patient-info { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; }
        .patient-info span { margin-right: 16px; }
        .rx-symbol { font-size: 32px; font-weight: 700; color: #3b82f6; margin: 16px 0 12px; font-family: serif; }
        .prescription-list { list-style: none; margin-bottom: 24px; }
        .prescription-list li { margin-bottom: 16px; padding-left: 8px; border-left: 3px solid #3b82f6; }
        .med-name { font-weight: 600; font-size: 14px; }
        .med-form { font-size: 12px; color: #64748b; }
        .med-dosage { font-size: 13px; color: #475569; margin-top: 2px; }
        .follow-up { margin-top: 20px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 13px; }
        .follow-up strong { font-weight: 600; }
        .advice { margin-top: 12px; font-size: 13px; color: #475569; }
        .signature { margin-top: 40px; text-align: right; }
        .signature .line { width: 200px; border-top: 1px solid #94a3b8; margin-left: auto; margin-bottom: 4px; }
        .signature .name { font-weight: 600; font-size: 14px; }
        .signature .reg { font-size: 12px; color: #64748b; }
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
        $items = $pharmacyOrder->items ?? [];
        $soapNotes = $encounter->soap_notes ?? [];
        $age = $patient->date_of_birth ? $patient->date_of_birth->age : ($patient->age_approximate ?? '-');
    @endphp

    <button class="print-btn" onclick="window.print()">Print Prescription</button>

    <div class="header">
        <div class="header-left">
            <h1>{{ $hospital->name ?? 'Hospital' }}</h1>
            <p>{{ $hospital->phone ?? '' }}</p>
        </div>
        <div class="header-right">
            <p>{{ $encounter->encounter_number }}</p>
        </div>
    </div>

    <div class="doctor-info">
        <strong>Doctor:</strong> Dr. {{ $doctor->name ?? '-' }}{{ $doctor->specialization ? ', ' . $doctor->specialization : '' }}
        {{ $doctor->department ? ' (' . $doctor->department . ')' : '' }}
    </div>
    <div class="date">Date: {{ $encounter->created_at->format('M d, Y') }}</div>

    <div class="patient-info">
        <span><strong>Patient:</strong> {{ $patient->name ?? '-' }}</span>
        <span><strong>Age:</strong> {{ $age }}</span>
        <span><strong>Gender:</strong> {{ ucfirst($patient->gender ?? '-') }}</span>
    </div>

    <div class="rx-symbol">&#8478;</div>

    @if(count($items) > 0)
    <ol class="prescription-list">
        @foreach($items as $index => $med)
        <li>
            <span class="med-name">{{ $med['name'] ?? 'Medicine' }} {{ $med['dosage'] ?? '' }}</span>
            @if(!empty($med['form']))
            <span class="med-form">({{ $med['form'] }})</span>
            @endif
            <div class="med-dosage">
                {{ $med['frequency'] ?? '' }}
                @if(!empty($med['timing']))
                &middot; {{ ucfirst($med['timing']) }}
                @endif
                @if(!empty($med['duration']))
                &middot; {{ $med['duration'] }}
                @endif
            </div>
        </li>
        @endforeach
    </ol>
    @else
    <p style="color: #94a3b8; font-style: italic; margin: 16px 0;">No medications prescribed.</p>
    @endif

    @if($encounter->follow_up_date)
    <div class="follow-up">
        <strong>Follow-up:</strong> {{ $encounter->follow_up_date->format('M d, Y') }}
    </div>
    @endif

    @if(!empty($soapNotes['plan']) || !empty($encounter->follow_up_notes))
    <div class="advice">
        <strong>Advice:</strong> {{ $encounter->follow_up_notes ?? ($soapNotes['plan'] ?? '') }}
    </div>
    @endif

    <div class="signature">
        <div class="line"></div>
        <div class="name">Dr. {{ $doctor->name ?? '-' }}</div>
        <div class="reg">Reg. No: {{ $doctor->registration_number ?? '____' }}</div>
    </div>
</body>
</html>
