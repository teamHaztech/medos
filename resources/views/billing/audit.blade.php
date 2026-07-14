@extends('layouts.app')
@section('title', 'Billing Audit & Reports')
@section('page-title', 'Billing — Audit & Reports')

@php $q = ['from' => $from, 'to' => $to]; @endphp

@section('content')
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.billing.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Dashboard</a>
        <a href="{{ route('web.billing.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Bills</a>
        <a href="{{ route('web.billing.services') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Service Master</a>
        <a href="{{ route('web.billing.audit') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Audit &amp; Reports</a>
        <form method="GET" class="ml-auto flex items-end gap-2">
            <div><label class="block text-xs text-slate-400 uppercase">From</label><input type="date" name="from" value="{{ $from }}" class="input-field text-sm"></div>
            <div><label class="block text-xs text-slate-400 uppercase">To</label><input type="date" name="to" value="{{ $to }}" class="input-field text-sm"></div>
            <button class="btn-primary px-4">Apply</button>
        </form>
    </div>

    <p class="text-sm text-slate-500 mb-4">Revenue-cycle audit built from the charge-capture ledger — every chargeable event across the hospital (consultations, lab, pharmacy, consumables, IPD) posts here. Period: <strong>{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }}</strong> to <strong>{{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</strong>.</p>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Net Revenue</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $cur }}{{ number_format($totals['net'], 2) }}</p>
            <p class="text-xs text-slate-400 mt-1">Taxable + exempt, excl. GST</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">GST Collected</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $cur }}{{ number_format($totals['tax'], 2) }}</p>
            <p class="text-xs text-slate-400 mt-1">CGST + SGST</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Gross (incl GST)</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ $cur }}{{ number_format($totals['gross'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Collections</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $cur }}{{ number_format($collections, 2) }}</p>
            <p class="text-xs text-slate-400 mt-1">Payments received</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Pending Charges</p>
            <p class="text-2xl font-bold {{ $totals['pending'] > 0 ? 'text-amber-600' : 'text-slate-800' }} mt-1">{{ $cur }}{{ number_format($totals['pending'], 2) }}</p>
            <p class="text-xs text-slate-400 mt-1">Not yet billed</p>
        </div>
    </div>

    {{-- Export bar --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider mr-1">Downloads for audit:</span>
        <a href="{{ route('web.billing.audit.charges', $q) }}" class="btn-secondary text-sm">⤓ Charge Ledger (CSV)</a>
        <a href="{{ route('web.billing.audit.gst', $q) }}" class="btn-secondary text-sm">⤓ GST Summary (CSV)</a>
        <a href="{{ route('web.billing.audit.payments', $q) }}" class="btn-secondary text-sm">⤓ Payments (CSV)</a>
        <a href="{{ route('web.billing.export', array_merge($q, ['format' => 'csv'])) }}" class="btn-secondary text-sm">⤓ Bills (CSV)</a>
        <a href="{{ route('web.billing.export', array_merge($q, ['format' => 'tally'])) }}" class="btn-secondary text-sm">⤓ Tally (XML)</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Revenue by department --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-800">Revenue by Department</h3></div>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Department</th>
                        <th class="table-header text-right">Net</th>
                        <th class="table-header text-right">GST</th>
                        <th class="table-header text-right">Pending</th>
                        <th class="table-header text-right">Items</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($depts as $name => $d)
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell font-medium">{{ $name }}</td>
                            <td class="table-cell text-right">{{ $cur }}{{ number_format($d['net'], 2) }}</td>
                            <td class="table-cell text-right text-slate-500">{{ $cur }}{{ number_format($d['tax'], 2) }}</td>
                            <td class="table-cell text-right {{ $d['pending'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $cur }}{{ number_format($d['pending'], 2) }}</td>
                            <td class="table-cell text-right text-slate-500">{{ $d['cnt'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-400 text-sm">No charges in this period.</td></tr>
                    @endforelse
                </tbody>
                @if(count($depts))
                <tfoot class="bg-slate-50 border-t border-slate-200">
                    <tr class="font-semibold text-slate-800">
                        <td class="table-cell">Total</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($totals['net'], 2) }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($totals['tax'], 2) }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($totals['pending'], 2) }}</td>
                        <td class="table-cell"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- GST summary --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-800">GST Summary (for filing)</h3></div>
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Rate</th>
                        <th class="table-header text-right">Taxable Value</th>
                        <th class="table-header text-right">CGST</th>
                        <th class="table-header text-right">SGST</th>
                        <th class="table-header text-right">Total GST</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($gstRows as $g)
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell font-medium">{{ rtrim(rtrim(number_format($g['rate'], 2), '0'), '.') }}%</td>
                            <td class="table-cell text-right">{{ $cur }}{{ number_format($g['taxable'], 2) }}</td>
                            <td class="table-cell text-right text-slate-500">{{ $cur }}{{ number_format($g['cgst'], 2) }}</td>
                            <td class="table-cell text-right text-slate-500">{{ $cur }}{{ number_format($g['sgst'], 2) }}</td>
                            <td class="table-cell text-right font-medium">{{ $cur }}{{ number_format($g['gst'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-400 text-sm">No taxable charges in this period.</td></tr>
                    @endforelse
                </tbody>
                @if(count($gstRows))
                <tfoot class="bg-slate-50 border-t border-slate-200">
                    <tr class="font-semibold text-slate-800">
                        <td class="table-cell">Total</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($gstRows->sum('taxable'), 2) }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($gstRows->sum('cgst'), 2) }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($gstRows->sum('sgst'), 2) }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($gstRows->sum('gst'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Detailed source breakdown --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-800">Revenue by Source (detail)</h3></div>
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Source</th>
                    <th class="table-header text-right">Net</th>
                    <th class="table-header text-right">Billed</th>
                    <th class="table-header text-right">Pending</th>
                    <th class="table-header text-right">Cancelled</th>
                    <th class="table-header text-right">GST</th>
                    <th class="table-header text-right">Items</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($sources as $key => $s)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $s['label'] }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($s['net'], 2) }}</td>
                        <td class="table-cell text-right text-green-600">{{ $cur }}{{ number_format($s['billed'], 2) }}</td>
                        <td class="table-cell text-right {{ $s['pending'] > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $cur }}{{ number_format($s['pending'], 2) }}</td>
                        <td class="table-cell text-right text-slate-400">{{ $cur }}{{ number_format($s['cancelled'], 2) }}</td>
                        <td class="table-cell text-right text-slate-500">{{ $cur }}{{ number_format($s['tax'], 2) }}</td>
                        <td class="table-cell text-right text-slate-500">{{ $s['cnt'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-slate-400 text-sm">No charges in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- Charge ledger (audit trail) --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800">Charge Ledger — latest 50 (audit trail)</h3>
            <a href="{{ route('web.billing.audit.charges', $q) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">⤓ Download full ledger</a>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Posted</th>
                    <th class="table-header">Source</th>
                    <th class="table-header">Description</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header text-right">Net</th>
                    <th class="table-header text-right">GST</th>
                    <th class="table-header text-right">Total</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Bill</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($ledger as $c)
                    @php
                        $net = (float) $c->total;
                        $gst = $c->is_taxable ? round($net * (float) $c->gst_rate / 100, 2) : 0;
                        $st  = $c->status;
                        $badge = $st === 'billed' ? 'bg-green-100 text-green-700' : ($st === 'cancelled' ? 'bg-slate-100 text-slate-500' : 'bg-amber-100 text-amber-700');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell text-xs text-slate-500 whitespace-nowrap">{{ optional($c->posted_at ?? $c->created_at)->format('d M H:i') }}</td>
                        <td class="table-cell text-xs">{{ \App\Modules\Billing\Models\ChargeItem::SOURCES[$c->source] ?? $c->source }}</td>
                        <td class="table-cell">{{ \Illuminate\Support\Str::limit($c->description, 40) }}</td>
                        <td class="table-cell text-slate-600">{{ $c->patient?->name ?? '—' }}</td>
                        <td class="table-cell text-right">{{ $cur }}{{ number_format($net, 2) }}</td>
                        <td class="table-cell text-right text-slate-500">{{ $cur }}{{ number_format($gst, 2) }}</td>
                        <td class="table-cell text-right font-medium">{{ $cur }}{{ number_format($net + $gst, 2) }}</td>
                        <td class="table-cell"><span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badge }}">{{ ucfirst($st) }}</span></td>
                        <td class="table-cell">
                            @if($c->bill_id)
                                <a href="{{ route('web.billing.show', $c->bill_id) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800">{{ $c->bill?->bill_number ?? 'View' }}</a>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-8 text-center text-slate-400 text-sm">No charges in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
@endsection
