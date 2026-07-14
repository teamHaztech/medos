@extends('layouts.app')
@section('title', $patient->name . ' - Patient')
@section('page-title', 'Patient Detail')

@section('content')
    <a href="{{ route('web.doctor.patients') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to My Patients
    </a>

    {{-- Patient card --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center text-xl font-bold">{{ strtoupper(substr($patient->name, 0, 1)) }}</div>
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $patient->name }}</h2>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                    <span>{{ $patient->phone }}</span>
                    @php $age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : $patient->age_approximate; @endphp
                    @if($age)<span>{{ $age }} yrs</span>@endif
                    <span class="capitalize">{{ $patient->gender ?? '' }}</span>
                </div>
            </div>
        </div>

        @if(!empty($patient->allergies))
        <div class="mt-3 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
            <strong>Allergies:</strong> {{ is_array($patient->allergies) ? implode(', ', $patient->allergies) : '' }}
        </div>
        @endif

        @if(!empty($patient->current_medications))
        <div class="mt-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <strong>Current Meds:</strong> {{ is_array($patient->current_medications) ? implode(', ', $patient->current_medications) : '' }}
        </div>
        @endif
    </div>

    {{-- My consultation history with this patient --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-700">My Consultations with {{ $patient->name }}</h3>
        </div>
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Date</th>
                    <th class="table-header">Encounter #</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Complaint</th>
                    <th class="table-header">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($encounters as $enc)
                <tr class="hover:bg-slate-50">
                    <td class="table-cell text-slate-500">{{ $enc['date'] }}</td>
                    <td class="table-cell font-mono text-xs">{{ $enc['encounter_number'] }}</td>
                    <td class="table-cell capitalize">{{ str_replace('_', ' ', $enc['type']) }}</td>
                    <td class="table-cell">{{ $enc['complaint'] }}</td>
                    <td class="table-cell"><x-status-badge :status="$enc['status']" type="encounter" /></td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No consultation history</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
