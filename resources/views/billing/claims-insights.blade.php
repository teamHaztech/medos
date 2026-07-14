@extends('layouts.app')
@section('title', 'Claims Insights')
@section('page-title', 'Claims Insights')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));

    $trendTotal = collect($trend)->sum('value');
    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.number_format($b['value']).' claims'])->all();

    $funnelMax = max($funnel['filed'] ?: 0, 1);
    $funnelRows = [
        ['label'=>'Filed',    'value'=>$funnel['filed'],    'color'=>'bg-blue-500'],
        ['label'=>'Approved', 'value'=>$funnel['approved'], 'color'=>'bg-green-500'],
        ['label'=>'Denied',   'value'=>$funnel['denied'],   'color'=>'bg-red-500'],
        ['label'=>'Pending',  'value'=>$funnel['pending'],  'color'=>'bg-amber-500'],
    ];
    $insMax = max($byInsurer->max('claimed') ?: 0, 1);
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Claims Insights</h2>
        <p class="text-sm text-slate-500">Claim funnel, approvals & realization — {{ $periodLabel }}</p>
    </div>
    <x-insights.period-tabs route="web.claims.insights" :period="$period" />
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Claims filed" :value="number_format($kpis['filed'])" :delta="$kpis['filed_change']" accent="blue" icon="shield" />
    <x-insights.kpi label="Approval rate" :value="$kpis['approval_rate'].'%'" :sub="'of resolved claims'" accent="green" icon="check" />
    <x-insights.kpi label="Amount claimed" :value="$money($kpis['claimed'])" :delta="$kpis['claimed_change']" accent="purple" icon="currency" />
    <x-insights.kpi label="Amount approved" :value="$money($kpis['approved_amt'])" :delta="$kpis['approved_change']" accent="cyan" icon="cash" />
</div>

{{-- Secondary strip --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Realization rate</p>
        <p class="text-lg font-bold text-slate-900">{{ $kpis['realization'] }}%</p>
        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1.5">
            <div class="bg-cyan-500 h-1.5 rounded-full" style="width: {{ min(100, $kpis['realization']) }}%;"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Avg turnaround</p>
        <p class="text-lg font-bold text-slate-900">{{ $kpis['avg_tat_days'] ? $kpis['avg_tat_days'].' days' : '—' }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Pending review</p>
        <p class="text-lg font-bold {{ $funnel['pending'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format($funnel['pending']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Denied</p>
        <p class="text-lg font-bold {{ $funnel['denied'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ number_format($funnel['denied']) }}</p>
    </div>
</div>

{{-- Claims trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="purple"
        title="Claims filed · {{ $periodLabel }}"
        meta="{{ number_format($trendTotal) }} claims"
        empty="No claims filed in this period yet." />
</div>

{{-- Funnel + by insurer --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Claim funnel</h3>
        @if($funnel['filed'] > 0)
        <div class="space-y-3">
            @foreach($funnelRows as $f)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $f['label'] }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ number_format($f['value']) }}
                        <span class="text-xs text-slate-400 font-normal">· {{ round(($f['value'] / $funnelMax) * 100) }}%</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="{{ $f['color'] }} h-2 rounded-full" style="width: {{ round(($f['value'] / $funnelMax) * 100) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No claims in this period.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Performance by payer</h3>
        @if($byInsurer->count())
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Insurer</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Claims</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Claimed</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Approved</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($byInsurer as $ins)
                <tr>
                    <td class="py-2.5 text-sm font-medium text-slate-800">{{ $ins->insurer }}</td>
                    <td class="py-2.5 text-sm text-right text-slate-600">{{ number_format($ins->count) }}</td>
                    <td class="py-2.5 text-sm text-right text-slate-600">{{ $money($ins->claimed) }}</td>
                    <td class="py-2.5 text-sm text-right font-semibold text-green-600">{{ $money($ins->approved) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No claims in this period.</p>
        @endif
    </div>
</div>
@endsection
