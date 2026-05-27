@extends('layouts.app')
@section('title', 'Lab Results')
@section('page-title', 'Enter Lab Results')

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Order Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-bold text-white {{ ($order->patient?->gender ?? '') === 'female' ? 'bg-pink-500' : 'bg-blue-500' }}">
                    {{ strtoupper(substr($order->patient?->name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">{{ $order->patient?->name ?? 'Unknown' }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ ucfirst($order->patient?->gender ?? '') }}{{ $order->patient?->age_approximate ? ', ' . $order->patient->age_approximate . ' years' : '' }}
                        {{ $order->patient?->phone ? ' · ' . $order->patient->phone : '' }}
                    </p>
                </div>
            </div>
            <div class="text-right text-sm">
                @if($order->sample_id)
                <p class="font-mono bg-slate-100 text-slate-700 px-2 py-1 rounded text-xs inline-block">{{ $order->sample_id }}</p>
                @endif
                <p class="text-slate-400 mt-1">Ordered by <span class="font-medium text-slate-600">{{ $order->orderedBy?->name ?? '-' }}</span></p>
                <p class="text-slate-400">{{ $order->created_at?->format('d M Y, h:i A') }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Results Form --}}
    <form method="POST" action="{{ route('web.lab.results.save', $order->id) }}">
        @csrf

        @php
            $items = $order->items ?? [];
            $existingResults = $order->results ?? [];
        @endphp

        @foreach($items as $index => $item)
            @php
                $result = $existingResults[$index] ?? [];
                $testName = $item['name'] ?? 'Test ' . ($index + 1);
                $catalog = $testCatalog[$testName] ?? null;
                $rawRanges = $catalog && isset($catalog->reference_ranges) ? $catalog->reference_ranges : null;
                $ranges = $rawRanges ? (is_string($rawRanges) ? json_decode($rawRanges, true) : $rawRanges) : [];
                $unit = $catalog && isset($catalog->unit) ? ($catalog->unit ?? '') : '';
                $prev = $previousResults[$testName] ?? null;
                $patientGender = $order->patient?->gender ?? 'male';
                $genderRange = $ranges[$patientGender] ?? $ranges['default'] ?? $ranges;
                $rangeDisplay = '';
                if (!empty($genderRange['min']) && !empty($genderRange['max'])) {
                    $rangeDisplay = $genderRange['min'] . ' - ' . $genderRange['max'] . ($unit ? ' ' . $unit : '');
                }
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-bold text-slate-900">{{ $testName }}</h3>
                    @if($prev)
                    <div class="text-xs text-slate-400 bg-slate-50 px-2 py-1 rounded">
                        Previous: <span class="font-semibold text-slate-600">{{ $prev['value'] }}</span>
                        <span class="text-slate-300">·</span> {{ $prev['date'] }}
                    </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Result Value {{ $unit ? '(' . $unit . ')' : '' }}</label>
                        <input type="text" name="results[{{ $index }}][value]" value="{{ $result['value'] ?? '' }}" class="input-field w-full" placeholder="Enter value">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Reference Range</label>
                        <input type="text" name="results[{{ $index }}][reference_range]" value="{{ $result['reference_range'] ?? $rangeDisplay }}" class="input-field w-full bg-slate-50" placeholder="e.g. 4.0 - 11.0">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1">Notes</label>
                        <input type="text" name="results[{{ $index }}][notes]" value="{{ $result['notes'] ?? '' }}" class="input-field w-full" placeholder="Additional notes">
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-3">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="results[{{ $index }}][abnormal]" value="1" {{ !empty($result['abnormal']) ? 'checked' : '' }} class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                        <span class="text-sm font-medium text-slate-600">Abnormal</span>
                    </label>
                    @if(!empty($result['critical']))
                    <span class="text-xs font-bold text-red-600 bg-red-100 px-2 py-0.5 rounded-full">CRITICAL {{ strtoupper($result['critical']) }}</span>
                    @endif
                </div>

                <input type="hidden" name="results[{{ $index }}][test_name]" value="{{ $testName }}">
                <input type="hidden" name="results[{{ $index }}][unit]" value="{{ $unit }}">
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Results</button>
            <a href="{{ route('web.lab.dashboard') }}" class="btn-secondary">Back to Dashboard</a>
        </div>
    </form>
</div>
@endsection
