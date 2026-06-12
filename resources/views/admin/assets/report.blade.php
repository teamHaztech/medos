<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Asset Register Report</title>
    @php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: #1e293b; margin: 24px; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #64748b; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: #475569; }
        .pill { display: inline-block; padding: 1px 6px; border-radius: 999px; font-size: 10px; font-weight: 600; }
        .ok { background: #dcfce7; color: #15803d; }
        .warn { background: #fef3c7; color: #b45309; }
        .bad { background: #fee2e2; color: #b91c1c; }
        .none { background: #f1f5f9; color: #64748b; }
        .toolbar { margin-bottom: 12px; }
        button { padding: 6px 14px; border: 1px solid #cbd5e1; background: #fff; border-radius: 6px; cursor: pointer; font-size: 12px; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>

    <h1>Asset Register &amp; Warranty Status</h1>
    <p class="muted">{{ $hospital?->name ?? 'Hospital' }} · Generated {{ now()->format('M d, Y g:i A') }} · {{ $assets->count() }} assets</p>

    <table>
        <thead>
            <tr>
                <th>Asset</th><th>Type</th><th>Dept</th><th>Serial</th><th>Status</th>
                <th>Vendor</th><th>Purchase</th><th>Warranty</th><th>Expiry</th><th>State</th>
            </tr>
        </thead>
        <tbody>
            @foreach($assets as $a)
                @php
                    $w = $a->activeWarranty();
                    $latest = $w ?? $a->warranties->first();
                    if ($w) { $days = $w->daysToExpiry(); $cls = $days <= 30 ? 'warn' : 'ok'; $state = $days . 'd left'; }
                    elseif ($a->warranties->count()) { $cls = 'bad'; $state = 'Expired'; }
                    else { $cls = 'none'; $state = 'None'; }
                @endphp
                <tr>
                    <td>{{ $a->asset_name }}</td>
                    <td>{{ $a->asset_type }}</td>
                    <td>{{ $a->department }}</td>
                    <td>{{ $a->serial_number }}</td>
                    <td>{{ $a->statusLabel() }}</td>
                    <td>{{ $a->vendor?->name }}</td>
                    <td>{{ $a->purchase_cost ? $cur . number_format($a->purchase_cost, 0) : '' }}</td>
                    <td>{{ $latest?->typeLabel() }}</td>
                    <td>{{ optional($latest?->end_date)->format('M d, Y') }}</td>
                    <td><span class="pill {{ $cls }}">{{ $state }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
