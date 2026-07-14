@props([
    // `title` is kept for back-compat but intentionally NOT rendered: every page
    // already shows its title in the top bar (@yield('page-title')), so repeating
    // it here produced a duplicate heading. We render only the subtitle + actions.
    'title' => '',
    'subtitle' => null,
])

@if($subtitle || isset($actions))
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    @if($subtitle)
        <p class="text-sm text-slate-500">{{ $subtitle }}</p>
    @else
        <span></span>
    @endif
    @isset($actions)
        <div class="flex items-center gap-2 text-xs">{{ $actions }}</div>
    @endisset
</div>
@endif
