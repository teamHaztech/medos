@extends('layouts.app')
@section('title', 'Revenue Insights')
@section('page-title', 'Revenue Insights')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));

    $srcLabels  = \App\Modules\Billing\Models\ChargeItem::SOURCES;
    $srcColors  = ['consultation'=>'bg-blue-500','lab'=>'bg-indigo-500','imaging'=>'bg-cyan-500','pharmacy'=>'bg-green-500','procedure'=>'bg-purple-500','room'=>'bg-amber-500','nursing'=>'bg-pink-500','registration'=>'bg-rose-500','consumable'=>'bg-red-500','other'=>'bg-slate-400'];
    $methodLabels = \App\Modules\Billing\Models\BillPayment::METHODS;
    $methodColors = ['cash'=>'bg-green-500','upi'=>'bg-blue-500','card'=>'bg-purple-500','insurance'=>'bg-cyan-500','bank'=>'bg-amber-500','cheque'=>'bg-pink-500'];

    $srcTotal    = max($bySource->sum('revenue') ?: 0, 1);
    $methodTotal = max($methodMix->sum('amount') ?: 0, 1);
    $trendTotal  = collect($trend)->sum('value');

    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.$money($b['value'])])->all();

    $statusStyle = ['paid'=>'bg-green-100 text-green-700','partial'=>'bg-amber-100 text-amber-700','pending'=>'bg-slate-100 text-slate-600','cancelled'=>'bg-red-100 text-red-700','refunded'=>'bg-purple-100 text-purple-700'];
@endphp

{{-- Header + period selector --}}
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Revenue Insights</h2>
        <p class="text-sm text-slate-500">Revenue, collections & receivables — {{ $periodLabel }}</p>
    </div>
    <x-insights.period-tabs route="web.billing.insights" :period="$period" />
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Revenue billed" :value="$money($kpis['revenue'])" :delta="$kpis['revenue_change']" accent="green" icon="currency" />
    <x-insights.kpi label="Collected" :value="$money($kpis['collected'])" :delta="$kpis['collected_change']" accent="blue" icon="cash" />
    <x-insights.kpi label="Outstanding (AR)" :value="$money($kpis['outstanding'])" :sub="'across all open bills'" accent="amber" icon="alert" />
    <x-insights.kpi label="Bills issued" :value="number_format($kpis['bills'])" :delta="$kpis['bills_change']" accent="purple" icon="doc" />
</div>

{{-- Secondary metric strip --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Collection rate</p>
        <p class="text-lg font-bold text-slate-900">{{ $kpis['collection_rate'] }}%</p>
        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1.5">
            <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ min(100, $kpis['collection_rate']) }}%;"></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Avg bill value</p>
        <p class="text-lg font-bold text-slate-900">{{ $money($kpis['avg_bill']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Refunds ({{ $periodLabel }})</p>
        <p class="text-lg font-bold {{ $kpis['refunded'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $money($kpis['refunded']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Bills by status</p>
        <div class="flex flex-wrap gap-1 mt-1.5">
            @forelse($statusMix as $st => $ct)
                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusStyle[$st] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst($st) }} {{ $ct }}</span>
            @empty
                <span class="text-xs text-slate-400"></span>
            @endforelse
        </div>
    </div>
</div>

{{-- Revenue trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="green"
        title="Revenue trend · {{ $periodLabel }}"
        meta="Total {{ $money($trendTotal) }}"
        empty="No revenue recorded in this period yet." />
</div>

{{-- How revenue is made + payment mix --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Revenue by source --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700">How revenue is made</h3>
        <p class="text-xs text-slate-400 mb-4">by captured charge line</p>
        @if($bySource->count())
        <div class="space-y-3">
            @foreach($bySource as $s)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $srcLabels[$s->source] ?? ucfirst($s->source) }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $money($s->revenue) }}
                        <span class="text-xs text-slate-400 font-normal">· {{ round(($s->revenue / $srcTotal) * 100) }}%</span></span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="{{ $srcColors[$s->source] ?? 'bg-slate-400' }} h-2 rounded-full" style="width: {{ round(($s->revenue / $srcTotal) * 100) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No revenue in this period.</p>
        @endif
    </div>

    {{-- Payment method mix --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Payment method mix</h3>
        @if($methodMix->count())
        <div class="space-y-3">
            @foreach($methodMix as $m)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $methodLabels[$m->method] ?? ucfirst($m->method) }}
                        <span class="text-xs text-slate-400 font-normal">({{ $m->count }})</span></span>
                    <span class="text-sm font-semibold text-slate-900">{{ $money($m->amount) }}</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="{{ $methodColors[$m->method] ?? 'bg-slate-400' }} h-2 rounded-full" style="width: {{ round(($m->amount / $methodTotal) * 100) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No payments collected in this period.</p>
        @endif
    </div>
</div>

{{-- Top services --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    <h3 class="text-sm font-semibold text-slate-700 mb-4">Top revenue-earning services</h3>
    @if($topItems->count())
    <table class="w-full">
        <thead>
            <tr class="border-b border-slate-200">
                <th class="text-left text-xs font-medium text-slate-500 pb-2">#</th>
                <th class="text-left text-xs font-medium text-slate-500 pb-2">Service / item</th>
                <th class="text-left text-xs font-medium text-slate-500 pb-2">Category</th>
                <th class="text-right text-xs font-medium text-slate-500 pb-2">Volume</th>
                <th class="text-right text-xs font-medium text-slate-500 pb-2">Revenue</th>
                <th class="text-right text-xs font-medium text-slate-500 pb-2">Share</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($topItems as $i => $t)
            <tr>
                <td class="py-2.5 text-sm text-slate-400">{{ $i + 1 }}</td>
                <td class="py-2.5 text-sm font-medium text-slate-800">{{ $t->description }}</td>
                <td class="py-2.5 text-sm text-slate-500">{{ $srcLabels[$t->source] ?? ucfirst($t->source) }}</td>
                <td class="py-2.5 text-sm text-right text-slate-600">{{ number_format($t->units) }}</td>
                <td class="py-2.5 text-sm text-right font-semibold text-green-600">{{ $money($t->revenue) }}</td>
                <td class="py-2.5 text-sm text-right text-slate-500">{{ $t->share }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="text-sm text-slate-400 text-center py-8">No billed services in this period.</p>
    @endif
</div>
@endsection
