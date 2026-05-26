@extends('layouts.app')
@section('title', 'Super Admin — Hospitals')
@section('page-title', 'Multi-Hospital Dashboard')

@section('content')
{{-- Top KPI row --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Hospitals</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ $hospitals->count() }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ $hospitals->where('is_active', true)->count() }} active</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Staff</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalStaff) }}</p>
        <p class="text-xs text-slate-400 mt-1">Active staff across all hospitals</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Patients</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalPatients) }}</p>
        <p class="text-xs text-slate-400 mt-1">All hospitals combined</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Appointments Today</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalAppointmentsToday) }}</p>
        <p class="text-xs text-slate-400 mt-1">Revenue: {{ \App\Modules\Core\Services\RegionService::currency() }} {{ number_format($totalRevenueToday, 2) }}</p>
    </div>
</div>

{{-- Action bar --}}
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $hospitals->count() }} hospitals registered</p>
    <a href="{{ route('web.superadmin.hospitals.create') }}" class="btn-primary">+ Add Hospital</a>
</div>

{{-- Hospital cards grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach($hospitals as $h)
    @php
        $region = $regions[$h->country] ?? $regions['IN'];
        $hStaff    = $staffCounts[$h->id] ?? 0;
        $hPatients = $patientCounts[$h->id] ?? 0;
        $hApts     = $aptCounts[$h->id] ?? 0;
        $hRev      = $revCounts[$h->id] ?? 0;
        $isCurrentHospital = auth()->user()?->hospital_id === $h->id;
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden {{ !$h->is_active ? 'opacity-50' : '' }} {{ $isCurrentHospital ? 'ring-2 ring-blue-400' : '' }}">
        {{-- Header --}}
        <div class="p-5 flex items-start justify-between">
            <div class="flex items-start gap-3">
                <span class="text-3xl">{{ $h->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $h->name }}</h3>
                    <p class="text-sm text-slate-500">{{ $h->city }}{{ $h->state ? ', ' . $h->state : '' }}</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if($h->is_active)
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded">ACTIVE</span>
                        @else
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded">INACTIVE</span>
                        @endif
                        @if($isCurrentHospital)
                            <span class="px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded">CURRENT</span>
                        @endif
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-medium rounded">{{ $region['currency'] }} {{ $region['currency_code'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-4 border-t border-slate-100">
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hStaff }}</p>
                <p class="text-xs text-slate-500">Staff</p>
            </div>
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hPatients }}</p>
                <p class="text-xs text-slate-500">Patients</p>
            </div>
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hApts }}</p>
                <p class="text-xs text-slate-500">Appts Today</p>
            </div>
            <div class="p-3 text-center">
                <p class="text-lg font-bold text-slate-800">{{ $region['currency'] }}{{ number_format($hRev, 0) }}</p>
                <p class="text-xs text-slate-500">Revenue Today</p>
            </div>
        </div>

        {{-- Contact info --}}
        @if($h->phone || $h->email)
        <div class="px-5 py-2 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
            @if($h->phone)<span>{{ $h->phone }}</span>@endif
            @if($h->phone && $h->email)<span class="mx-1">&middot;</span>@endif
            @if($h->email)<span>{{ $h->email }}</span>@endif
        </div>
        @endif

        {{-- Actions --}}
        <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-2">
            <a href="{{ route('web.superadmin.hospitals.show', $h->id) }}" class="btn-primary text-xs px-3 py-1.5">Manage</a>
            <a href="{{ route('web.superadmin.hospitals.edit', $h->id) }}" class="btn-secondary text-xs px-3 py-1.5">Edit</a>
            @if(!$isCurrentHospital)
            <form method="POST" action="{{ route('switch-hospital') }}" class="inline">
                @csrf
                <input type="hidden" name="hospital_id" value="{{ $h->id }}">
                <button type="submit" class="btn-secondary text-xs px-3 py-1.5">Switch to This</button>
            </form>
            @endif
            @if($h->is_active && !$isCurrentHospital)
            <form method="POST" action="{{ route('web.superadmin.hospitals.delete', $h->id) }}" class="inline" onsubmit="return confirm('Deactivate this hospital?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs px-3 py-1.5 text-red-600 hover:text-red-800 font-medium">Deactivate</button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
