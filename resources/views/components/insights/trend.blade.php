@props([
    'series' => [],       // array of ['label'=>, 'value'=>, 'tip'=>?]
    'accent' => 'blue',   // bar colour (verified-present bg-*-500 families only)
    'title'  => null,
    'meta'   => null,     // small right-aligned caption (e.g. "Total ₹12,340")
    'empty'  => 'No activity recorded in this period yet.',
])
@php
    $bars = [
        'blue' => 'bg-blue-500', 'green' => 'bg-green-500', 'indigo' => 'bg-indigo-500',
        'purple' => 'bg-purple-500', 'cyan' => 'bg-cyan-500', 'amber' => 'bg-amber-500',
        'pink' => 'bg-pink-500', 'red' => 'bg-red-500', 'rose' => 'bg-rose-500',
    ];
    $bar   = $bars[$accent] ?? $bars['blue'];
    $data  = collect($series);
    $max   = max($data->max('value') ?: 0, 1);
    $total = $data->sum('value');
    $n     = max($data->count(), 1);
    $width = max($n * 26, 300);
@endphp

<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
    @if($title || $meta)
    <div class="flex items-center justify-between mb-4">
        @if($title)<h3 class="text-sm font-semibold text-slate-700">{{ $title }}</h3>@endif
        @if($meta)<span class="text-xs text-slate-400">{{ $meta }}</span>@endif
    </div>
    @endif

    @if($total > 0)
    <div class="overflow-x-auto">
        <div class="flex items-end gap-1" style="height: 200px; min-width: {{ $width }}px;">
            @foreach($series as $b)
                @php $h = round((($b['value'] ?? 0) / $max) * 100); @endphp
                <div class="flex-1 flex flex-col items-center justify-end h-full" style="min-width: 18px;">
                    <div class="w-full rounded-t {{ ($b['value'] ?? 0) > 0 ? $bar : 'bg-slate-100' }}"
                         style="height: {{ max($h, ($b['value'] ?? 0) > 0 ? 2 : 0) }}%;"
                         title="{{ $b['tip'] ?? ($b['label'] . ' — ' . number_format($b['value'] ?? 0)) }}"></div>
                </div>
            @endforeach
        </div>
        <div class="flex gap-1 mt-2" style="min-width: {{ $width }}px;">
            @foreach($series as $b)
                <div class="flex-1 text-center text-slate-400" style="min-width: 18px; font-size: 10px;">{{ $b['label'] }}</div>
            @endforeach
        </div>
    </div>
    @else
    <p class="text-sm text-slate-400 text-center py-12">{{ $empty }}</p>
    @endif
</div>
