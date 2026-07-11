@extends('layouts.app')
@section('title', 'Inpatients')
@section('page-title', 'Inpatients / Ward Board')

@section('content')
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.ip.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Ward Board</a>
        <a href="{{ route('web.ip.admissions') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Admissions</a>
        <a href="{{ route('web.ip.adt') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">ADT Tracking</a>
        <a href="{{ route('web.ip.wards') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Wards &amp; Beds</a>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Inpatients</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['inpatients'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Beds</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['total_beds'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Available</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['available'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Occupancy</p>
            <p class="text-3xl font-bold {{ $stats['occupancy_pct'] >= 85 ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $stats['occupancy_pct'] }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Avg. Stay</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['avg_los'] }}<span class="text-base text-slate-400"> d</span></p>
        </div>
    </div>

    {{-- Ward bed board --}}
    @forelse($wards as $ward)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-800">{{ $ward->name }}
                        <span class="text-xs font-normal text-slate-400">{{ $ward->ward_type ? '· ' . $ward->ward_type : '' }}{{ $ward->floor ? ' · Floor ' . $ward->floor : '' }}</span>
                    </h3>
                </div>
                <span class="text-xs text-slate-500">{{ $ward->occupiedCount() }}/{{ $ward->beds->count() }} occupied · {{ $ward->occupancyPct() }}%</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($ward->beds as $bed)
                    @php $adm = $bed->admission; @endphp
                    @if($adm)
                        <a href="{{ route('web.ip.show', $adm->id) }}" class="block rounded-lg border p-3 bg-blue-50 border-blue-200 hover:bg-blue-100 transition">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-800">{{ $bed->bed_number }}</span>
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            </div>
                            <p class="text-xs font-medium text-slate-700 mt-1 truncate">{{ $adm->patient?->name ?? 'Patient' }}</p>
                            <p class="text-[10px] text-slate-400 truncate">{{ $adm->provisional_diagnosis ?? $adm->reason ?? $adm->admission_no }}</p>
                        </a>
                    @else
                        <div class="rounded-lg border p-3 {{ $bed->status === 'maintenance' ? 'bg-slate-100 border-slate-200' : 'bg-green-50 border-green-200' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-bold text-slate-800">{{ $bed->bed_number }}</span>
                                <span class="w-2 h-2 rounded-full {{ $bed->status === 'maintenance' ? 'bg-slate-400' : 'bg-green-500' }}"></span>
                            </div>
                            <p class="text-xs {{ $bed->status === 'maintenance' ? 'text-slate-500' : 'text-green-700' }} mt-1">{{ $bed->statusLabel() }}</p>
                        </div>
                    @endif
                @endforeach
                @if($ward->beds->isEmpty())
                    <p class="col-span-full text-sm text-slate-400">No beds in this ward yet.</p>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-10 text-center">
            <p class="text-slate-500">No wards configured yet.</p>
            <a href="{{ route('web.ip.wards') }}" class="btn-primary inline-block mt-3">Set up Wards &amp; Beds</a>
        </div>
    @endforelse
@endsection
