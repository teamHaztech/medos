@extends('layouts.app')

@section('title', 'Bill Detail')
@section('page-title', 'Bill Detail')

@section('content')
@php
    $status = is_object($bill->payment_status) ? $bill->payment_status->value : $bill->payment_status;
    $currency = \App\Modules\Core\Services\RegionService::currency();
    $taxName = \App\Modules\Core\Services\RegionService::taxName();
@endphp

<div x-data="{ showPayModal: false }">

    {{-- Action buttons --}}
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ route('web.billing.index') }}" class="btn-secondary">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>
        <a href="{{ route('web.billing.print', $bill->id) }}" target="_blank" class="btn-secondary">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
            Print Receipt
        </a>
        @if($bill->encounter_id)
        <a href="{{ route('prescription.print', $bill->encounter_id) }}" target="_blank" class="btn-secondary">Print Prescription</a>
        <a href="{{ route('discharge.summary', $bill->encounter_id) }}" target="_blank" class="btn-secondary">Discharge Summary</a>
        @endif
        @if($status !== 'paid')
        <button @click="showPayModal = true" class="btn-primary">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            Record Payment
        </button>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Bill info --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Patient info card --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Patient Information</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-slate-500">Name</span>
                        <p class="font-medium text-slate-800">{{ $bill->patient->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-slate-500">Phone</span>
                        <p class="font-medium text-slate-800">{{ $bill->patient->phone ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-slate-500">Encounter</span>
                        <p class="font-medium text-slate-800">{{ $bill->encounter->encounter_number ?? '-' }}</p>
                    </div>
                </div>
            </div>

            {{-- Line items table --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Line Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="table-header">Description</th>
                                <th class="table-header text-center">Qty</th>
                                <th class="table-header text-right">Rate</th>
                                <th class="table-header text-right">Amount</th>
                                <th class="table-header">Category</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($bill->line_items ?? [] as $item)
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-800">{{ $item['description'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 text-center">{{ $item['quantity'] ?? 1 }}</td>
                                <td class="px-4 py-3 text-sm text-slate-600 text-right">{{ $currency }}{{ number_format($item['unit_price'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-800 text-right">{{ $currency }}{{ number_format($item['total'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 capitalize">{{ $item['category'] ?? '-' }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Right: Totals + Payment --}}
        <div class="space-y-6">
            {{-- Totals --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Summary</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Subtotal</span>
                        <span class="font-medium text-slate-800">{{ $currency }}{{ number_format($bill->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">{{ $taxName }}</span>
                        <span class="text-slate-700">{{ $currency }}{{ number_format($bill->tax_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Discount</span>
                        <span class="text-green-600">-{{ $currency }}{{ number_format($bill->discount_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Insurance Covered</span>
                        <span class="text-green-600">-{{ $currency }}{{ number_format($bill->insurance_covered, 2) }}</span>
                    </div>
                    <div class="border-t border-slate-200 pt-3 flex justify-between">
                        <span class="font-semibold text-slate-800">Total</span>
                        <span class="font-bold text-lg text-slate-900">{{ $currency }}{{ number_format($bill->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Amount Paid</span>
                        <span class="font-medium text-green-700">{{ $currency }}{{ number_format($bill->amount_paid ?? 0, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Balance Due</span>
                        <span class="font-medium {{ ($bill->balance_due ?? 0) > 0 ? 'text-red-600' : 'text-slate-700' }}">{{ $currency }}{{ number_format($bill->balance_due ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Payment info --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Payment</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Status</span>
                        @if($status === 'paid')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Paid</span>
                        @elseif($status === 'partial')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Partial</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pending</span>
                        @endif
                    </div>
                    @if($bill->payment_method)
                    <div class="flex justify-between">
                        <span class="text-slate-500">Method</span>
                        <span class="text-slate-800 capitalize">{{ $bill->payment_method }}</span>
                    </div>
                    @endif
                    @if($bill->payment_reference)
                    <div class="flex justify-between">
                        <span class="text-slate-500">Reference</span>
                        <span class="text-slate-800">{{ $bill->payment_reference }}</span>
                    </div>
                    @endif
                    @if($bill->paid_at)
                    <div class="flex justify-between">
                        <span class="text-slate-500">Paid At</span>
                        <span class="text-slate-800">{{ $bill->paid_at->format('M d, Y h:i A') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Bill metadata --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4">Bill Info</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Bill #</span>
                        <span class="font-mono text-slate-800">{{ $bill->bill_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Issued</span>
                        <span class="text-slate-800">{{ $bill->issued_at ? $bill->issued_at->format('M d, Y') : $bill->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Record Payment Modal --}}
    <div x-show="showPayModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/50" @click="showPayModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Record Payment</h3>
                <button @click="showPayModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('web.billing.pay', $bill->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                    <select name="method" required class="input-field">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="insurance">Insurance</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reference / Transaction ID</label>
                    <input type="text" name="reference" class="input-field" placeholder="Optional">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount ({{ $currency }})</label>
                    <input type="number" name="amount_paid" step="0.01" min="0.01" value="{{ number_format($bill->balance_due ?? $bill->total_amount, 2, '.', '') }}" required class="input-field">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showPayModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
