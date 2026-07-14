@props([
    'label' => '',
    'value' => null,
    'delta' => null,   // int % change vs previous period; null hides the delta line
    'sub'   => null,   // muted caption shown when no delta
    'accent'=> 'blue', // blue|green|amber|cyan|purple|red|yellow
    'icon'  => null,   // key below, or a raw SVG path
])
@php
    // Only accent pairs verified to exist in the compiled CSS (no-rebuild pipeline).
    $tiles = [
        'blue'   => 'bg-blue-50 text-blue-600',
        'green'  => 'bg-green-50 text-green-600',
        'amber'  => 'bg-amber-50 text-amber-600',
        'cyan'   => 'bg-cyan-50 text-cyan-600',
        'purple' => 'bg-purple-50 text-purple-600',
        'red'    => 'bg-red-50 text-red-600',
        'yellow' => 'bg-yellow-50 text-yellow-600',
    ];
    $tile = $tiles[$accent] ?? $tiles['blue'];

    $icons = [
        'currency' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'cash'     => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        'clock'    => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'check'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'alert'    => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
        'doc'      => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
        'flask'    => 'M9 3v6.2a2 2 0 01-.34 1.12l-4.32 6.48A2 2 0 006 20h12a2 2 0 001.66-3.2l-4.32-6.48A2 2 0 0115 9.2V3M8 3h8M9 14h6',
        'box'      => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'users'    => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z',
        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'bed'      => 'M3 12h18M3 12v6m18-6v6M3 12V8a2 2 0 012-2h6a2 2 0 012 2v4m0 0h8M7 10h.01',
        'shield'   => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'chart'    => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'refresh'  => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        'ban'      => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
    ];
    $path = $icons[$icon] ?? $icon;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
    <div class="flex items-center justify-between">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $tile }}">
            @if($path)
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/></svg>
            @endif
        </div>
    </div>
    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</p>
    @if(! is_null($delta))
        @php $up = $delta >= 0; @endphp
        <p class="mt-1 text-xs font-medium {{ $up ? 'text-green-600' : 'text-red-600' }}">
            {{ $up ? '▲ +' : '▼ ' }}{{ $delta }}% <span class="text-slate-400 font-normal">vs prev</span>
        </p>
    @elseif($sub)
        <p class="mt-1 text-xs text-slate-400">{{ $sub }}</p>
    @endif
</div>
