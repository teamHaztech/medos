@extends('layouts.app')

@section('title', ($patient->name ?? 'Patient') . ' - Patient Detail')
@section('page-title', 'Patient Detail')

@section('content')
<div x-data="{ activeTab: 'timeline' }">

    {{-- Back link --}}
    <a href="{{ route('web.admin.patients') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Patients
    </a>

    {{-- Patient Info Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center text-xl font-bold">
                    {{ strtoupper(substr($patient->name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ $patient->name ?? 'Unknown Patient' }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                        <span>{{ $patient->phone ?? '-' }}</span>
                        @if($patient->email)
                            <span>{{ $patient->email }}</span>
                        @endif
                        @php
                            $age = $patient->date_of_birth
                                ? \Carbon\Carbon::parse($patient->date_of_birth)->age
                                : $patient->age_approximate;
                        @endphp
                        @if($age)
                            <span>{{ $age }} yrs</span>
                        @endif
                        <span class="capitalize">{{ $patient->gender ?? '-' }}</span>
                        <span class="uppercase">{{ $patient->language_preference ?? 'en' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(!empty($patient->insurance_details))
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        Insured
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-slate-100 text-slate-600">
                        Self-pay
                    </span>
                @endif
                @if($patient->abha_number)
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                        🏥 ABHA: {{ substr($patient->abha_number, 0, 2) . '-' . substr($patient->abha_number, 2, 4) . '-' . substr($patient->abha_number, 6, 4) . '-' . substr($patient->abha_number, 10, 4) }}
                        @if($patient->abha_verified) ✓ @endif
                    </span>
                @endif
            </div>
        </div>

        {{-- Allergies alert --}}
        @if(!empty($patient->allergies))
        <div class="mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg">
            <div class="flex items-center gap-2 text-red-800">
                <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <span class="text-sm font-semibold">Allergies:</span>
                <span class="text-sm">{{ is_array($patient->allergies) ? implode(', ', $patient->allergies) : $patient->allergies }}</span>
            </div>
        </div>
        @endif

        {{-- Current Medications --}}
        @if(!empty($patient->current_medications))
        <div class="mt-3 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-center gap-2 text-blue-800">
                <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                <span class="text-sm font-semibold">Medications:</span>
                <span class="text-sm">{{ is_array($patient->current_medications) ? implode(', ', $patient->current_medications) : $patient->current_medications }}</span>
            </div>
        </div>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="border-b border-slate-200 mb-6">
        <nav class="flex gap-6">
            <button @click="activeTab = 'timeline'" :class="activeTab === 'timeline' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="pb-3 border-b-2 text-sm font-medium transition-colors">Timeline</button>
            <button @click="activeTab = 'encounters'" :class="activeTab === 'encounters' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="pb-3 border-b-2 text-sm font-medium transition-colors">Encounters ({{ count($encounters) }})</button>
            <button @click="activeTab = 'bills'" :class="activeTab === 'bills' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'" class="pb-3 border-b-2 text-sm font-medium transition-colors">Bills ({{ count($bills) }})</button>
        </nav>
    </div>

    {{-- Timeline Tab --}}
    <div x-show="activeTab === 'timeline'" x-transition>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="space-y-0">
                @forelse($timeline ?? [] as $event)
                <div class="flex gap-4">
                    <div class="flex flex-col items-center">
                        <div class="w-3 h-3 rounded-full flex-shrink-0 {{ $event['type'] === 'appointment' ? 'bg-blue-500' : ($event['type'] === 'encounter' ? 'bg-purple-500' : 'bg-green-500') }}"></div>
                        @if(!$loop->last)
                        <div class="w-px flex-1 bg-slate-200 min-h-[2rem]"></div>
                        @endif
                    </div>
                    <div class="flex-1 pb-6">
                        <p class="text-sm font-medium text-slate-800">{{ $event['title'] ?? 'Event' }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $event['date'] ?? '' }}</p>
                        @if(!empty($event['description']))
                            <p class="text-sm text-slate-600 mt-1">{{ $event['description'] }}</p>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-slate-400 text-center py-8">No timeline events yet</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Encounters Tab --}}
    <div x-show="activeTab === 'encounters'" x-transition style="display:none;">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Date</th>
                        <th class="table-header">Type</th>
                        <th class="table-header">Doctor</th>
                        <th class="table-header">Complaint</th>
                        <th class="table-header">Triage</th>
                        <th class="table-header">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($encounters ?? [] as $enc)
                    @php
                        $intake = is_array($enc->intake_data) ? $enc->intake_data : [];
                        $encStatus = is_object($enc->status) ? $enc->status->value : ($enc->status ?? 'unknown');
                        $encType = is_object($enc->type) ? $enc->type->value : ($enc->type ?? '-');
                        $triageClass = is_object($enc->triage_classification) ? $enc->triage_classification->value : $enc->triage_classification;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell">{{ $enc->created_at?->format('M d, Y H:i') ?? '-' }}</td>
                        <td class="table-cell capitalize">{{ str_replace('_', ' ', $encType) }}</td>
                        <td class="table-cell">{{ $enc->doctor?->name ?? '-' }}</td>
                        <td class="table-cell">{{ $intake['chief_complaint'] ?? '-' }}</td>
                        <td class="table-cell">
                            @if($triageClass)
                                <x-status-badge :status="$triageClass" type="triage" />
                            @else
                                -
                            @endif
                        </td>
                        <td class="table-cell">
                            <x-status-badge :status="$encStatus" type="encounter" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No encounters found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bills Tab --}}
    <div x-show="activeTab === 'bills'" x-transition style="display:none;">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Bill #</th>
                        <th class="table-header">Date</th>
                        <th class="table-header">Total</th>
                        <th class="table-header">Insurance</th>
                        <th class="table-header">Patient Owes</th>
                        <th class="table-header">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bills ?? [] as $bill)
                    @php
                        $payStatus = is_object($bill->payment_status) ? $bill->payment_status->value : ($bill->payment_status ?? 'pending');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $bill->bill_number ?? '-' }}</td>
                        <td class="table-cell">{{ $bill->created_at?->format('M d, Y') ?? '-' }}</td>
                        <td class="table-cell">₹{{ number_format($bill->total_amount ?? 0, 2) }}</td>
                        <td class="table-cell">₹{{ number_format($bill->insurance_covered ?? 0, 2) }}</td>
                        <td class="table-cell font-medium">₹{{ number_format($bill->patient_payable ?? 0, 2) }}</td>
                        <td class="table-cell">
                            <x-status-badge :status="$payStatus" type="payment" />
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No bills found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
