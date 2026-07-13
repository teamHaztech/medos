<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt - {{ $bill->bill_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.5; padding: 20px; max-width: 800px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { font-size: 22px; font-weight: 700; margin-bottom: 2px; }
        .header p { font-size: 12px; color: #64748b; }
        .divider { border: none; border-top: 1px solid #cbd5e1; margin: 16px 0; }
        .title { text-align: center; font-size: 18px; font-weight: 700; letter-spacing: 2px; margin: 12px 0; color: #334155; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 13px; }
        .info-row .label { color: #64748b; }
        .info-row .value { font-weight: 500; }
        .info-section { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        th { text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; padding: 8px 6px; border-bottom: 2px solid #e2e8f0; }
        th.right, td.right { text-align: right; }
        th.center, td.center { text-align: center; }
        td { padding: 8px 6px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        .totals { margin-top: 8px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; }
        .totals .row.bold { font-weight: 700; font-size: 16px; border-top: 2px solid #334155; padding-top: 8px; margin-top: 4px; }
        .footer { text-align: center; margin-top: 24px; font-size: 13px; color: #64748b; }
        .print-btn { display: block; margin: 0 auto 20px; padding: 10px 32px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .print-btn:hover { background: #2563eb; }

        @media print {
            .print-btn { display: none !important; }
            body { padding: 0; margin: 0; }
            .divider { border-color: #000; }
        }
    </style>
</head>
<body>
    @php
        $currency = \App\Modules\Core\Services\RegionService::currency();
        $taxName = \App\Modules\Core\Services\RegionService::taxName();
        $status = is_object($bill->payment_status) ? $bill->payment_status->value : $bill->payment_status;
        $cancelled = ! is_null($bill->cancelled_at);
        $refunded = round((float) abs(($bill->payments ?? collect())->where('amount', '<', 0)->sum('amount')), 2);
        $cfg = is_array($hospital?->config) ? $hospital->config : json_decode($hospital?->config ?? '{}', true);
        $gstin = $cfg['gstin'] ?? null;
        $gstState = $cfg['gst_state'] ?? null;
        $hasGst = (float) $bill->tax_amount > 0 || $gstin;
        $docTitle = $cancelled ? 'CANCELLED / VOID' : ($hasGst ? 'TAX INVOICE' : 'RECEIPT');
    @endphp

    <button class="print-btn" onclick="window.print()">Print {{ $hasGst ? 'Invoice' : 'Receipt' }}</button>

    <div class="header">
        <h1>{{ $hospital->name ?? 'Hospital' }}</h1>
        <p>{{ $hospital->address ?? '' }}{{ $hospital->city ? ', ' . $hospital->city : '' }}</p>
        <p>Phone: {{ $hospital->phone ?? '-' }}</p>
        @if($gstin)<p><strong>GSTIN: {{ $gstin }}</strong>{{ $gstState ? ' · State: '.$gstState : '' }}</p>@endif
    </div>

    <hr class="divider">
    <div class="title" style="{{ $cancelled ? 'color:#dc2626;' : '' }}">{{ $docTitle }}</div>
    @if($cancelled)
    <div style="border:2px solid #dc2626; color:#dc2626; text-align:center; font-weight:700; padding:8px; border-radius:6px; margin:8px 0;">
        THIS BILL WAS CANCELLED — NOT A VALID RECEIPT{{ $bill->cancel_reason ? ' · '.$bill->cancel_reason : '' }}
    </div>
    @endif

    <div class="info-section">
        <div class="info-row">
            <span class="label">Bill #:</span>
            <span class="value">{{ $bill->bill_number }}</span>
        </div>
        <div class="info-row">
            <span class="label">Date:</span>
            <span class="value">{{ ($bill->issued_at ?? $bill->created_at)->format('M d, Y') }}</span>
        </div>
    </div>

    <hr class="divider">

    <div class="info-section">
        <div class="info-row">
            <span class="label">Patient:</span>
            <span class="value">{{ $bill->patient->name ?? '-' }} | Phone: {{ $bill->patient->phone ?? '-' }}</span>
        </div>
        @if($bill->encounter && $bill->encounter->doctor)
        <div class="info-row">
            <span class="label">Doctor:</span>
            <span class="value">Dr. {{ $bill->encounter->doctor->name ?? '-' }} | Dept: {{ $bill->encounter->doctor->department ?? '-' }}</span>
        </div>
        @endif
    </div>

    <hr class="divider">

    <table>
        <thead>
            <tr>
                <th>Description</th>
                @if($hasGst)<th class="center">HSN/SAC</th>@endif
                <th class="center">Qty</th>
                <th class="right">Rate</th>
                @if($hasGst)<th class="center">GST%</th>@endif
                <th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->line_items ?? [] as $item)
            <tr>
                <td>{{ $item['description'] ?? '-' }}</td>
                @if($hasGst)<td class="center">{{ $item['hsn_sac'] ?? '—' }}</td>@endif
                <td class="center">{{ rtrim(rtrim(number_format($item['quantity'] ?? 1, 2), '0'), '.') }}</td>
                <td class="right">{{ $currency }}{{ number_format($item['unit_price'] ?? 0, 2) }}</td>
                @if($hasGst)<td class="center">{{ ($item['gst_rate'] ?? 0) > 0 ? rtrim(rtrim(number_format($item['gst_rate'], 2), '0'), '.').'%' : 'Exempt' }}</td>@endif
                <td class="right">{{ $currency }}{{ number_format($item['total'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <hr class="divider">

    <div class="totals">
        <div class="row">
            <span>Subtotal:</span>
            <span>{{ $currency }}{{ number_format($bill->subtotal, 2) }}</span>
        </div>
        @if(($bill->cgst_amount ?? 0) > 0 || ($bill->sgst_amount ?? 0) > 0)
        <div class="row">
            <span>CGST:</span>
            <span>{{ $currency }}{{ number_format($bill->cgst_amount, 2) }}</span>
        </div>
        <div class="row">
            <span>SGST:</span>
            <span>{{ $currency }}{{ number_format($bill->sgst_amount, 2) }}</span>
        </div>
        @if(($bill->igst_amount ?? 0) > 0)
        <div class="row">
            <span>IGST:</span>
            <span>{{ $currency }}{{ number_format($bill->igst_amount, 2) }}</span>
        </div>
        @endif
        @else
        <div class="row">
            <span>{{ $taxName }}:</span>
            <span>{{ $currency }}{{ number_format($bill->tax_amount, 2) }}</span>
        </div>
        @endif
        <div class="row">
            <span>Discount:{{ $bill->discount_reason ? ' ('.$bill->discount_reason.')' : '' }}</span>
            <span>-{{ $currency }}{{ number_format($bill->discount_amount, 2) }}</span>
        </div>
        <div class="row">
            <span>Insurance Covered:</span>
            <span>-{{ $currency }}{{ number_format($bill->insurance_covered, 2) }}</span>
        </div>
        <div class="row bold">
            <span>TOTAL:</span>
            <span>{{ $currency }}{{ number_format($bill->total_amount, 2) }}</span>
        </div>
        @if(($bill->deposit_applied ?? 0) > 0)
        <div class="row">
            <span>Deposit Applied:</span>
            <span>-{{ $currency }}{{ number_format($bill->deposit_applied, 2) }}</span>
        </div>
        @endif
        <div class="row">
            <span>Amount Paid:</span>
            <span>{{ $currency }}{{ number_format($bill->amount_paid ?? 0, 2) }}</span>
        </div>
        @if($refunded > 0)
        <div class="row" style="color:#7c3aed;">
            <span>Refunded:</span>
            <span>-{{ $currency }}{{ number_format($refunded, 2) }}</span>
        </div>
        @endif
        <div class="row">
            <span>Balance Due:</span>
            <span>{{ $currency }}{{ number_format($bill->balance_due ?? 0, 2) }}</span>
        </div>
    </div>

    <hr class="divider">

    <div class="info-row" style="margin-top: 8px;">
        <span class="label">Payment:</span>
        <span class="value">{{ ucfirst($bill->payment_method ?? '-') }} | Ref: {{ $bill->payment_reference ?? '-' }}</span>
    </div>

    <hr class="divider">

    <div class="footer">
        <p>Thank you for visiting!</p>
        <p style="font-size: 11px; margin-top: 4px;">This is a computer-generated receipt.</p>
    </div>
</body>
</html>
