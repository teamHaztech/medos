@extends('layouts.app')
@section('title', 'OPD Insights')
@section('page-title', 'OPD Insights')

@section('content')
@php
    $trendTotal = collect($trend)->sum('value');
    $tseries = collect($trend)->map(fn ($b) => ['label'=>$b['label'], 'value'=>$b['value'], 'tip'=>$b['label'].' — '.number_format($b['value']).' appts'])->all();

    $maxDoc  = max($topDoctors->max('cnt') ?: 0, 1);
    $deptTot = max($byDept->sum('cnt') ?: 0, 1);
    $srcTot  = max($bySource->sum('cnt') ?: 0, 1);

    $srcLabels = ['walk_in'=>'Walk-in','online'=>'Online / Web','whatsapp'=>'WhatsApp','phone'=>'Phone','kiosk'=>'Kiosk','voice_ai'=>'Voice AI','referral'=>'Referral'];
    $srcColors = ['bg-blue-500','bg-green-500','bg-purple-500','bg-cyan-500','bg-amber-500','bg-pink-500','bg-indigo-500','bg-rose-500'];

    $statusStyle = ['completed'=>'bg-green-100 text-green-700','no_show'=>'bg-red-100 text-red-700','cancelled'=>'bg-slate-200 text-slate-600','in_progress'=>'bg-blue-100 text-blue-700','checked_in'=>'bg-cyan-100 text-cyan-700','scheduled'=>'bg-slate-100 text-slate-600','confirmed'=>'bg-indigo-100 text-indigo-700','rescheduled'=>'bg-amber-100 text-amber-700'];
@endphp

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-800">OPD Insights</h2>
        <p class="text-sm text-slate-500">Appointment volume, attendance & load — {{ $periodLabel }}</p>
    </div>
    <x-insights.period-tabs route="web.admin.opd-insights" :period="$period" />
</div>

{{-- KPI row --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-insights.kpi label="Appointments" :value="number_format($kpis['total'])" :delta="$kpis['total_change']" accent="blue" icon="calendar" />
    <x-insights.kpi label="Completed" :value="number_format($kpis['completed'])" :delta="$kpis['completed_change']" accent="green" icon="check" />
    <x-insights.kpi label="No-show rate" :value="$kpis['no_show_rate'].'%'" :sub="'of booked appointments'" accent="red" icon="ban" />
    <x-insights.kpi label="Unique patients" :value="number_format($kpis['unique_patients'])" :sub="$kpis['completion_rate'].'% completion rate'" accent="purple" icon="users" />
</div>

{{-- Appointment trend --}}
<div class="mb-6">
    <x-insights.trend :series="$tseries" accent="blue"
        title="Appointment volume · {{ $periodLabel }}"
        meta="{{ number_format($trendTotal) }} appointments"
        empty="No appointments in this period yet." />
</div>

{{-- Top doctors + department load --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Busiest doctors</h3>
        @if($topDoctors->count())
        <div class="space-y-3">
            @foreach($topDoctors as $doc)
            <div>
                <div class="flex items-center justify-between mb-1 gap-2">
                    <span class="text-sm font-medium text-slate-700 truncate">{{ $doc->name }}
                        <span class="text-xs text-slate-400 font-normal">{{ $doc->department ?? 'General' }}</span></span>
                    <span class="text-sm font-semibold text-slate-900 whitespace-nowrap">{{ number_format($doc->cnt) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex-1 bg-slate-100 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ round(($doc->cnt / $maxDoc) * 100) }}%;"></div>
                    </div>
                    <span class="text-xs text-slate-400 whitespace-nowrap">{{ number_format($doc->patients) }} patients</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No appointments in this period.</p>
        @endif
    </div>

    <div class="space-y-6">
        {{-- Department split --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Load by department</h3>
            @if($byDept->count())
            <div class="space-y-3">
                @foreach($byDept as $d)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-slate-700">{{ $d->department }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ number_format($d->cnt) }}</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-indigo-500 h-2 rounded-full" style="width: {{ round(($d->cnt / $deptTot) * 100) }}%;"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
            @endif
        </div>

        {{-- Booking source --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Booking source</h3>
            @if($bySource->count())
            <div class="space-y-3">
                @foreach($bySource as $i => $s)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-slate-700">{{ $srcLabels[$s->source] ?? ucfirst(str_replace('_',' ',$s->source)) }}</span>
                        <span class="text-sm font-semibold text-slate-900">{{ number_format($s->cnt) }}
                            <span class="text-xs text-slate-400 font-normal">· {{ round(($s->cnt / $srcTot) * 100) }}%</span></span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="{{ $srcColors[$i % count($srcColors)] }} h-2 rounded-full" style="width: {{ round(($s->cnt / $srcTot) * 100) }}%;"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-slate-400 text-center py-8">No data for this period.</p>
            @endif
        </div>
    </div>
</div>

{{-- Busiest weekday + status breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <x-insights.trend :series="$busiestDays" accent="cyan"
        title="Busiest weekdays"
        empty="No appointments in this period." />

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Appointment outcomes</h3>
        @if($byStatus->count())
        <div class="flex flex-wrap gap-2">
            @foreach($byStatus->sortDesc() as $st => $ct)
            <span class="px-3 py-1.5 rounded-lg text-sm font-medium {{ $statusStyle[$st] ?? 'bg-slate-100 text-slate-600' }}">
                {{ ucfirst(str_replace('_',' ',$st)) }} · {{ number_format($ct) }}
            </span>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-3 text-center">
            <div>
                <p class="text-xl font-bold text-green-600">{{ $kpis['completion_rate'] }}%</p>
                <p class="text-xs text-slate-500">Completion</p>
            </div>
            <div>
                <p class="text-xl font-bold text-red-600">{{ $kpis['no_show_rate'] }}%</p>
                <p class="text-xs text-slate-500">No-show</p>
            </div>
            <div>
                <p class="text-xl font-bold text-amber-600">{{ $kpis['cancel_rate'] }}%</p>
                <p class="text-xs text-slate-500">Cancelled</p>
            </div>
        </div>
        @else
        <p class="text-sm text-slate-400 text-center py-8">No appointments in this period.</p>
        @endif
    </div>
</div>
@endsection
