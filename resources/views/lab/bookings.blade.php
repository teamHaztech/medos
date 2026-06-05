@extends('layouts.app')
@section('title', 'Lab Appointments')
@section('page-title', 'Lab Appointments')

@section('content')
<div>
    <div class="flex items-end gap-3 mb-5">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date</label>
            <input type="date" value="{{ $date }}" onchange="window.location.href='{{ route('web.lab.bookings') }}?date='+this.value" class="input-field">
        </div>
        <p class="text-sm text-slate-500 pb-2">{{ $labBookings->count() }} booking(s) on {{ \Carbon\Carbon::parse($date)->format('D, M d') }}</p>
    </div>

    @include('lab._bookings_table', ['labBookings' => $labBookings])
</div>
@endsection
