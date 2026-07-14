@props([
    'route'  => null,   // named route accepting a ?period= query
    'period' => 'month',
])
<div class="flex flex-wrap gap-2">
    @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label)
        <a href="{{ route($route, ['period' => $key]) }}"
           class="px-3 py-1.5 text-sm rounded-lg border transition {{ $period === $key ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
