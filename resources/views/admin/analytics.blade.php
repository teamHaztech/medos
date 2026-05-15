@extends('layouts.app')
@section('title', 'Analytics')
@section('page-title', 'Analytics')

@section('content')
    {{-- Period Selector --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-lg font-semibold text-slate-800">{{ $periodLabel }}</h2>
        <div class="flex gap-2">
            @foreach(['this_month' => 'This Month', 'last_month' => 'Last Month', 'last_3_months' => 'Last 3 Months', 'this_year' => 'This Year'] as $key => $label)
                <a href="{{ route('web.admin.analytics', ['period' => $key]) }}"
                   class="px-3 py-1.5 text-sm rounded-lg border transition {{ $period === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    @php
        $currency = \App\Modules\Core\Services\RegionService::currency();

        $patientChange = $patientsPrevPeriod > 0 ? round((($patientsThisPeriod - $patientsPrevPeriod) / $patientsPrevPeriod) * 100) : ($patientsThisPeriod > 0 ? 100 : 0);
        $revenueChange = $revenuePrevPeriod > 0 ? round((($revenueThisPeriod - $revenuePrevPeriod) / $revenuePrevPeriod) * 100) : ($revenueThisPeriod > 0 ? 100 : 0);
        $appointmentChange = $appointmentsPrevPeriod > 0 ? round((($appointmentsThisPeriod - $appointmentsPrevPeriod) / $appointmentsPrevPeriod) * 100) : ($appointmentsThisPeriod > 0 ? 100 : 0);
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Patients</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($patientsThisPeriod) }}</p>
                    <p class="mt-1 text-xs {{ $patientChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $patientChange >= 0 ? '+' : '' }}{{ $patientChange }}% vs prev period
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-blue-50 text-blue-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Revenue</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $currency }}{{ number_format($revenueThisPeriod) }}</p>
                    <p class="mt-1 text-xs {{ $revenueChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $revenueChange >= 0 ? '+' : '' }}{{ $revenueChange }}% vs prev period
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-green-50 text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Appointments</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ number_format($appointmentsThisPeriod) }}</p>
                    <p class="mt-1 text-xs {{ $appointmentChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $appointmentChange >= 0 ? '+' : '' }}{{ $appointmentChange }}% vs prev period
                    </p>
                </div>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-purple-50 text-purple-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Avg Wait Time</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $avgWaitTime }} min</p>
                    <p class="mt-1 text-xs text-slate-400">Based on queue data</p>
                </div>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-yellow-50 text-yellow-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Grid: Top Doctors + Department Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {{-- Top 5 Doctors --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Top Doctors by Patient Count</h3>
            @if($topDoctors->count())
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-200">
                        <th class="text-left text-xs font-medium text-slate-500 pb-2">Doctor</th>
                        <th class="text-left text-xs font-medium text-slate-500 pb-2">Department</th>
                        <th class="text-right text-xs font-medium text-slate-500 pb-2">Patients</th>
                        <th class="text-right text-xs font-medium text-slate-500 pb-2">Appointments</th>
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
            <p class="text-sm text-slate-400 text-center py-8">No data for this period</p>
            @endif
        </div>

        {{-- Department Breakdown --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Department-wise Patient Distribution</h3>
            @if($departmentStats->count())
            @php $maxPatients = $departmentStats->max('patient_count') ?: 1; @endphp
            <div class="space-y-3">
                @foreach($departmentStats as $dept)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-slate-700">{{ $dept->department }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ $dept->patient_count }}</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ round(($dept->patient_count / $maxPatients) * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400 text-center py-8">No data for this period</p>
            @endif
        </div>
    </div>

    {{-- Recent Encounters --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-700">Recent Encounters</h3>
        </div>
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Encounter #</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Doctor</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Chief Complaint</th>
                    <th class="table-header">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentEncounters as $enc)
                @php
                    $encType = is_object($enc->type) ? $enc->type->value : ($enc->type ?? 'visit');
                    $encStatus = is_object($enc->status) ? $enc->status->value : ($enc->status ?? 'unknown');
                    $intake = is_array($enc->intake_data) ? $enc->intake_data : [];
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
                            @else bg-slate-100 text-slate-600
                            @endif
                        ">{{ ucfirst(str_replace('_', ' ', $encStatus)) }}</span>
                    </td>
                    <td class="table-cell text-sm text-slate-500">{{ $intake['chief_complaint'] ?? '-' }}</td>
                    <td class="table-cell text-xs text-slate-400">{{ $enc->created_at?->format('M d, Y h:i A') ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-8 text-sm text-slate-400">No encounters found for this period</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
