@extends('layouts.app')
@section('title', 'ADT Tracking')
@section('page-title', 'Admission / Discharge Tracking')

@php
    use App\Modules\Inpatient\Models\Admission;
    $cols = [
        'in_care'   => ['In Care', 'text-slate-600', 'border-slate-200'],
        'initiated' => ['Discharge In Progress', 'text-amber-700', 'border-amber-200'],
        'ready'     => ['Ready to Leave', 'text-green-700', 'border-green-200'],
    ];
@endphp

@section('content')
<div>
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.ip.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Ward Board</a>
        <a href="{{ route('web.ip.admissions') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Admissions</a>
        <a href="{{ route('web.ip.adt') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">ADT Tracking</a>
        <a href="{{ route('web.ip.wards') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Wards &amp; Beds</a>
        <button type="button" onclick="location.reload()" class="ml-auto btn-secondary">Refresh</button>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        @foreach($cols as $key => [$label, $txt, $border])
            <div>
                <div class="flex items-center justify-between mb-2 px-1">
                    <h3 class="text-sm font-bold {{ $txt }}">{{ $label }}</h3>
                    <span class="text-xs font-semibold text-slate-400">{{ $groups[$key]->count() }}</span>
                </div>
                <div class="space-y-3">
                    @forelse($groups[$key] as $a)
                        <div class="bg-white rounded-xl shadow-sm border {{ $border }} p-4"
                             x-data="{
                                id: '{{ $a->id }}',
                                c: { billing: {{ $a->isCleared('billing') ? 'true' : 'false' }}, pharmacy: {{ $a->isCleared('pharmacy') ? 'true' : 'false' }}, nursing: {{ $a->isCleared('nursing') ? 'true' : 'false' }} },
                                summary: {{ $a->summaryReady() ? 'true' : 'false' }},
                                busy: false,
                                get done() { return Object.values(this.c).filter(Boolean).length; },
                                get ready() { return this.done === 3; },
                                async toggle(type) {
                                    if (this.busy) return; this.busy = true;
                                    try {
                                        const r = await fetch('/ip/admissions/' + this.id + '/clearance', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content },
                                            body: JSON.stringify({ type }),
                                        });
                                        const d = await r.json();
                                        if (d.success) this.c[type] = d.cleared;
                                    } catch (e) {}
                                    this.busy = false;
                                }
                             }">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-800 truncate">{{ $a->patient?->name ?? 'Unknown' }}</p>
                                    <p class="text-xs text-slate-400">{{ $a->admission_no }} · {{ $a->ward?->name ?? '—' }}{{ $a->bed?->bed_number ? ' / '.$a->bed->bed_number : '' }}</p>
                                </div>
                                <span class="text-xs text-slate-400 whitespace-nowrap">{{ $a->lengthOfDays() }}d</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">{{ $a->attendingDoctor?->name ?? $a->admittingDoctor?->name ?? '' }}</p>

                            @if($key === 'in_care')
                                <form method="POST" action="{{ route('web.ip.discharge.initiate', $a->id) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="w-full text-sm font-semibold px-3 py-1.5 rounded-lg bg-amber-100 text-amber-700 hover:bg-amber-200">Initiate Discharge</button>
                                </form>
                            @else
                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach(Admission::CLEARANCES as $ck => $clabel)
                                        <button type="button" @click="toggle('{{ $ck }}')" :disabled="busy"
                                            :class="c.{{ $ck }} ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
                                            class="text-xs px-2 py-1 rounded-full font-medium">
                                            <span x-text="(c.{{ $ck }} ? '✓ ' : '') + '{{ $clabel }}'"></span>
                                        </button>
                                    @endforeach
                                    <span x-show="ready" class="text-xs px-2 py-1 rounded-full font-medium bg-green-100 text-green-700">✓ Cleared</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <a href="{{ route('web.ip.show', $a->id) }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">Case sheet →</a>
                                    <a href="{{ route('web.ip.show', $a->id) }}" class="text-xs font-semibold px-3 py-1.5 rounded-lg" :class="ready ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-slate-100 text-slate-400'">Complete discharge</a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="bg-white rounded-xl border border-dashed border-slate-200 p-6 text-center text-xs text-slate-400">None</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Discharged today --}}
    <div class="mt-8">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-3">Discharged Today <span class="text-slate-300">({{ $dischargedToday->count() }})</span></h3>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="divide-y divide-slate-100">
                @forelse($dischargedToday as $a)
                    <div class="flex items-center gap-3 px-4 py-2.5">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-800">{{ $a->patient?->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-slate-400">{{ $a->admission_no }} · {{ $a->ward?->name ?? '—' }} · {{ $a->lengthOfDays() }} day(s)</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ optional($a->discharged_at)->format('g:i A') }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-600">{{ $a->dischargeTypeLabel() ?? 'Discharged' }}</span>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400 text-sm">No discharges today.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
