@extends('layouts.app')
@section('title', 'IPD Insights')
@section('page-title', 'IPD Insights')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));
    $dtLabels = \App\Modules\Inpatient\Models\Admission::DISCHARGE_TYPES ?? [];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">IPD Insights</h2>
        <p class="text-sm text-slate-500">Occupancy, admissions & length of stay — {{ $periodLabel }}</p>
    </div>
    @unless($notReady ?? false)
        <x-insights.period-tabs route="web.ip.insights" :period="$period" />
    @endunless
</div>

@if($notReady ?? false)
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
        <p class="text-sm text-slate-500">Inpatient module isn't set up yet. Add wards and beds to start tracking occupancy.</p>
    </div>
@else
@php
    $trendTotal = collect($trend)->sum('value');
    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.number_format($b['value']).' admissions'])->all();
    $dtTotal = max($dischargeTypes->sum() ?: 0, 1);
@endphp

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Bed occupancy" :value="$kpis['occupancy'].'%'"
        :sub="$secondary['total_beds'] - $secondary['available_beds'].' of '.$secondary['total_beds'].' beds'" accent="cyan" icon="bed" />
    <x-insights.kpi label="Admissions" :value="number_format($kpis['admissions'])" :delta="$kpis['admissions_change']" accent="blue" icon="users" />
    <x-insights.kpi label="Discharges" :value="number_format($kpis['discharges'])" :delta="$kpis['discharges_change']" accent="green" icon="check" />
    <x-insights.kpi label="Avg length of stay" :value="$kpis['alos'].' days'" :sub="'per discharged patient'" accent="purple" icon="clock" />
</div>

{{-- Secondary strip --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Current inpatients</p>
        <p class="text-lg font-bold text-slate-900">{{ number_format($secondary['current_inpatients']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Available beds</p>
        <p class="text-lg font-bold {{ $secondary['available_beds'] > 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($secondary['available_beds']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Total active beds</p>
        <p class="text-lg font-bold text-slate-900">{{ number_format($secondary['total_beds']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">IPD revenue ({{ $periodLabel }})</p>
        <p class="text-lg font-bold text-slate-900">{{ $money($secondary['ipd_revenue']) }}</p>
    </div>
</div>

{{-- Admissions trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="blue"
        title="Admissions · {{ $periodLabel }}"
        meta="{{ number_format($trendTotal) }} admissions"
        empty="No admissions in this period yet." />
</div>

{{-- Ward utilization + discharge mix --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Ward utilization</h3>
        @if($wardUtil->count())
        <div class="space-y-3">
            @foreach($wardUtil as $w)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $w->name }}
                        <span class="text-xs text-slate-400 font-normal">{{ $w->occupied }}/{{ $w->total }} beds</span></span>
                    <span class="text-sm font-semibold {{ $w->pct >= 90 ? 'text-red-600' : ($w->pct >= 70 ? 'text-amber-600' : 'text-slate-900') }}">{{ $w->pct }}%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="{{ $w->pct >= 90 ? 'bg-red-500' : ($w->pct >= 70 ? 'bg-amber-500' : 'bg-cyan-500') }} h-2 rounded-full" style="width: {{ min(100, $w->pct) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No active wards.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Discharge outcomes · {{ $periodLabel }}</h3>
        @if($dischargeTypes->count())
        <div class="space-y-3">
            @foreach($dischargeTypes->sortDesc() as $type => $ct)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $dtLabels[$type] ?? ucfirst(str_replace('_',' ',$type)) }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ number_format($ct) }}
                        <span class="text-xs text-slate-400 font-normal">· {{ round(($ct / $dtTotal) * 100) }}%</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ round(($ct / $dtTotal) * 100) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No discharges in this period.</p>
        @endif
    </div>
</div>
@endif
@endsection
