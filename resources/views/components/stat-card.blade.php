@props([
    'label' => '',
    'value' => null,
    'accent' => 'blue',
    'icon' => null,       // a known key below, or a raw SVG path string
    'sub' => null,        // small muted text after the label
    'xtext' => null,      // optional Alpine expression for a live value
])

@php
    // Explicit class strings (not interpolated) so they survive the no-rebuild pipeline.
    $accents = [
        'blue'   => 'bg-blue-100 text-blue-600',
        'green'  => 'bg-green-100 text-green-600',
        'amber'  => 'bg-amber-100 text-amber-600',
        'purple' => 'bg-purple-100 text-purple-600',
        'red'    => 'bg-red-100 text-red-600',
        'teal'   => 'bg-teal-100 text-teal-600',
        'slate'  => 'bg-slate-100 text-slate-600',
    ];
    $tile = $accents[$accent] ?? $accents['blue'];

    $icons = [
        'users'    => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z',
        'clock'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'check'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'alert'    => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        'refresh'  => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'doc'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'flask'    => 'M9 3v6.2a2 2 0 01-.34 1.12l-4.32 6.48A2 2 0 006 20h12a2 2 0 001.66-3.2l-4.32-6.48A2 2 0 0115 9.2V3M8 3h8M9 14h6',
        'box'      => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    ];
    $path = $icons[$icon] ?? $icon;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
    <div class="flex items-start justify-between">
        <div class="w-10 h-10 rounded-lg flex items-center justify-center {{ $tile }}">
            @if($path)
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/></svg>
            @endif
        </div>
        {{ $slot }}
    </div>
    <p class="text-2xl font-bold text-slate-800 mt-3" @if($xtext) x-text="{{ $xtext }}" @endif>{{ $value }}</p>
    <p class="text-xs text-slate-500 mt-0.5">{{ $label }}@if($sub) <span class="text-slate-400">{{ $sub }}</span>@endif</p>
</div>
