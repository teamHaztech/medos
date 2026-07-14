@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')

@php
    $cur = \App\Modules\Core\Services\RegionService::currency();
    $statusMeta = [
        'scheduled'   => ['label' => 'Scheduled',   'bar' => 'bg-slate-400',  'text' => 'text-slate-600'],
        'confirmed'   => ['label' => 'Confirmed',    'bar' => 'bg-blue-500',   'text' => 'text-blue-600'],
        'checked_in'  => ['label' => 'Checked In',   'bar' => 'bg-green-500',  'text' => 'text-green-600'],
        'in_progress' => ['label' => 'In Progress',  'bar' => 'bg-amber-500',  'text' => 'text-amber-600'],
        'completed'   => ['label' => 'Completed',    'bar' => 'bg-purple-500', 'text' => 'text-purple-600'],
        'cancelled'   => ['label' => 'Cancelled',    'bar' => 'bg-red-500',    'text' => 'text-red-600'],
        'no_show'     => ['label' => 'No-show',      'bar' => 'bg-slate-300',  'text' => 'text-slate-500'],
    ];
    $totalStatus = max(1, $statusBreakdown->sum());
@endphp

@section('content')
    {{-- Greeting header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h2>
            <p class="text-sm text-slate-500">{{ now()->format('l, F j, Y') }} · Here's what's happening today</p>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-slate-600">
                <span class="w-2 h-2 rounded-full bg-green-500"></span>{{ $activeDoctors }} doctors active
            </span>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-slate-600">
                {{ $newPatientsToday }} new patients today
            </span>
        </div>
    </div>

    {{-- KPI Cards with deltas --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $kpis = [
                ['label' => 'Patients Today',  'value' => $patientsToday,                    'delta' => $deltas['patients'], 'accent' => 'blue',   'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z'],
                ['label' => 'Revenue Today',   'value' => $cur . number_format($revenueToday), 'delta' => $deltas['revenue'],  'accent' => 'green',  'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1'],
                ['label' => 'Appointments',    'value' => $totalAppointmentsToday,           'delta' => null,                'accent' => 'purple', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['label' => 'AI Automation',   'value' => $aiRate . '%',                      'delta' => null,                'accent' => 'amber',  'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ];
        @endphp
        @foreach($kpis as $kpi)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-{{ $kpi['accent'] }}-100 text-{{ $kpi['accent'] }}-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $kpi['icon'] }}"/></svg>
                    </div>
                    @if($kpi['delta'] && $kpi['delta']['dir'] !== 'flat')
                        <span class="inline-flex items-center gap-0.5 text-xs font-semibold {{ $kpi['delta']['dir'] === 'up' ? 'text-green-600' : 'text-red-500' }}">
                            {{ $kpi['delta']['dir'] === 'up' ? '▲' : '▼' }} {{ $kpi['delta']['pct'] }}%
                        </span>
                    @elseif($kpi['delta'])
                        <span class="text-xs font-medium text-slate-400"></span>
                    @endif
                </div>
                <p class="text-2xl font-bold text-slate-800 mt-3">{{ $kpi['value'] }}</p>
                <p class="text-xs text-slate-500 mt-0.5">{{ $kpi['label'] }}
                    @if($kpi['delta']) <span class="text-slate-400">vs yesterday</span>@endif
                </p>
            </div>
        @endforeach
    </div>

    {{-- Charts row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- 7-day appointments trend --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-sm font-semibold text-slate-700">7-Day Trend</h3>
                <div class="flex items-center gap-4 text-xs text-slate-400">
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span>Appointments</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-green-400"></span>Revenue</span>
                </div>
            </div>
            <p class="text-xs text-slate-400 mb-5">Each day shows appointments booked (blue) and revenue billed (green). Hover a bar for exact figures.</p>
            <div class="flex items-end justify-between gap-2 sm:gap-3 h-44">
                @foreach($weeklyTrend as $day)
                    <div class="flex-1 flex flex-col items-center justify-end h-full group" title="{{ $day['date'] }} — {{ $day['appointments'] }} appointment{{ $day['appointments'] == 1 ? '' : 's' }}, {{ $cur }}{{ number_format($day['revenue']) }} revenue">
                        {{-- always-visible appointment count --}}
                        <span class="text-[11px] font-bold {{ $day['isToday'] ? 'text-blue-600' : 'text-slate-500' }} mb-1">{{ $day['appointments'] }}</span>
                        <div class="flex items-end justify-center gap-1 w-full" style="height: 100%">
                            <div class="w-1/2 rounded-t bg-blue-500 group-hover:bg-blue-600 transition-all"
                                 style="max-width:18px; height: {{ max(3, round(($day['appointments'] / $maxAppts) * 100)) }}%"></div>
                            <div class="w-1/2 rounded-t bg-green-400 group-hover:bg-green-500 transition-all"
                                 style="max-width:18px; height: {{ max(3, round(($day['revenue'] / $maxRevenue) * 100)) }}%"></div>
                        </div>
                        <p class="text-[11px] mt-2 {{ $day['isToday'] ? 'font-bold text-blue-600' : 'text-slate-400' }}">{{ $day['isToday'] ? 'Today' : $day['label'] }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 text-xs">
                <span class="text-slate-500">Last 7 days: <span class="font-semibold text-slate-700">{{ $weeklyTrend->sum('appointments') }}</span> appointments</span>
                <span class="text-slate-500">Revenue: <span class="font-semibold text-green-600">{{ $cur }}{{ number_format($weeklyTrend->sum('revenue')) }}</span></span>
            </div>
        </div>

        {{-- Today's status breakdown --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Today's Appointments</h3>
            @if($statusBreakdown->sum() > 0)
                {{-- stacked bar --}}
                <div class="flex h-3 rounded-full overflow-hidden mb-4 bg-slate-100">
                    @foreach($statusMeta as $key => $meta)
                        @php $c = $statusBreakdown[$key] ?? 0; @endphp
                        @if($c > 0)
                            <div class="{{ $meta['bar'] }}" style="width: {{ round(($c / $totalStatus) * 100) }}%" title="{{ $meta['label'] }}: {{ $c }}"></div>
                        @endif
                    @endforeach
                </div>
                <div class="space-y-1">
                    @foreach($statusMeta as $key => $meta)
                        @php $c = $statusBreakdown[$key] ?? 0; @endphp
                        @if($c > 0)
                            <a href="{{ route('web.admin.appointments', ['date' => today()->toDateString(), 'status' => $key]) }}"
                               class="flex items-center justify-between text-sm rounded-lg px-2 py-1.5 -mx-2 hover:bg-slate-50 transition-colors group">
                                <span class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-sm {{ $meta['bar'] }}"></span>
                                    <span class="text-slate-600 group-hover:text-slate-900">{{ $meta['label'] }}</span>
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <span class="font-semibold {{ $meta['text'] }}">{{ $c }}</span>
                                    <span class="text-slate-300 group-hover:text-slate-400">›</span>
                                </span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400 text-center py-8">No appointments today yet</p>
            @endif
        </div>
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent Activity Feed --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Recent Activity</h3>
            <div class="space-y-1 max-h-80 overflow-y-auto">
                @forelse($recentActivity as $event)
                    <div class="flex items-start gap-3 py-2.5 border-b border-slate-50 last:border-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                            @if($event['type'] === 'check_in') bg-green-100 text-green-600
                            @elseif($event['type'] === 'completed') bg-purple-100 text-purple-600
                            @elseif($event['type'] === 'cancelled') bg-red-100 text-red-600
                            @else bg-blue-100 text-blue-600
                            @endif
                        ">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700">{{ $event['message'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $event['time'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-8">No activity today yet</p>
                @endforelse
            </div>
        </div>

        {{-- Active Queues --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Active Queues</h3>
            <div class="space-y-3">
                @forelse($queues as $queue)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $queue['doctor'] }}</p>
                            <p class="text-xs text-slate-500">{{ $queue['department'] }}</p>
                        </div>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                            @if($queue['depth'] > 5) bg-red-100 text-red-700
                            @elseif($queue['depth'] > 2) bg-yellow-100 text-yellow-700
                            @else bg-green-100 text-green-700
                            @endif
                        ">{{ $queue['depth'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">No active queues</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Department load + Quick Stats --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        {{-- Department load --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Department Load (Today)</h3>
            @if($departmentLoad->count() > 0)
                <div class="space-y-3">
                    @foreach($departmentLoad as $dept => $count)
                        <div>
                            <div class="flex items-center justify-between text-sm mb-1">
                                <span class="text-slate-600">{{ $dept }}</span>
                                <span class="font-semibold text-slate-700">{{ $count }}</span>
                            </div>
                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-500 rounded-full" style="width: {{ round(($count / $maxDeptLoad) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400 text-center py-6">No appointments today</p>
            @endif
        </div>

        {{-- Quick Stats --}}
        <div class="lg:col-span-2">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 h-full">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center flex flex-col justify-center">
                    <p class="text-2xl font-bold text-blue-600">{{ $quickStats['pendingAppointments'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">Pending Appointments</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center flex flex-col justify-center">
                    <p class="text-2xl font-bold text-yellow-600">{{ $quickStats['insurancePending'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">Insurance Pending</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center flex flex-col justify-center">
                    <p class="text-2xl font-bold text-red-600">{{ $quickStats['billsUnpaid'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">Bills Unpaid</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center flex flex-col justify-center">
                    <p class="text-2xl font-bold text-green-600">{{ $quickStats['consultationsDone'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">Consultations Done</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center flex flex-col justify-center">
                    <p class="text-2xl font-bold text-slate-500">{{ $quickStats['noShows'] }}</p>
                    <p class="text-xs text-slate-500 mt-1">No-shows</p>
                </div>
            </div>
        </div>
    </div>
@endsection
