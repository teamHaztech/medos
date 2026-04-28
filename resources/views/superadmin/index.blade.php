@extends('layouts.app')
@section('title', 'Super Admin — Hospitals')
@section('page-title', 'Manage Hospitals')

@section('content')
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $hospitals->count() }} hospitals registered</p>
    <a href="{{ route('web.superadmin.hospitals.create') }}" class="btn-primary">+ Add Hospital</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach($hospitals as $h)
    @php
        $region = $regions[$h->country] ?? $regions['IN'];
        $staffCount = \DB::table('staff')->where('hospital_id', $h->id)->where('is_active', true)->count();
        $patientCount = \DB::table('patients')->where('hospital_id', $h->id)->count();
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
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs font-medium rounded">{{ $region['name'] }}</span>
                        <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-medium rounded">{{ $region['currency'] }} {{ $region['currency_code'] }}</span>
                        <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 text-xs font-medium rounded">{{ $region['health_id']['system'] }}</span>
                        <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs font-medium rounded">{{ $region['insurance']['system'] }}</span>
                        @if($isCurrentHospital)
                            <span class="px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded">CURRENT</span>
                        @endif
                        @if(!$h->is_active)
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded">INACTIVE</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 border-t border-slate-100">
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $staffCount }}</p>
                <p class="text-xs text-slate-500">Staff</p>
            </div>
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $patientCount }}</p>
                <p class="text-xs text-slate-500">Patients</p>
            </div>
            <div class="p-3 text-center">
                <p class="text-lg font-bold text-slate-800">{{ count($region['languages']) }}</p>
                <p class="text-xs text-slate-500">Languages</p>
            </div>
        </div>

        {{-- Region details --}}
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500 space-y-1">
            <p><strong>Languages:</strong> {{ implode(', ', array_map(fn($l) => $l['native'], $region['languages'])) }}</p>
            <p><strong>Phone:</strong> {{ $region['phone_prefix'] }} ({{ $region['phone_digits'] }} digits) · <strong>Emergency:</strong> {{ $region['emergency_number'] }}</p>
            <p><strong>Payment:</strong> {{ implode(', ', $region['payment_methods']) }} · <strong>Tax:</strong> {{ $region['tax_name'] }} {{ $region['tax_rate'] }}%</p>
        </div>

        {{-- Actions --}}
        <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-2">
            <a href="{{ route('web.superadmin.hospitals.edit', $h->id) }}" class="btn-secondary text-xs px-3 py-1.5">Edit</a>
            @if(!$isCurrentHospital)
            <form method="POST" action="{{ route('switch-hospital') }}" class="inline">
                @csrf
                <input type="hidden" name="hospital_id" value="{{ $h->id }}">
                <button type="submit" class="btn-primary text-xs px-3 py-1.5">Switch to This</button>
            </form>
            @endif
            @if($h->is_active && !$isCurrentHospital)
            <form method="POST" action="{{ route('web.superadmin.hospitals.delete', $h->id) }}" class="inline" onsubmit="return confirm('Deactivate this hospital?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger text-xs px-3 py-1.5">Deactivate</button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endsection
