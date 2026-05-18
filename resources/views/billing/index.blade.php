@extends('layouts.app')

@section('title', 'Billing')
@section('page-title', 'Billing')

@section('content')
<div>
    {{-- Filters --}}
    <form method="GET" action="{{ route('web.billing.index') }}" class="mb-6">
        <div class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1 max-w-xs">
                <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Patient name or phone..." class="input-field pl-10">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                <select name="payment_status" class="input-field">
                    <option value="">All</option>
                    <option value="pending" {{ request('payment_status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>Paid</option>
                    <option value="partial" {{ request('payment_status') === 'partial' ? 'selected' : '' }}>Partial</option>
                    <option value="refunded" {{ request('payment_status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
                    <option value="waived" {{ request('payment_status') === 'waived' ? 'selected' : '' }}>Waived</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="input-field">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary">Filter</button>
                <a href="{{ route('web.billing.index') }}" class="btn-secondary">Clear</a>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Bill #</th>
                        <th class="table-header">Date</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Total</th>
                        <th class="table-header">Paid</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bills as $bill)
                        @php
                            $status = is_object($bill->payment_status) ? $bill->payment_status->value : $bill->payment_status;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ $bill->bill_number }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $bill->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $bill->patient->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-800">{{ \App\Modules\Core\Services\RegionService::formatMoney($bill->total_amount) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ \App\Modules\Core\Services\RegionService::formatMoney($bill->amount_paid ?? 0) }}</td>
                            <td class="px-4 py-3">
                                @if($status === 'paid')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Paid</span>
                                @elseif($status === 'partial')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Partial</span>
                                @elseif($status === 'refunded')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Refunded</span>
                                @elseif($status === 'waived')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">Waived</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('web.billing.show', $bill->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View</a>
                                    <a href="{{ route('web.billing.print', $bill->id) }}" target="_blank" class="text-slate-600 hover:text-slate-800 text-sm font-medium">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                <p class="text-sm">No bills found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bills->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $bills->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
