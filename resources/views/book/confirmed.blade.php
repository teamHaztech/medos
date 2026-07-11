@extends('layouts.public')
@section('title', 'Booking Confirmed')
@section('brand', $hospital->name)
@section('subtitle', 'Appointment confirmed')

@section('content')
<div class="text-center py-4">
    <div class="w-16 h-16 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    </div>
    <h2 class="text-xl font-bold text-slate-800">Appointment booked!</h2>
    <p class="text-sm text-slate-500 mt-1">Please arrive 10 minutes early and show this token.</p>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-5 space-y-3">
    <div class="text-center border-b border-slate-100 pb-3">
        <p class="text-xs text-slate-400 uppercase tracking-wider">Your token</p>
        <p class="text-3xl font-bold text-blue-700">{{ $appointment->notes }}</p>
    </div>
    <div class="flex justify-between text-sm"><span class="text-slate-500">Patient</span><span class="font-medium text-slate-800">{{ $appointment->patient?->name }}</span></div>
    <div class="flex justify-between text-sm"><span class="text-slate-500">Doctor</span><span class="font-medium text-slate-800">{{ $appointment->doctor?->name }}</span></div>
    <div class="flex justify-between text-sm"><span class="text-slate-500">Department</span><span class="font-medium text-slate-800">{{ $appointment->doctor?->department ?? '—' }}</span></div>
    <div class="flex justify-between text-sm"><span class="text-slate-500">When</span><span class="font-medium text-slate-800">{{ optional($appointment->slot_start)->format('D, M d · g:i A') }}</span></div>
    @if($bill)
    <div class="flex justify-between text-sm"><span class="text-slate-500">Consultation fee</span><span class="font-medium text-slate-800">{{ $currency }}{{ number_format($bill->total_amount, 2) }} <span class="text-xs text-amber-600">· pay at hospital</span></span></div>
    @endif
</div>

<a href="{{ route('book.index') }}" class="block text-center text-sm text-blue-600 hover:text-blue-800 mt-5">Book another appointment</a>
@endsection
