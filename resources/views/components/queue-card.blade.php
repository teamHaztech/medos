@props([
    'token' => '0',
    'name' => 'Patient',
    'complaint' => '',
    'urgency' => 'routine',
    'waitTime' => '0 min',
    'active' => false,
])

@php
    $urgencyColors = [
        'emergency'   => 'border-l-red-500 bg-red-50',
        'urgent'      => 'border-l-orange-500 bg-orange-50',
        'semi_urgent' => 'border-l-yellow-500 bg-yellow-50',
        'routine'     => 'border-l-green-500 bg-white',
    ];
    $cardClass = $urgencyColors[$urgency] ?? $urgencyColors['routine'];
    $activeClass = $active ? 'ring-2 ring-blue-500 shadow-md' : '';
@endphp

<div {{ $attributes->merge(['class' => "border-l-4 rounded-lg p-4 {$cardClass} {$activeClass} transition-all"]) }}>
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
            <div class="px-3 py-2 bg-slate-800 text-white rounded-lg flex items-center justify-center text-sm font-bold whitespace-nowrap flex-shrink-0">
                {{ $token }}
            </div>
            <div class="min-w-0">
                <p class="font-medium text-slate-900 truncate">{{ $name }}</p>
                @if($complaint)
                    <p class="text-sm text-slate-500 mt-0.5 line-clamp-1">{{ $complaint }}</p>
                @endif
            </div>
        </div>
        <div class="text-right flex flex-col items-end gap-1">
            <x-status-badge :status="$urgency" type="triage" />
            <span class="text-xs text-slate-400">{{ $waitTime }}</span>
        </div>
    </div>
</div>
