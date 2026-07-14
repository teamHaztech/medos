@props([
    'title' => '',
    'subtitle' => null,
])

<div class="flex flex-wrap items-end justify-between gap-3 mb-6">
    <div>
        <h2 class="text-xl font-bold text-slate-800">{{ $title }}</h2>
        @if($subtitle)<p class="text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex items-center gap-2 text-xs">{{ $actions }}</div>
    @endisset
</div>
