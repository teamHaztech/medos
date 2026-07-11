@extends('layouts.public')
@section('title', 'Select Hospital')
@section('brand', 'Book an Appointment')
@section('subtitle', 'Choose a hospital')

@section('content')
<p class="text-sm text-slate-500 mb-4">Select a hospital to book with:</p>
<div class="space-y-2">
    @forelse($hospitals as $h)
    <a href="{{ route('book.index', ['hospital' => $h->slug]) }}" class="block bg-white border border-slate-200 hover:border-blue-300 rounded-xl px-4 py-3">
        <p class="text-sm font-semibold text-slate-800">{{ $h->name }}</p>
        <p class="text-xs text-slate-500">{{ $h->city }}</p>
    </a>
    @empty
    <p class="text-sm text-slate-400 text-center py-8">No hospitals available.</p>
    @endforelse
</div>
@endsection
