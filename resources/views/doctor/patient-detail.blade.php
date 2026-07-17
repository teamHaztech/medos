@extends('layouts.app')
@section('title', $patient->name . ' - Patient')
@section('page-title', 'Patient History')

@section('content')
    <a href="{{ route('web.doctor.patients') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to My Patients
    </a>

    {{-- Patient summary --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center text-xl font-bold shrink-0">{{ strtoupper(substr($patient->name, 0, 1)) }}</div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">{{ $patient->name }}</h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                        <span>{{ $patient->phone }}</span>
                        @php $age = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : $patient->age_approximate; @endphp
                        @if($age)<span>{{ $age }} yrs</span>@endif
                        @if($patient->gender)<span class="capitalize">{{ $patient->gender }}</span>@endif
                        @if($patient->blood_group)<span class="px-2 py-0.5 rounded bg-red-50 text-red-700 text-xs font-semibold">{{ $patient->blood_group }}</span>@endif
                    </div>
                    @if($patient->abha_number)
                        <p class="text-xs text-slate-400 mt-1 font-mono">ABHA {{ $patient->abha_number }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right text-xs text-slate-400">
                @if($kpis['first_seen'])<p>First seen {{ $kpis['first_seen']->format('M d, Y') }}</p>@endif
                @if($kpis['last_visit'])<p>Last visit {{ $kpis['last_visit']->diffForHumans() }}</p>@endif
            </div>
        </div>

        {{-- Clinical alerts — the things a doctor must not miss --}}
        @php
            $allergies = array_filter(is_array($patient->allergies) ? $patient->allergies : [], 'is_scalar');
            $meds      = array_filter(is_array($patient->current_medications) ? $patient->current_medications : [], 'is_scalar');
            $mhx       = array_filter(is_array($patient->medical_history) ? $patient->medical_history : [], 'is_scalar');
        @endphp
        @if($allergies)
            <div class="mt-3 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                <strong>⚠ Allergies:</strong> {{ implode(', ', $allergies) }}
            </div>
        @endif
        @if($mhx)
            <div class="mt-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700">
                <strong>History:</strong> {{ implode(', ', $mhx) }}
            </div>
        @endif
        @if($meds)
            <div class="mt-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                <strong>Current meds:</strong> {{ implode(', ', $meds) }}
            </div>
        @endif
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <x-stat-card label="Visits with you" :value="$kpis['visits']" accent="blue" icon="calendar" />
        <x-stat-card label="Last visit" :value="$kpis['last_visit'] ? $kpis['last_visit']->format('M d, Y') : '—'" accent="purple" icon="clock" />
        <x-stat-card label="Tests ordered" :value="$kpis['labs']" accent="amber" icon="flask" />
        <x-stat-card label="Medicines prescribed" :value="$kpis['meds']" accent="green" icon="doc" />
    </div>

    {{-- Consultation timeline --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Consultation History</h3>
            <span class="text-xs text-slate-400">{{ $history->count() }} visit{{ $history->count() === 1 ? '' : 's' }} · newest first</span>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($history as $h)
                <div x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                    {{-- Visit header (click to expand) --}}
                    <button type="button" @click="open = !open" class="w-full text-left px-5 py-3 hover:bg-slate-50 flex items-start gap-3">
                        <div class="w-9 text-center shrink-0">
                            <p class="text-xs font-bold text-slate-700">{{ \Illuminate\Support\Str::before($h['date'], ' ') }}</p>
                            <p class="text-slate-400" style="font-size:10px">{{ \Illuminate\Support\Str::after($h['date'], ', ') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-slate-800">{{ $h['complaint'] }}</span>
                                @if($h['duration_days'])<span class="text-xs text-slate-400">· {{ $h['duration_days'] }}d</span>@endif
                                @if($h['triage'])
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ in_array($h['triage'], ['emergency','urgent']) ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500' }}">{{ ucfirst($h['triage']) }}</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-slate-400">
                                <span class="capitalize">{{ str_replace('_', ' ', $h['type']) }}</span>
                                <span class="font-mono">{{ $h['encounter_number'] }}</span>
                                <span>{{ $h['time'] }}</span>
                                @if($h['labs']->count())<span class="text-amber-600">{{ $h['labs']->count() }} test{{ $h['labs']->count() === 1 ? '' : 's' }}</span>@endif
                                @if($h['meds']->count())<span class="text-green-600">{{ $h['meds']->count() }} med{{ $h['meds']->count() === 1 ? '' : 's' }}</span>@endif
                                @if($h['follow_up'])<span class="text-blue-600">Follow-up {{ $h['follow_up'] }}</span>@endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <x-status-badge :status="$h['status']" type="encounter" />
                            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>

                    {{-- Visit detail --}}
                    {{-- plain x-show: the Alpine collapse plugin isn't registered in app.js --}}
                    <div x-show="open" style="display:none" class="px-5 pb-4 pt-1 bg-slate-50/60">
                        @php
                            $hasDetail = $h['vitals'] || $h['diagnosis'] || $h['soap'] || $h['advice'] || $h['labs']->count() || $h['meds']->count() || $h['follow_up'];
                        @endphp

                        @if(! $hasDetail)
                            <p class="text-xs text-slate-400 py-2">No clinical detail was recorded for this visit.</p>
                        @endif

                        {{-- Vitals --}}
                        @if($h['vitals'])
                            <div class="mb-3">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Vitals</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($h['vitals'] as $k => $v)
                                        <span class="px-2 py-1 rounded-lg bg-white border border-slate-200 text-xs">
                                            <span class="text-slate-400">{{ ucwords(str_replace('_', ' ', $k)) }}:</span>
                                            <span class="font-semibold text-slate-700">{{ is_scalar($v) ? $v : json_encode($v) }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Diagnosis --}}
                        @if($h['diagnosis'])
                            <div class="mb-3">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Diagnosis</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($h['diagnosis'] as $d)
                                        <span class="px-2 py-0.5 rounded bg-purple-100 text-purple-700 text-xs font-medium">{{ $d }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- SOAP --}}
                        @if($h['soap'])
                            <div class="mb-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach(['subjective' => 'Subjective', 'objective' => 'Objective', 'assessment' => 'Assessment', 'plan' => 'Plan'] as $key => $label)
                                    @php $val = $h['soap'][$key] ?? null; @endphp
                                    @if($val)
                                        <div class="bg-white border border-slate-200 rounded-lg p-3">
                                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">{{ $label }}</p>
                                            <p class="text-sm text-slate-700">{{ is_scalar($val) ? $val : implode(', ', array_filter((array) $val, 'is_scalar')) }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        {{-- Tests + Medicines --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @if($h['labs']->count())
                                <div class="bg-white border border-slate-200 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Tests ordered</p>
                                    @foreach($h['labs'] as $o)
                                        <div class="flex items-start justify-between gap-2 text-sm py-0.5">
                                            <span class="text-slate-700">{{ $o['items'] ? implode(', ', $o['items']) : ucfirst($o['type']) }}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full shrink-0 {{ $o['critical'] ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-500' }}">{{ $o['critical'] ? 'Critical' : ucfirst(str_replace('_', ' ', $o['status'])) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if($h['meds']->count())
                                <div class="bg-white border border-slate-200 rounded-lg p-3">
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Prescribed</p>
                                    @foreach($h['meds'] as $m)
                                        <div class="text-sm py-0.5">
                                            <span class="text-slate-700 font-medium">{{ $m['name'] }}</span>
                                            <span class="text-xs text-slate-400">{{ trim(implode(' · ', array_filter([$m['dosage'], $m['freq'], $m['days']]))) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Advice + follow-up --}}
                        @if($h['advice'])
                            <div class="mt-2 bg-white border border-slate-200 rounded-lg p-3">
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Advice</p>
                                <ul class="text-sm text-slate-700 list-disc list-inside">
                                    @foreach($h['advice'] as $a)<li>{{ $a }}</li>@endforeach
                                </ul>
                            </div>
                        @endif
                        @if($h['follow_up'])
                            <div class="mt-2 px-3 py-2 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
                                <strong>Follow-up:</strong> {{ $h['follow_up'] }}@if($h['follow_up_notes']) — {{ $h['follow_up_notes'] }}@endif
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-400">No consultation history with this patient yet.</div>
            @endforelse
        </div>
    </div>
@endsection
