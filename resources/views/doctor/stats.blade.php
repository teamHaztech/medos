@extends('layouts.app')
@section('title', 'My Stats')
@section('page-title', 'My Dashboard')

@section('content')
    {{-- Period selector --}}
    <div class="flex items-center gap-1 bg-white rounded-xl shadow-sm border border-slate-200 p-1 mb-5 w-fit">
        @foreach(['today' => 'Today', 'week' => '7 Days', 'month' => '30 Days', 'all' => 'All Time'] as $key => $lbl)
            <a href="{{ route('web.doctor.stats', ['period' => $key]) }}"
               class="px-4 py-2 rounded-lg text-sm font-semibold transition-all {{ $period === $key ? 'bg-blue-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                {{ $lbl }}
            </a>
        @endforeach
    </div>

    {{-- KPI row 1: Primary stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div class="kpi-card">
            <p class="text-3xl font-bold text-blue-600">{{ $patients }}</p>
            <p class="text-xs text-slate-500 mt-1">Patients</p>
        </div>
        <div class="kpi-card">
            <p class="text-3xl font-bold text-green-600">{{ $completed }}</p>
            <p class="text-xs text-slate-500 mt-1">Completed</p>
        </div>
        <div class="kpi-card">
            <p class="text-3xl font-bold text-amber-600">{{ $pending }}</p>
            <p class="text-xs text-slate-500 mt-1">Pending</p>
        </div>
        <div class="kpi-card">
            <p class="text-3xl font-bold text-red-500">{{ $noShows }}</p>
            <p class="text-xs text-slate-500 mt-1">No-shows</p>
        </div>
    </div>

    {{-- KPI row 2: Revenue, avg time, encounters --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="kpi-card">
            <p class="text-2xl font-bold text-green-600">₹{{ number_format($revenue) }}</p>
            <p class="text-xs text-slate-500 mt-1">Revenue</p>
        </div>
        <div class="kpi-card">
            <p class="text-2xl font-bold text-purple-600">{{ $totalEncounters }}</p>
            <p class="text-xs text-slate-500 mt-1">Consultations</p>
        </div>
        <div class="kpi-card">
            <p class="text-2xl font-bold text-cyan-600">{{ $avgDuration }} <span class="text-sm font-normal">min</span></p>
            <p class="text-xs text-slate-500 mt-1">Avg Duration</p>
        </div>
        <div class="kpi-card">
            <p class="text-2xl font-bold text-slate-700">{{ $totalApts > 0 ? round($completed / $totalApts * 100) : 0 }}<span class="text-sm font-normal">%</span></p>
            <p class="text-xs text-slate-500 mt-1">Completion Rate</p>
        </div>
    </div>

    {{-- Consultations table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-700">Consultations — {{ $label }}</h3>
            <span class="text-xs text-slate-400">{{ $recentEncounters->count() }} records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Date</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Complaint</th>
                        <th class="table-header">Type</th>
                        <th class="table-header">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentEncounters as $enc)
                    <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('web.doctor.patients.show', $enc['patient_id']) }}'">
                        <td class="table-cell text-slate-500 whitespace-nowrap">{{ $enc['date'] }}</td>
                        <td class="table-cell font-medium">{{ $enc['patient'] }}</td>
                        <td class="table-cell">{{ $enc['complaint'] }}</td>
                        <td class="table-cell capitalize text-slate-500">{{ str_replace('_', ' ', $enc['type']) }}</td>
                        <td class="table-cell"><x-status-badge :status="$enc['status']" type="encounter" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No consultations for this period</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
