@extends('layouts.app')
@section('title', 'Admissions')
@section('page-title', 'Inpatients / Admissions')

@php
    $wardData = $wards->map(fn ($w) => [
        'id' => $w->id, 'name' => $w->name,
        'beds' => $w->beds->map(fn ($b) => ['id' => $b->id, 'number' => $b->bed_number])->values(),
    ])->values();
@endphp

@section('content')
<div x-data="{
        admitOpen: false,
        wards: {{ Illuminate\Support\Js::from($wardData) }},
        wardId: '',
        get beds() { return (this.wards.find(w => w.id === this.wardId) || {}).beds || []; }
    }">

    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.ip.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Ward Board</a>
        <a href="{{ route('web.ip.admissions') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Admissions</a>
        <a href="{{ route('web.ip.adt') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">ADT Tracking</a>
        <a href="{{ route('web.ip.wards') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Wards &amp; Beds</a>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('web.ip.admissions', ['discharged' => $showDischarged ? 0 : 1]) }}" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">{{ $showDischarged ? 'Show current' : 'Show discharged' }}</a>
            @unless($showDischarged)<button @click="admitOpen = true" class="btn-primary">+ Admit Patient</button>@endunless
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Admission #</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Ward / Bed</th>
                    <th class="table-header">Doctor</th>
                    <th class="table-header">{{ $showDischarged ? 'Discharged' : 'Admitted' }}</th>
                    <th class="table-header">LOS</th>
                    <th class="table-header w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($admissions as $a)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $a->admission_no }}</td>
                        <td class="table-cell">
                            <span class="font-medium text-slate-800">{{ $a->patient?->name ?? '-' }}</span>
                            <span class="block text-xs text-slate-400">{{ $a->provisional_diagnosis ?? $a->reason }}</span>
                        </td>
                        <td class="table-cell">{{ $a->ward?->name ?? '-' }}{{ $a->bed?->bed_number ? ' · ' . $a->bed->bed_number : '' }}</td>
                        <td class="table-cell text-slate-600">{{ $a->attendingDoctor?->name ?? '-' }}</td>
                        <td class="table-cell text-slate-500 text-sm">{{ optional($showDischarged ? $a->discharged_at : $a->admitted_at)->format('M d, Y g:i A') }}</td>
                        <td class="table-cell">{{ $a->lengthOfDays() }}d @if($showDischarged && $a->dischargeTypeLabel())<span class="text-xs text-slate-400">· {{ $a->dischargeTypeLabel() }}</span>@endif</td>
                        <td class="table-cell">
                            <a href="{{ route('web.ip.show', $a->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-10">No {{ $showDischarged ? 'discharged' : 'current' }} inpatients.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Admit modal --}}
    <x-modal show="admitOpen" title="Admit Patient" max="lg">
            <form method="POST" action="{{ route('web.ip.admit') }}" class="grid grid-cols-2 gap-4">
                @csrf
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Patient *</label>
                    <select name="patient_id" required class="input-field">
                        <option value="">Select patient…</option>
                        @foreach($patients as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->phone ? ' · ' . $p->phone : '' }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Ward *</label>
                    <select name="ward_id" x-model="wardId" required class="input-field">
                        <option value="">Select ward…</option>
                        <template x-for="w in wards" :key="w.id"><option :value="w.id" x-text="w.name"></option></template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Bed *</label>
                    <select name="bed_id" required class="input-field" :disabled="!wardId">
                        <option value="">Select bed…</option>
                        <template x-for="b in beds" :key="b.id"><option :value="b.id" x-text="b.number"></option></template>
                    </select>
                    <p x-show="wardId && beds.length === 0" class="text-xs text-red-500 mt-1">No free beds in this ward.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Admitting doctor</label>
                    <select name="admitting_doctor_id" class="input-field">
                        <option value="">—</option>
                        @foreach($doctors as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Attending doctor</label>
                    <select name="attending_doctor_id" class="input-field">
                        <option value="">—</option>
                        @foreach($doctors as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reason for admission</label>
                    <input type="text" name="reason" class="input-field" placeholder="e.g. Observation, surgery, delivery">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Provisional diagnosis</label>
                    <input type="text" name="provisional_diagnosis" class="input-field">
                </div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5">Admit</button>
                    <button type="button" @click="admitOpen = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
