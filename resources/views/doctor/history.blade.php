@extends('layouts.app')
@section('title', 'Consultation History')
@section('page-title', 'Consultation History')

@section('content')
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Date</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Complaint</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Encounter #</th>
                    <th class="table-header">Billing</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($encounters as $enc)
                @php
                    $intake = is_array($enc->intake_data) ? $enc->intake_data : [];
                    $type = is_object($enc->type) ? $enc->type->value : ($enc->type ?? '');
                @endphp
                <tr class="hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('web.doctor.patients.show', $enc->patient_id) }}'">
                    <td class="table-cell text-slate-500">{{ $enc->created_at?->format('M d, Y h:i A') }}</td>
                    <td class="table-cell font-medium">{{ $enc->patient?->name ?? 'Unknown' }}</td>
                    <td class="table-cell">{{ $intake['chief_complaint'] ?? '-' }}</td>
                    <td class="table-cell capitalize">{{ str_replace('_', ' ', $type) }}</td>
                    <td class="table-cell font-mono text-xs text-slate-500">{{ $enc->encounter_number }}</td>
                    <td class="table-cell" onclick="event.stopPropagation()">
                        <a href="{{ route('web.billing.create', $enc->id) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">Generate Bill</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No completed consultations yet</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($encounters->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">{{ $encounters->withQueryString()->links() }}</div>
        @endif
    </div>
@endsection
