@extends('layouts.app')
@section('title', 'Live Queue')
@section('page-title', 'Live Queue')

@php
    $cur = \App\Modules\Core\Services\RegionService::currency();
    $hSlug = $hospital?->slug;
    $q = $hSlug ? ('?hospital=' . $hSlug) : '';
    // name -> doctor id map so each queue can link to its own room screen
    $docIdByName = collect($doctors)->keyBy('name');
@endphp

@section('content')
<div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <p class="text-sm text-slate-500">Live doctor &amp; lab queues · auto-refreshes every 30s</p>
        <div class="flex items-center gap-2">
            @include('admin._add_to_queue')
            <a href="{{ url('/kiosk/queue-display') }}{{ $q }}" target="_blank" class="btn-secondary">📺 Doctors Board</a>
            <a href="{{ url('/kiosk/room/lab') }}{{ $q }}" target="_blank" class="btn-secondary">🧪 Lab Board</a>
            <button type="button" onclick="location.reload()" class="btn-secondary">Refresh</button>
        </div>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    {{-- Doctor queues --}}
    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-3">Doctor Queues</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @forelse($doctorQueue as $docName => $appts)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                @php $docId = $docIdByName[$docName]->id ?? null; @endphp
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-slate-700 truncate">{{ $docName }}</h4>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs font-medium text-slate-500">{{ $appts->count() }} in queue</span>
                        @if($docId)
                            <a href="{{ url('/kiosk/room/' . $docId) }}{{ $q }}" target="_blank" title="Open room screen" class="text-xs font-medium text-blue-600 hover:text-blue-800">🖥 Screen</a>
                            <button type="button" onclick="copyRoomLink(this, '{{ url('/kiosk/room/' . $docId) . $q }}')" title="Copy link" class="text-xs font-medium text-slate-500 hover:text-slate-700">Copy</button>
                        @endif
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($appts as $i => $apt)
                        @php $st = is_object($apt->status) ? $apt->status->value : ($apt->status ?? ''); @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5 {{ $st === 'in_progress' ? 'bg-blue-50' : '' }}">
                            <div class="w-7 h-7 flex items-center justify-center rounded-full text-xs font-bold {{ $st === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' }}">{{ $i + 1 }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate">{{ $apt->patient?->name ?? 'Unknown' }}</p>
                                <p class="text-xs text-slate-400">Token {{ $apt->notes ?? '—' }}</p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $st === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700' }}">{{ $st === 'in_progress' ? 'With doctor' : 'Waiting' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-slate-200 p-10 text-center text-slate-400 text-sm">No patients in any doctor queue right now.</div>
        @endforelse
    </div>

    {{-- Lab queue --}}
    <div class="flex items-center justify-between gap-2 mb-3">
        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400">Lab Queue <span class="text-slate-300">({{ $labQueue->count() }})</span></h3>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ url('/kiosk/room/lab') }}{{ $q }}" target="_blank" title="Open lab screen" class="text-xs font-medium text-blue-600 hover:text-blue-800">🖥 Screen</a>
            <button type="button" onclick="copyRoomLink(this, '{{ url('/kiosk/room/lab') . $q }}')" title="Copy link" class="text-xs font-medium text-slate-500 hover:text-slate-700">Copy</button>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-100">
            @forelse($labQueue as $o)
                @php
                    $items = collect($o->items ?? [])->pluck('name')->filter()->implode(', ');
                    $pr = $o->priority ?? 'routine';
                    $os = is_object($o->status) ? $o->status->value : ($o->status ?? 'ordered');
                @endphp
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800">{{ $o->patient?->name ?? 'Unknown' }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ $items ?: 'Tests' }} · Token {{ $o->notes ?? '—' }}</p>
                    </div>
                    @if($pr !== 'routine')<span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $pr === 'stat' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">{{ strtoupper($pr) }}</span>@endif
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-600">{{ ucfirst(str_replace('_', ' ', $os)) }}</span>
                </div>
            @empty
                <div class="p-10 text-center text-slate-400 text-sm">Lab queue is empty.</div>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
    function copyRoomLink(btn, url) {
        navigator.clipboard.writeText(url).then(function () {
            const original = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = original; }, 1500);
        });
    }

    setInterval(function () {
        if (document.hidden) return;
        const modalOpen = [...document.querySelectorAll('.fixed.inset-0')].some(el => el.offsetParent !== null);
        if (!modalOpen) location.reload();
    }, 30000);
</script>
@endpush
@endsection
