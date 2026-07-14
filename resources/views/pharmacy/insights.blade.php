@extends('layouts.app')
@section('title', 'Pharmacy Insights')
@section('page-title', 'Pharmacy Insights')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $maxRev = max(collect($trend)->max('revenue') ?: 0, 1);
    $trendTotal = collect($trend)->sum('revenue');
    // Potential margin locked in current inventory (retail − cost on hand).
    $invMargin = max(0, ($inventory['retail_value'] ?? 0) - ($inventory['cost_value'] ?? 0));

    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));
    $delta = function ($v) {
        $up = $v >= 0;
        return '<span class="text-xs font-medium '.($up ? 'text-green-600' : 'text-red-600').'">'
            .($up ? '▲ +' : '▼ ').$v.'% <span class="text-slate-400 font-normal">vs prev</span></span>';
    };
@endphp

{{-- Header + period selector --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Pharmacy Insights</h2>
        <p class="text-sm text-slate-500">Revenue, best sellers & inventory — {{ $periodLabel }}</p>
    </div>
    <div class="flex gap-2">
        @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
            <a href="{{ route('web.pharmacy.insights', ['period' => $key]) }}"
               class="px-3 py-1.5 text-sm rounded-lg border transition {{ $period === $key ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Revenue</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-emerald-50 text-emerald-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $money($kpis['revenue']) }}</p>
        <p class="mt-1">{!! $delta($kpis['revenue_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Prescriptions dispensed</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-50 text-blue-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($kpis['rx']) }}</p>
        <p class="mt-1">{!! $delta($kpis['rx_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Units dispensed</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-purple-50 text-purple-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($kpis['units']) }}</p>
        <p class="mt-1">{!! $delta($kpis['units_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Avg / prescription</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-amber-50 text-amber-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $money($kpis['avg_rx']) }}</p>
        <p class="mt-1 text-xs text-slate-400">basket value per Rx</p>
    </div>
</div>

{{-- Revenue trend --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Revenue trend · {{ $periodLabel }}</h3>
        <span class="text-xs text-slate-400">Total {{ $money($trendTotal) }}</span>
    </div>
    @if($trendTotal > 0)
    <div class="overflow-x-auto">
        <div class="flex items-end gap-1" style="height: 200px; min-width: {{ max(count($trend) * 26, 300) }}px;">
            @foreach($trend as $b)
                @php $h = round(($b['revenue'] / $maxRev) * 100); @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full group" style="min-width: 18px;">
                    <div class="w-full rounded-t transition-all {{ $b['revenue'] > 0 ? 'bg-emerald-500 group-hover:bg-emerald-600' : 'bg-slate-100' }}"
                         style="height: {{ max($h, $b['revenue'] > 0 ? 2 : 0) }}%;"
                         title="{{ $b['label'] }} — {{ $money($b['revenue']) }} · {{ number_format($b['units']) }} units"></div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-1 mt-2" style="min-width: {{ max(count($trend) * 26, 300) }}px;">
            @foreach($trend as $b)
                <div class="flex-1 text-center text-slate-400" style="min-width: 18px; font-size: 10px;">{{ $b['label'] }}</div>
            @endforeach
        </div>
    </div>
    @else
    <p class="text-sm text-slate-400 text-center py-12">No dispensing recorded in this period yet.</p>
    @endif
</div>

{{-- Best sellers --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- By revenue --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Top medicines by revenue</h3>
        @if($byRevenue->count())
        @php $mr = max($byRevenue->max('revenue') ?: 0, 1); @endphp
        <div class="space-y-3">
            @foreach($byRevenue as $i => $m)
            <div>
                <div class="flex items-center justify-between mb-1 gap-2">
                    <span class="text-sm font-medium text-slate-700 truncate">
                        <span class="text-slate-400">{{ $i + 1 }}.</span> {{ $m->description }}
                    </span>
                    <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">{{ $money($m->revenue) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full" style="width: {{ round(($m->revenue / $mr) * 100) }}%;"></div>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap">{{ number_format($m->units) }} units · {{ $m->share }}%</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No sales in this period.</p>
        @endif
    </div>

    {{-- By volume --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Sold most (by units)</h3>
        @if($byVolume->count())
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">#</th>
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Medicine</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Units</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($byVolume as $i => $m)
                <tr>
                    <td class="py-2.5 text-sm text-slate-400">{{ $i + 1 }}</td>
                    <td class="py-2.5 text-sm font-medium text-slate-800">{{ $m->description }}</td>
                    <td class="py-2.5 text-sm text-right font-semibold text-emerald-600">{{ number_format($m->units) }}</td>
                    <td class="py-2.5 text-sm text-right text-slate-600">{{ $money($m->revenue) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No sales in this period.</p>
        @endif
    </div>
</div>

{{-- Inventory valuation + risk --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 lg:col-span-1">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Inventory on hand</h3>
        <div class="space-y-4">
            <div>
                <p class="text-xs text-slate-500">Stock value (at cost)</p>
                <p class="text-xl font-bold text-slate-900">{{ $money($inventory['cost_value']) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Retail value</p>
                <p class="text-xl font-bold text-slate-900">{{ $money($inventory['retail_value']) }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Potential margin</p>
                <p class="text-xl font-bold text-emerald-600">{{ $money($invMargin) }}</p>
            </div>
            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <div class="flex-1">
                    <p class="text-2xl font-bold {{ $inventory['low'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $inventory['low'] }}</p>
                    <p class="text-xs text-slate-500">Low stock (≤10)</p>
                </div>
                <div class="flex-1">
                    <p class="text-2xl font-bold {{ $inventory['out'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $inventory['out'] }}</p>
                    <p class="text-xs text-slate-500">Out of stock</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Expiring within 90 days</h3>
        @if($inventory['expiring']->count())
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Medicine</th>
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Batch</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Qty</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Expires</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($inventory['expiring'] as $s)
                @php
                    $exp = \Carbon\Carbon::parse($s->expiry_date);
                    $days = (int) round(now()->startOfDay()->diffInDays($exp, false));
                @endphp
                <tr>
                    <td class="py-2.5 text-sm font-medium text-slate-800">{{ $s->medicine_name }}</td>
                    <td class="py-2.5 text-sm text-slate-500 font-mono text-xs">{{ $s->batch_number }}</td>
                    <td class="py-2.5 text-sm text-right text-slate-600">{{ number_format($s->quantity_available) }}</td>
                    <td class="py-2.5 text-sm text-right">
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $days <= 30 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $exp->format('d M Y') }} ({{ $days }}d)
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-slate-400 text-center py-8">Nothing expiring in the next 90 days.</p>
        @endif
    </div>
</div>
@endsection
