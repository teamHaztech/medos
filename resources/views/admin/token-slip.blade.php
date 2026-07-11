@extends('layouts.app')
@section('title', 'Token Slip')
@section('page-title', 'Token Issued')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<style>@media print { body * { visibility: hidden; } #slip, #slip * { visibility: visible; } #slip { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; } .print-hide { display: none !important; } }</style>
<div class="max-w-md mx-auto">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm print:hidden">{{ session('success') }}</div>@endif

    {{-- Slip --}}
    <div id="slip" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="text-center px-6 py-4 border-b border-slate-200">
            <h2 class="text-lg font-bold text-slate-900">{{ $hospital?->name ?? 'Hospital' }}</h2>
            <p class="text-xs text-slate-400">Token Slip · {{ optional($bill->issued_at ?? $bill->created_at)->format('D, M d Y · g:i A') }}</p>
        </div>

        <div class="px-6 py-8 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-1">Your Token</p>
            <p class="text-5xl font-extrabold text-blue-700 tracking-tight">{{ $token ?: '—' }}</p>
            @if($position)<p class="mt-2 text-sm text-slate-500">Position in queue: <span class="font-bold text-slate-700">{{ $position }}</span></p>@endif
        </div>

        <div class="px-6 pb-6 space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-slate-400">Patient</span><span class="font-medium text-slate-800">{{ $bill->patient?->name ?? '—' }}</span></div>
            @if($doctor)<div class="flex justify-between"><span class="text-slate-400">Doctor</span><span class="font-medium text-slate-800">{{ $doctor->name }}{{ $doctor->department ? ' · '.$doctor->department : '' }}</span></div>@endif
            <div class="border-t border-dashed border-slate-200 my-2"></div>
            @foreach($bill->line_items ?? [] as $li)
                <div class="flex justify-between text-slate-600"><span>{{ $li['description'] ?? 'Charge' }}{{ ($li['quantity'] ?? 1) > 1 ? ' × '.$li['quantity'] : '' }}</span><span>{{ $cur }}{{ number_format($li['total'] ?? 0, 2) }}</span></div>
            @endforeach
            <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-slate-800"><span>Paid ({{ ucfirst($bill->payment_method ?? 'cash') }})</span><span>{{ $cur }}{{ number_format($bill->total_amount, 2) }}</span></div>
            <p class="text-xs text-slate-400 text-center pt-3">Receipt {{ $bill->bill_number }} · Please keep this slip and wait to be called.</p>
        </div>
    </div>

    {{-- Actions (hidden on print) --}}
    <div class="flex items-center gap-3 mt-5 print:hidden">
        <button type="button" onclick="window.print()" class="btn-primary">Print Slip</button>
        <a href="{{ route('web.admin.counter') }}" class="btn-secondary">Issue Another</a>
        <a href="{{ route('web.billing.show', $bill->id) }}" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">View Receipt</a>
    </div>
</div>
@endsection
