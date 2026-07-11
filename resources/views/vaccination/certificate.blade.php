@php
use App\Modules\Vaccination\Models\Vaccine;
$dob = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Immunization Certificate — {{ $patient->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #1e293b; background: #f1f5f9; padding: 24px; }
        .sheet { max-width: 760px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .head { padding: 24px 28px; border-bottom: 3px solid #2563eb; }
        .head h1 { font-size: 20px; color: #0f172a; }
        .head p { font-size: 13px; color: #64748b; margin-top: 2px; }
        .badge { display: inline-block; margin-top: 8px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #2563eb; font-weight: 700; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; padding: 18px 28px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
        .meta div { font-size: 13px; }
        .meta span { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px 28px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
        td { padding: 10px 28px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .foot { padding: 20px 28px; font-size: 12px; color: #94a3b8; display: flex; justify-content: space-between; align-items: flex-end; }
        .sign { text-align: center; }
        .sign .line { width: 180px; border-top: 1px solid #94a3b8; margin-bottom: 4px; }
        .btn { display: inline-block; margin: 0 auto 16px; }
        @media print { body { background: #fff; padding: 0; } .noprint { display: none; } .sheet { border: none; } }
    </style>
</head>
<body>
    <div class="noprint" style="max-width:760px;margin:0 auto 12px;text-align:right;">
        <button onclick="window.print()" style="padding:8px 16px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;">Print / Save PDF</button>
    </div>
    <div class="sheet">
        <div class="head">
            <h1>{{ $hospital->name ?? 'MedOS Hospital' }}</h1>
            <p>Immunization Certificate</p>
            <span class="badge">Official record of vaccination</span>
        </div>
        <div class="meta">
            <div><span>Name:</span> <strong>{{ $patient->name }}</strong></div>
            <div><span>Phone:</span> {{ $patient->phone ?? '—' }}</div>
            <div><span>Date of birth:</span> {{ $dob ? $dob->format('d M Y') : '—' }}</div>
            <div><span>Gender:</span> {{ ucfirst($patient->gender ?? '—') }}</div>
            @if($patient->abha_number)<div><span>ABHA:</span> {{ $patient->abha_number }}</div>@endif
            <div><span>Issued:</span> {{ now()->format('d M Y') }}</div>
        </div>
        <table>
            <thead><tr><th>Vaccine</th><th>Dose</th><th>Date given</th><th>Route</th><th>Batch</th></tr></thead>
            <tbody>
                @forelse($doses as $d)
                <tr>
                    <td>{{ $d->vaccine?->name ?? '—' }}</td>
                    <td>{{ $d->dose_number }}</td>
                    <td>{{ optional($d->given_date)->format('d M Y') }}</td>
                    <td>{{ Vaccine::ROUTES[$d->route] ?? $d->route ?? '—' }}</td>
                    <td>{{ $d->batch_number ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">No doses recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="foot">
            <div>This certificate reflects immunization records held at {{ $hospital->name ?? 'the facility' }} as of {{ now()->format('d M Y') }}.<br>Total doses: {{ $doses->count() }}</div>
            <div class="sign"><div class="line"></div>Authorised signatory</div>
        </div>
    </div>
</body>
</html>
