@extends('layouts.app')
@section('title', 'Analytics')
@section('page-title', 'Analytics')

@section('content')
@php
    $c = \App\Modules\Core\Services\RegionService::currency();
    $money = fn ($v) => $c . number_format((float) $v, ($v >= 1000 ? 0 : 2));

    $trendTotal = collect($trend)->sum('value');
    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.$money($b['value'])])->all();

    $maxDept = max($departmentStats->max('patient_count') ?: 0, 1);
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">Hospital Analytics</h2>
        <p class="text-sm text-slate-500">Revenue, patients & activity — {{ $periodLabel }}</p>
    </div>
    <x-insights.period-tabs route="web.admin.analytics" :period="$period" />
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Revenue" :value="$money($kpis['revenue'])" :delta="$kpis['revenue_change']" accent="green" icon="currency" />
    <x-insights.kpi label="New patients" :value="number_format($kpis['patients'])" :delta="$kpis['patients_change']" accent="blue" icon="users" />
    <x-insights.kpi label="Appointments" :value="number_format($kpis['appointments'])" :delta="$kpis['appts_change']" accent="purple" icon="calendar" />
    <x-insights.kpi label="Avg wait time" :value="$kpis['avg_wait'].' min'" :sub="'from queue data'" accent="amber" icon="clock" />
</div>

{{-- Revenue trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="green"
        title="Revenue trend · {{ $periodLabel }}"
        meta="Total {{ $money($trendTotal) }}"
        empty="No revenue recorded in this period yet." />
</div>

{{-- Top doctors + department distribution --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Top doctors by patients</h3>
        @if($topDoctors->count())
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200">
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Doctor</th>
                    <th class="text-left text-xs font-medium text-slate-500 pb-2">Department</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Patients</th>
                    <th class="text-right text-xs font-medium text-slate-500 pb-2">Appts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($topDoctors as $doc)
                <tr>
                    <td class="py-2.5 text-sm font-medium text-slate-800">{{ $doc->name }}</td>
                    <td class="py-2.5 text-sm text-slate-500">{{ $doc->department ?? 'General' }}</td>
                    <td class="py-2.5 text-sm text-right font-semibold text-blue-600">{{ $doc->patient_count }}</td>
                    <td class="py-2.5 text-sm text-right text-slate-600">{{ $doc->appointment_count }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
        @endif
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Patients by department</h3>
        @if($departmentStats->count())
        <div class="space-y-3">
            @foreach($departmentStats as $dept)
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-medium text-slate-700">{{ $dept->department }}</span>
                    <span class="text-sm font-semibold text-slate-900">{{ $dept->patient_count }}</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2">
                    <div class="bg-purple-500 h-2 rounded-full" style="width: {{ round(($dept->patient_count / $maxDept) * 100) }}%;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
        @endif
    </div>
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
                    <th class="table-header">Encounter #</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Doctor</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentEncounters as $enc)
                @php
                    $encType = is_object($enc->type) ? $enc->type->value : ($enc->type ?? 'visit');
                    $encStatus = is_object($enc->status) ? $enc->status->value : ($enc->status ?? 'unknown');
                @endphp
                <tr class="hover:bg-slate-50">
                    <td class="table-cell font-mono text-xs">{{ $enc->encounter_number }}</td>
                    <td class="table-cell font-medium">{{ $enc->patient?->name ?? '-' }}</td>
                    <td class="table-cell">{{ $enc->doctor?->name ?? '-' }}</td>
                    <td class="table-cell capitalize">{{ str_replace('_', ' ', $encType) }}</td>
                    <td class="table-cell">
                        <span class="px-2 py-0.5 rounded text-xs font-medium
                            @if($encStatus === 'completed') bg-green-100 text-green-700
                            @elseif($encStatus === 'in_progress') bg-blue-100 text-blue-700
                            @elseif($encStatus === 'cancelled') bg-red-100 text-red-700
                            @else bg-slate-100 text-slate-600 @endif">
                            {{ ucfirst(str_replace('_', ' ', $encStatus)) }}
                        </span>
                    </td>
                    <td class="table-cell text-xs text-slate-400">{{ $enc->created_at?->format('M d, Y h:i A') ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-sm text-slate-400">No encounters found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
