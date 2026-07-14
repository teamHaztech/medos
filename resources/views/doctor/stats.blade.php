@extends('layouts.app')
@section('title', 'My Stats')
@section('page-title', 'My Stats')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));

    $trendTotal = collect($trend)->sum('value');
    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.number_format($b['value']).' encounters'])->all();

    $statusStyle = ['completed'=>'bg-green-100 text-green-700','in_progress'=>'bg-blue-100 text-blue-700','cancelled'=>'bg-red-100 text-red-700'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">My Stats</h2>
        <p class="text-sm text-slate-500">{{ $staff->name ?? 'Doctor' }} · {{ $periodLabel }}</p>
    </div>
    <x-insights.period-tabs route="web.doctor.stats" :period="$period" />
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Patients seen" :value="number_format($kpis['patients'])" :delta="$kpis['patients_change']" accent="blue" icon="users" />
    <x-insights.kpi label="Consultations" :value="number_format($kpis['completed'])" :delta="$kpis['completed_change']" accent="green" icon="check" />
    <x-insights.kpi label="Revenue generated" :value="$money($kpis['revenue'])" :delta="$kpis['revenue_change']" accent="purple" icon="currency" />
    <x-insights.kpi label="Avg consult time" :value="$kpis['avg_duration'].' min'" :sub="'per completed visit'" accent="amber" icon="clock" />
</div>

{{-- Secondary strip --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Encounters</p>
        <p class="text-lg font-bold text-slate-900">{{ number_format($kpis['encounters']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Completion rate</p>
        <p class="text-lg font-bold text-green-600">{{ $kpis['completion_rate'] }}%</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">Pending</p>
        <p class="text-lg font-bold {{ $kpis['pending'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ number_format($kpis['pending']) }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <p class="text-xs text-slate-500">No-shows</p>
        <p class="text-lg font-bold {{ $kpis['no_shows'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ number_format($kpis['no_shows']) }}</p>
    </div>
</div>

{{-- Encounters trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="blue"
        title="Patient encounters · {{ $periodLabel }}"
        meta="{{ number_format($trendTotal) }} encounters"
        empty="No encounters in this period yet." />
</div>

{{-- Recent encounters --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="p-6 border-b border-slate-200">
        <h3 class="text-sm font-semibold text-slate-700">Recent encounters</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Chief complaint</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentEncounters as $e)
                <tr class="hover:bg-slate-50">
                    <td class="table-cell font-medium">{{ $e['patient'] }}</td>
                    <td class="table-cell text-sm text-slate-500">{{ $e['complaint'] }}</td>
                    <td class="table-cell capitalize">{{ str_replace('_', ' ', $e['type']) }}</td>
                    <td class="table-cell">
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $statusStyle[$e['status']] ?? 'bg-slate-100 text-slate-600' }}">
                            {{ ucfirst(str_replace('_', ' ', $e['status'])) }}
                        </span>
                    </td>
                    <td class="table-cell text-xs text-slate-400">{{ $e['date'] }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-sm text-slate-400">No encounters in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
