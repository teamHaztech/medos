@extends('layouts.app')

@section('title', 'Voice AI Dashboard')
@section('page-title', 'Voice AI')

@php
    $cur = \App\Modules\Core\Services\RegionService::currency();
    $isEnabled = $settings->is_enabled ?? false;
    $phoneNumbers = $settings->phone_numbers ?? [];
    $totalCalls = $todayStats['total_calls'] ?? 0;
    $answered = $todayStats['answered'] ?? 0;
    $missed = $todayStats['missed'] ?? 0;
    $aiResolved = $todayStats['ai_resolved'] ?? 0;
    $humanTransferred = $todayStats['human_transferred'] ?? 0;
    $avgDuration = $todayStats['avg_duration'] ?? 0;
    $totalCost = $todayStats['total_cost'] ?? 0;
    $resolutionPct = $totalCalls > 0 ? round(($aiResolved / $totalCalls) * 100, 1) : 0;
    $avgMin = floor($avgDuration / 60);
    $avgSec = $avgDuration % 60;

    $purposeColors = [
        'appointment_booking' => ['bg' => 'bg-blue-500', 'light' => 'bg-blue-100', 'text' => 'text-blue-700'],
        'schedule_check'      => ['bg' => 'bg-purple-500', 'light' => 'bg-purple-100', 'text' => 'text-purple-700'],
        'lab_results'         => ['bg' => 'bg-amber-500', 'light' => 'bg-amber-100', 'text' => 'text-amber-600'],
        'queue_status'        => ['bg' => 'bg-green-500', 'light' => 'bg-green-100', 'text' => 'text-green-700'],
        'general_inquiry'     => ['bg' => 'bg-slate-400', 'light' => 'bg-slate-100', 'text' => 'text-slate-600'],
        'emergency'           => ['bg' => 'bg-red-500', 'light' => 'bg-red-100', 'text' => 'text-red-700'],
        'callback'            => ['bg' => 'bg-cyan-500', 'light' => 'bg-cyan-100', 'text' => 'text-cyan-700'],
    ];

    $statusColors = [
        'completed'   => 'bg-green-100 text-green-700',
        'missed'      => 'bg-red-100 text-red-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'transferred' => 'bg-amber-100 text-amber-600',
        'failed'      => 'bg-slate-100 text-slate-600',
    ];

    $sentimentColors = [
        'positive'   => 'bg-green-100 text-green-700',
        'neutral'    => 'bg-slate-100 text-slate-600',
        'negative'   => 'bg-red-100 text-red-700',
        'frustrated' => 'bg-orange-100 text-orange-700',
    ];

    $hourlyArr = is_array($callsByHour) ? $callsByHour : (method_exists($callsByHour, 'toArray') ? $callsByHour->toArray() : []);
    $maxHourly = max(1, !empty($hourlyArr) ? max($hourlyArr) : 1);
    $reasonArr = $topReasons instanceof \Illuminate\Support\Collection ? $topReasons->pluck('cnt')->toArray() : (is_array($topReasons) ? $topReasons : []);
    $maxReason = max(1, !empty($reasonArr) ? max($reasonArr) : 1);
@endphp

@section('content')

