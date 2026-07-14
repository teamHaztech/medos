@extends('layouts.app')
@section('title', 'Lab Insights')
@section('page-title', 'Lab Insights')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $maxVol = max(collect($trend)->max('lines') ?: 0, 1);
    $testsTotal = collect($trend)->sum('lines');
    $revTotal   = collect($trend)->sum('revenue');

    $srcLabels = ['lab' => 'Laboratory', 'imaging' => 'Imaging / Radiology', 'procedure' => 'Procedures'];
    $srcColors = ['lab' => 'bg-indigo-500', 'imaging' => 'bg-cyan-500', 'procedure' => 'bg-pink-500'];
    $catTotalRev = max($categories->sum('revenue') ?: 0, 1);

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
        <h2 class="text-lg font-semibold text-slate-800">Lab Insights</h2>
        <p class="text-sm text-slate-500">Test volume, revenue & turnaround — {{ $periodLabel }}</p>
    </div>
    <div class="flex gap-2">
        @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
            <a href="{{ route('web.lab.insights', ['period' => $key]) }}"
               class="px-3 py-1.5 text-sm rounded-lg border transition {{ $period === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
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
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-green-50 text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $money($kpis['revenue']) }}</p>
        <p class="mt-1">{!! $delta($kpis['revenue_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Tests performed</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-50 text-blue-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v6.2a2 2 0 01-.34 1.12l-4.32 6.48A2 2 0 006 20h12a2 2 0 001.66-3.2l-4.32-6.48A2 2 0 0115 9.2V3M8 3h8M9 14h6"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($kpis['tests']) }}</p>
        <p class="mt-1">{!! $delta($kpis['tests_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Reports verified</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-green-50 text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($kpis['completed']) }}</p>
        <p class="mt-1">{!! $delta($kpis['completed_change']) !!}</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-slate-500">Avg turnaround</p>
            <div class="w-9 h-9 rounded-lg flex items-center justify-center bg-amber-50 text-amber-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ $kpis['avg_tat'] ?: '—' }}</p>
        <p class="mt-1 text-xs text-slate-400">order → verified</p>
    </div>
</div>

{{-- Test volume trend --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-700">Tests performed · {{ $periodLabel }}</h3>
        <span class="text-xs text-slate-400">{{ number_format($testsTotal) }} tests · {{ $money($revTotal) }}</span>
    </div>
    @if($testsTotal > 0)
    <div class="overflow-x-auto">
        <div class="flex items-end gap-1" style="height: 200px; min-width: {{ max(count($trend) * 26, 300) }}px;">
            @foreach($trend as $b)
                @php $h = round(($b['lines'] / $maxVol) * 100); @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full group" style="min-width: 18px;">
                    <div class="w-full rounded-t transition-all {{ $b['lines'] > 0 ? 'bg-indigo-500' : 'bg-slate-100' }}"
                         style="height: {{ max($h, $b['lines'] > 0 ? 2 : 0) }}%;"
                         title="{{ $b['label'] }} — {{ number_format($b['lines']) }} tests · {{ $money($b['revenue']) }}"></div>
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
    <p class="text-sm text-slate-400 text-center py-12">No tests ordered in this period yet.</p>
    @endif
</div>

{{-- Most-performed tests + category split --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Most performed (volume) --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Most-performed tests</h3>
        @if($byVolume->count())
        @php $mv = max($byVolume->max('lines') ?: 0, 1); @endphp
        <div class="space-y-3">
            @foreach($byVolume as $i => $t)
            <div>
                <div class="flex items-center justify-between mb-1 gap-2">
                    <span class="text-sm font-medium text-slate-700 truncate">
                        <span class="text-slate-400">{{ $i + 1 }}.</span> {{ $t->description }}
                    </span>
                    <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">{{ number_format($t->lines) }}×</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-slate-100 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ round(($t->lines / $mv) * 100) }}%;"></div>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap">{{ $money($t->revenue) }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No tests in this period.</p>
        @endif
    </div>

    {{-- Category split + top by revenue --}}
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Revenue by category</h3>
            @if($categories->count())
            <div class="space-y-3">
                @foreach($categories as $cat)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-slate-700">{{ $srcLabels[$cat->source] ?? ucfirst($cat->source) }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $money($cat->revenue) }} <span class="text-xs text-slate-400 font-normal">· {{ number_format($cat->lines) }} tests</span></span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="{{ $srcColors[$cat->source] ?? 'bg-slate-400' }} h-2 rounded-full" style="width: {{ round(($cat->revenue / $catTotalRev) * 100) }}%;"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Top revenue earners</h3>
            @if($byRevenue->count())
            <table class="w-full">
                <tbody class="divide-y divide-slate-50">
                    @foreach($byRevenue->take(6) as $i => $t)
                    <tr>
                        <td class="py-2 text-sm text-slate-400 w-6">{{ $i + 1 }}</td>
                        <td class="py-2 text-sm font-medium text-slate-800">{{ $t->description }}</td>
                        <td class="py-2 text-sm text-right text-slate-500">{{ number_format($t->lines) }}×</td>
                        <td class="py-2 text-sm text-right font-semibold text-green-600">{{ $money($t->revenue) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
            @endif
        </div>
    </div>
</div>
@endsection
