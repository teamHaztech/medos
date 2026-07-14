@extends('layouts.app')
@section('title', 'Lab Slots')
@section('page-title', 'Lab Availability & Slots')

@section('content')
@php
    $days = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
    $ranges = [];
    foreach (array_keys($days) as $d) {
        $ranges[$d] = array_map(fn($b) => ['start' => $b['start'] ?? '09:00', 'end' => $b['end'] ?? '13:00'], $schedule[$d] ?? []);
    }
@endphp
<div class="max-w-3xl" x-data="labSlots()">
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <p class="text-sm text-slate-500 mb-4">Set the days &amp; hours the lab collects samples. Patients booking tests (chat &amp; kiosk) pick from these slots. Each slot can hold several patients.</p>

    <form method="POST" action="{{ route('web.lab.slots.save') }}">
        @csrf

        {{-- Slot settings --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Slot length (minutes)</label>
                    <input type="number" name="slot_duration" min="5" max="120" x-model="duration" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Capacity per slot</label>
                    <input type="number" name="capacity" min="1" max="50" x-model="capacity" class="input-field">
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1" x-model="isActive" class="rounded border-slate-300">
                        Lab accepting bookings
                    </label>
                </div>
            </div>
        </div>

        {{-- Weekly schedule --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 divide-y divide-slate-100">
            @foreach($days as $key => $label)
            <div class="p-4" x-data="{ day: '{{ $key }}' }">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" :checked="ranges['{{ $key }}'].length > 0" @change="$event.target.checked ? addRange('{{ $key }}') : (ranges['{{ $key }}'] = [])" class="rounded border-slate-300">
                        <span class="text-sm font-semibold text-slate-800">{{ $label }}</span>
                    </div>
                    <button type="button" @click="addRange('{{ $key }}')" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">+ Add time range</button>
                </div>
                <template x-if="ranges['{{ $key }}'].length === 0">
                    <p class="text-xs text-slate-400 pl-6">Closed</p>
                </template>
                <div class="space-y-2 pl-6">
                    <template x-for="(r, i) in ranges['{{ $key }}']" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="time" :name="'schedule[{{ $key }}][' + i + '][start]'" x-model="r.start" class="input-field w-32 py-1.5">
                            <span class="text-slate-400">to</span>
                            <input type="time" :name="'schedule[{{ $key }}][' + i + '][end]'" x-model="r.end" class="input-field w-32 py-1.5">
                            <button type="button" @click="ranges['{{ $key }}'].splice(i,1)" class="text-red-500 hover:text-red-700 text-lg leading-none">&times;</button>
                        </div>
                    </template>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-5">
            <button type="submit" class="btn-primary px-6 py-2.5">Save Availability</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function labSlots() {
    return {
        duration: {{ (int) $duration }},
        capacity: {{ (int) $capacity }},
        isActive: {{ $isActive ? 'true' : 'false' }},
        ranges: @json($ranges),
        addRange(day) {
            this.ranges[day].push({ start: '09:00', end: '13:00' });
        },
    };
}
</script>
@endpush
@endsection