{{-- Top Header Bar --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6"
     x-data="{ enabled: {{ $isEnabled ? 'true' : 'false' }}, toggling: false }">
    <div class="flex flex-wrap items-center justify-between gap-4">
        {{-- Left: Title + Status + Toggle --}}
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center bg-purple-100 text-purple-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800">Voice AI</h2>
                <div class="flex items-center gap-2 mt-0.5">
                    <span x-show="enabled" class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Active
                    </span>
                    <span x-show="!enabled" class="inline-flex items-center gap-1.5 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Inactive
                    </span>
                </div>
            </div>
            {{-- Toggle Button --}}
            <button
                @click="
                    toggling = true;
                    fetch('/voice-calls/toggle', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    })
                    .then(r => r.json())
                    .then(d => { enabled = d.is_enabled; toggling = false; })
                    .catch(() => { toggling = false; })
                "
                :disabled="toggling"
                class="ml-2 relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none"
                :class="enabled ? 'bg-green-500' : 'bg-slate-300'"
            >
                <span class="inline-block w-4 h-4 transform rounded-full bg-white shadow transition-transform"
                      :class="enabled ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
        </div>

        {{-- Right: Phone numbers + links --}}
        <div class="flex flex-wrap items-center gap-3">
            @if(!empty($phoneNumbers))
                <div class="flex items-center gap-1.5 text-sm text-slate-600">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    @foreach($phoneNumbers as $i => $num)
                        <span class="font-medium">{{ $num }}</span>@if(!$loop->last)<span class="text-slate-300">|</span>@endif
                    @endforeach
                </div>
            @endif
            <a href="{{ url('/voice-calls/settings') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Settings
            </a>
            <a href="{{ url('/voice-calls/log') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Call Log
            </a>
        </div>
    </div>
</div>

{{-- KPI Stat Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    @php
        $kpis = [
            [
                'label' => 'Total Calls',
                'value' => number_format($totalCalls),
                'accent' => 'blue',
                'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
            ],
            [
                'label' => 'Answered',
                'value' => number_format($answered),
                'accent' => 'green',
                'icon' => 'M5 13l4 4L19 7',
            ],
            [
                'label' => 'Missed',
                'value' => number_format($missed),
                'accent' => 'red',
                'icon' => 'M6 18L18 6M6 6l12 12',
            ],
            [
                'label' => 'AI Resolution',
                'value' => $resolutionPct . '%',
                'accent' => 'purple',
                'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            ],
            [
                'label' => 'Avg Duration',
                'value' => $avgMin . 'm ' . $avgSec . 's',
                'accent' => 'amber',
                'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'label' => 'Cost Today',
                'value' => $cur . number_format($totalCost, 2),
                'accent' => 'cyan',
                'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
            ],
        ];
    @endphp

    @foreach($kpis as $kpi)
        <x-stat-card :label="$kpi['label']" :value="$kpi['value']" :accent="$kpi['accent']" :icon="$kpi['icon']" />
    @endforeach
</div>

{{-- Live Active Calls Panel --}}
@if($activeCalls->count() > 0)
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6"
     x-data="{
        calls: {{ Js::from($activeCalls->map(fn($c) => [
            'id' => $c->id,
            'caller_number' => $c->caller_number ?? '',
            'patient_name' => $c->patient?->name ?? '',
            'started_at' => $c->started_at?->toIso8601String() ?? now()->toIso8601String(),
            'language' => $c->language ?? 'EN',
            'sentiment' => $c->sentiment ?? 'neutral',
            'ai_confidence' => $c->ai_confidence ?? 0,
            'last_transcript' => $c->transcripts?->last()?->text ?? '',
        ])) }},
        now: Date.now(),
        interval: null
     }"
     x-init="
        interval = setInterval(() => { now = Date.now() }, 1000);
        setInterval(() => {
            fetch('/voice-calls/live-calls')
                .then(r => r.json())
                .then(d => { if(d.calls) calls = d.calls; })
                .catch(() => {});
        }, 3000);
     "
     x-on:destroy.window="clearInterval(interval)"
>
    {{-- Header --}}
    <div class="flex items-center gap-2 mb-4">
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
        </span>
        <h3 class="text-sm font-semibold text-slate-700">Live Calls</h3>
        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700" x-text="calls.length"></span>
    </div>

    {{-- Active Call Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <template x-for="call in calls" :key="call.id">
            <div class="border border-slate-200 rounded-lg p-4">
                {{-- Caller info --}}
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-lg font-bold text-slate-800" x-text="call.caller_number"></p>
                        <p class="text-xs text-slate-500" x-show="call.patient_name" x-text="call.patient_name"></p>
                    </div>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700" x-text="call.language"></span>
                </div>

                {{-- Duration timer --}}
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm font-medium text-slate-700"
                          x-text="(() => {
                              let s = Math.floor((now - new Date(call.started_at).getTime()) / 1000);
                              let m = Math.floor(s / 60);
                              let sec = s % 60;
                              return m + 'm ' + (sec < 10 ? '0' : '') + sec + 's';
                          })()"></span>
                </div>

                {{-- Sentiment dot --}}
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs text-slate-500">Sentiment</span>
                    <span class="w-2.5 h-2.5 rounded-full"
                          :class="{
                              'bg-green-500': call.sentiment === 'positive',
                              'bg-amber-500': call.sentiment === 'neutral',
                              'bg-red-500': call.sentiment === 'negative' || call.sentiment === 'frustrated'
                          }"></span>
                    <span class="text-xs text-slate-500" x-text="call.sentiment" style="text-transform: capitalize;"></span>
                </div>

                {{-- AI Confidence bar --}}
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-slate-500">AI Confidence</span>
                        <span class="text-xs font-medium text-slate-700" x-text="Math.round(call.ai_confidence) + '%'"></span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full" style="height: 6px;">
                        <div class="bg-purple-500 rounded-full transition-all" style="height: 6px;"
                             :style="'width: ' + call.ai_confidence + '%'"></div>
                    </div>
                </div>

                {{-- Last transcript --}}
                <p class="text-xs text-slate-500 italic truncate mb-3" x-text="call.last_transcript || 'Listening...'"></p>

                {{-- Take Over button --}}
                <button @click="alert('WebRTC takeover coming soon. Call ID: ' + call.id)"
                        class="w-full px-3 py-1.5 text-xs font-medium text-amber-600 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
                    Take Over
                </button>
            </div>
        </template>
    </div>
</div>
@endif

{{-- Two Column: Calls by Hour + Top Reasons --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Left 2/3: Calls by Hour Chart --}}
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-1">Calls by Hour</h3>
        <p class="text-xs text-slate-400 mb-4">Today's call volume distribution (6 AM - 10 PM)</p>

        <div class="flex items-end gap-1" style="height: 160px;">
            @for($h = 6; $h <= 22; $h++)
                @php
                    $count = $callsByHour[$h] ?? 0;
                    $heightPct = $maxHourly > 0 ? round(($count / $maxHourly) * 100) : 0;
                    $minHeight = $count > 0 ? 8 : 2;
                @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full">
                    <div class="w-full bg-blue-500 rounded-sm transition-all hover:bg-blue-600"
                         style="height: {{ max($minHeight, $heightPct) }}%; min-height: {{ $minHeight }}px;"
                         title="{{ $h > 12 ? ($h - 12) : $h }}{{ $h >= 12 ? 'PM' : 'AM' }}: {{ $count }} calls"></div>
                    <span class="text-xs text-slate-400 mt-1" style="font-size: 10px;">{{ $h > 12 ? ($h - 12) : $h }}{{ $h >= 12 ? 'p' : 'a' }}</span>
                </div>
            @endfor
        </div>
    </div>

    {{-- Right 1/3: Top Call Reasons --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h3 class="text-sm font-semibold text-slate-700 mb-1">Call Reasons</h3>
        <p class="text-xs text-slate-400 mb-4">Purpose breakdown</p>

        <div class="space-y-3">
            @forelse($topReasons as $purpose => $count)
                @php
                    $colors = $purposeColors[$purpose] ?? ['bg' => 'bg-slate-400', 'light' => 'bg-slate-100', 'text' => 'text-slate-600'];
                    $widthPct = round(($count / $maxReason) * 100);
                    $label = str_replace('_', ' ', ucfirst($purpose));
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-medium text-slate-700">{{ $label }}</span>
                        <span class="text-xs font-medium {{ $colors['text'] }}">{{ $count }}</span>
                    </div>
                    <div class="w-full {{ $colors['light'] }} rounded-full" style="height: 8px;">
                        <div class="{{ $colors['bg'] }} rounded-full" style="height: 8px; width: {{ $widthPct }}%;"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400 text-center py-4">No calls yet today</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Recent Calls Table --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-6">
    <div class="flex items-center justify-between p-5 border-b border-slate-200">
        <h3 class="text-sm font-semibold text-slate-700">Recent Calls</h3>
        <a href="{{ url('/voice-calls/log') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
            View all calls &rarr;
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-slate-600">
            <thead>
                <tr class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wider">
                    <th class="px-5 py-3 text-left">Time</th>
                    <th class="px-5 py-3 text-left">Caller</th>
                    <th class="px-5 py-3 text-left">Patient</th>
                    <th class="px-5 py-3 text-left">Purpose</th>
                    <th class="px-5 py-3 text-left">Duration</th>
                    <th class="px-5 py-3 text-left">Status</th>
                    <th class="px-5 py-3 text-left">Sentiment</th>
                    <th class="px-5 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($recentCalls as $call)
                    @php
                        $callStatus = is_object($call->status) ? $call->status->value : ($call->status ?? 'unknown');
                        $callSentiment = is_object($call->sentiment) ? $call->sentiment->value : ($call->sentiment ?? 'neutral');
                        $callPurpose = is_object($call->call_purpose) ? $call->call_purpose->value : ($call->call_purpose ?? '');
                        $dur = $call->duration ?? 0;
                        $durMin = floor($dur / 60);
                        $durSec = $dur % 60;
                        $purposeLabel = str_replace('_', ' ', ucfirst($callPurpose));
                        $statusBadge = $statusColors[$callStatus] ?? 'bg-slate-100 text-slate-600';
                        $sentimentBadge = $sentimentColors[$callSentiment] ?? 'bg-slate-100 text-slate-600';
                    @endphp
                    <tr class="hover:bg-slate-50 transition-colors cursor-pointer"
                        onclick="window.location='{{ url('/voice-calls/' . $call->id) }}'">
                        <td class="px-5 py-3 whitespace-nowrap text-xs text-slate-500">
                            {{ $call->created_at?->format('h:i A') ?? '' }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="font-medium text-slate-700">{{ $call->caller_number ?? '' }}</span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            {{ $call->patient?->name ?? '' }}
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            @if($callPurpose)
                                @php $pColors = $purposeColors[$callPurpose] ?? ['light' => 'bg-slate-100', 'text' => 'text-slate-600']; @endphp
                                <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $pColors['light'] }} {{ $pColors['text'] }}">
                                    {{ $purposeLabel }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-xs">
                            {{ $durMin }}m {{ $durSec }}s
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $statusBadge }}">
                                {{ ucfirst(str_replace('_', ' ', $callStatus)) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap">
                            <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $sentimentBadge }}">
                                {{ ucfirst($callSentiment) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 whitespace-nowrap text-center">
                            <a href="{{ url('/voice-calls/' . $call->id) }}"
                               onclick="event.stopPropagation()"
                               class="inline-flex items-center gap-1 px-3 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-400">
                            No calls recorded today yet
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Callback Queue Banner --}}
@if(($callbackQueue ?? 0) > 0)
<div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-amber-100 text-amber-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-medium text-amber-800">
                {{ $callbackQueue }} {{ Str::plural('call', $callbackQueue) }} waiting for callback
            </p>
            <p class="text-xs text-amber-600 mt-0.5">These callers requested a callback and are waiting to be contacted.</p>
        </div>
        <a href="{{ url('/voice-calls/callbacks') }}" class="px-4 py-2 text-xs font-medium text-amber-600 bg-amber-100 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
            View Queue
        </a>
    </div>
</div>
@endif

@endsection
