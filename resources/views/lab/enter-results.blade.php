@extends('layouts.app')
@section('title', 'Enter Lab Results')
@section('page-title', 'Enter Lab Results')

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Order Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="grid grid-cols-3 gap-4">
            <div>
                <span class="text-xs text-slate-500 uppercase tracking-wider">Patient</span>
                <p class="font-semibold text-slate-900">{{ $order->patient->name ?? 'Unknown' }}</p>
            </div>
            <div>
                <span class="text-xs text-slate-500 uppercase tracking-wider">Order Date</span>
                <p class="font-semibold text-slate-900">{{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>
            <div>
                <span class="text-xs text-slate-500 uppercase tracking-wider">Ordered By</span>
                <p class="font-semibold text-slate-900">{{ $order->orderedBy->name ?? '-' }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
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
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-4">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">{{ $item['name'] ?? 'Test ' . ($index + 1) }}</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Result Value</label>
                        <input type="text" name="results[{{ $index }}][value]" value="{{ $result['value'] ?? '' }}" class="input-field w-full" placeholder="Enter result value">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reference Range</label>
                        <input type="text" name="results[{{ $index }}][reference_range]" value="{{ $result['reference_range'] ?? '' }}" class="input-field w-full" placeholder="e.g. 4.0 - 11.0">
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="results[{{ $index }}][abnormal]" value="1" id="abnormal_{{ $index }}" {{ !empty($result['abnormal']) ? 'checked' : '' }} class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                        <label for="abnormal_{{ $index }}" class="text-sm font-medium text-slate-700">Abnormal?</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <input type="text" name="results[{{ $index }}][notes]" value="{{ $result['notes'] ?? '' }}" class="input-field w-full" placeholder="Additional notes">
                    </div>
                </div>

                <input type="hidden" name="results[{{ $index }}][test_name]" value="{{ $item['name'] ?? '' }}">
            </div>
        @endforeach

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">Save Results</button>
            <a href="{{ route('web.lab.dashboard') }}" class="btn-secondary">Back to Dashboard</a>
        </div>
    </form>
</div>
@endsection
