@extends('layouts.app')

@section('title', 'Bill Detail')
@section('page-title', 'Bill Detail')

@section('content')
@php
    $status = is_object($bill->payment_status) ? $bill->payment_status->value : $bill->payment_status;
    $currency = \App\Modules\Core\Services\RegionService::currency();
    $taxName = \App\Modules\Core\Services\RegionService::taxName();
    $depositBalance = \App\Modules\Billing\Models\PatientDeposit::balanceFor($bill->hospital_id, $bill->patient_id);
    $cancelled = ! is_null($bill->cancelled_at);
@endphp

<div x-data="{
        showPayModal: false,
        editPayOpen: false,
        cancelOpen: false,
        depositOpen: false,
        editPay: { id:'', method:'cash', reference:'', amount:'', paid_at:'' },
        openEditPay(p) { this.editPay = Object.assign({}, p); this.editPayOpen = true; },
        get editPayAction() { return '{{ url('billing/'.$bill->id.'/payments') }}/' + this.editPay.id; }
    }">

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
        @if($status !== 'paid' && !$cancelled)
        <button @click="showPayModal = true" class="btn-primary">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
            Record Payment
        </button>
        @endif
        <button @click="depositOpen = true" class="btn-secondary">Collect Deposit</button>
        @if(!$cancelled)
        <button @click="cancelOpen = true" class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100">Cancel Bill</button>
        @endif
    </div>

    @if($cancelled)
    <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
        <span class="font-semibold">This bill is cancelled.</span> {{ $bill->cancel_reason }} <span class="text-red-500">· {{ optional($bill->cancelled_at)->format('M d, Y g:i A') }}</span>
    </div>
    @endif
    @if($depositBalance > 0)
    <div class="mb-6 px-4 py-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
        Patient advance/deposit balance: <span class="font-bold">{{ $currency }}{{ number_format($depositBalance, 2) }}</span> — can be applied as a payment below.
    </div>
    @endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

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
                                <th class="table-header text-center">Taxable</th>
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
                                <td class="px-4 py-3 text-center">@if($item['taxable'] ?? false)<span class="text-xs font-semibold text-amber-600">✓</span>@else<span class="text-xs text-slate-300">—</span>@endif</td>
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
                        <span class="text-slate-500">{{ $taxName }} <span class="text-xs text-slate-400">({{ number_format($bill->tax_rate ?? 0, ($bill->tax_rate == (int) $bill->tax_rate ? 0 : 2)) }}% on taxable)</span></span>
                        <span class="text-slate-700">{{ $currency }}{{ number_format($bill->tax_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Discount<span class="text-xs text-slate-400">{{ $bill->discount_reason ? ' · '.$bill->discount_reason : '' }}</span></span>
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
                        <span class="text-slate-500">Patient Payable</span>
                        <span class="font-semibold text-slate-800">{{ $currency }}{{ number_format($bill->patient_payable ?? $bill->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Amount Paid</span>
                        <span class="font-medium text-green-700">{{ $currency }}{{ number_format($bill->amount_paid ?? 0, 2) }}</span>
                    </div>
                    @if(($bill->deposit_applied ?? 0) > 0)
                    <div class="flex justify-between">
                        <span class="text-slate-400 text-xs pl-3">↳ of which from deposit</span>
                        <span class="text-xs text-emerald-600">{{ $currency }}{{ number_format($bill->deposit_applied, 2) }}</span>
                    </div>
                    @endif
                    @php $refundedTotal = round((float) abs($bill->payments->where('amount', '<', 0)->sum('amount')), 2); @endphp
                    @if($refundedTotal > 0)
                    <div class="flex justify-between">
                        <span class="text-slate-500">Refunded</span>
                        <span class="font-medium text-purple-600">-{{ $currency }}{{ number_format($refundedTotal, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between border-t border-slate-200 pt-3">
                        <span class="font-semibold text-slate-800">Balance Due</span>
                        <span class="font-bold {{ ($bill->balance_due ?? 0) > 0 ? 'text-red-600' : 'text-slate-700' }}">{{ $currency }}{{ number_format($bill->balance_due ?? 0, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Payment history / ledger --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Payments</h3>
                    @if($status === 'paid')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Paid</span>
                    @elseif($status === 'partial')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Partial</span>
                    @elseif($status === 'refunded')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Refunded</span>
                    @elseif($status === 'cancelled')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-200 text-slate-700">Cancelled</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pending</span>
                    @endif
                </div>
                <div class="space-y-2">
                    @forelse($bill->payments as $p)
                        @php $isRefund = (float) $p->amount < 0; @endphp
                        <div class="flex items-start justify-between py-2 border-b border-slate-50 last:border-0">
                            <div>
                                <p class="text-sm font-semibold {{ $isRefund ? 'text-purple-600' : 'text-slate-800' }}">{{ $isRefund ? '− ' : '' }}{{ $currency }}{{ number_format(abs($p->amount), 2) }}
                                    <span class="text-xs font-normal text-slate-500 ml-1">{{ $isRefund ? 'Refund' : $p->methodLabel() }}</span>
                                </p>
                                <p class="text-xs text-slate-400">{{ optional($p->paid_at)->format('M d, Y h:i A') }}{{ $p->reference ? ' · ' . $p->reference : '' }}{{ $p->received_by_name ? ' · by ' . $p->received_by_name : '' }}</p>
                            </div>
                            <div class="flex items-center gap-2 pt-0.5">
                                @if(!$isRefund && !$cancelled)
                                <button type="button" @click="openEditPay({ id:'{{ $p->id }}', method: @js($p->method), reference: @js($p->reference ?? ''), amount: '{{ $p->amount }}', paid_at: '{{ optional($p->paid_at)->format('Y-m-d\TH:i') }}' })" class="text-xs text-blue-600 hover:text-blue-800">Edit</button>
                                <form method="POST" action="{{ route('web.billing.payments.refund', [$bill->id, $p->id]) }}" onsubmit="return confirm('Refund this payment?')">
                                    @csrf
                                    <button class="text-xs text-purple-600 hover:text-purple-800">Refund</button>
                                </form>
                                @endif
                                <form method="POST" action="{{ route('web.billing.payments.delete', [$bill->id, $p->id]) }}" onsubmit="return confirm('Remove this ledger entry?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 text-center py-4">No payments recorded yet.</p>
                    @endforelse
                </div>
                @if($status !== 'paid')
                    <button @click="showPayModal = true" class="w-full mt-3 btn-primary justify-center">+ Record Payment</button>
                @endif
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
    <x-modal show="showPayModal" title="Record Payment" max="md">
            <p class="text-sm text-slate-500 mb-3">Balance due: <span class="font-bold text-slate-800">{{ $currency }}{{ number_format($bill->balance_due ?? $bill->total_amount, 2) }}</span> — you can record a partial amount.</p>
            <form method="POST" action="{{ route('web.billing.pay', $bill->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                    <select name="method" required class="input-field">
                        @foreach(\App\Modules\Billing\Models\BillPayment::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                        @if($depositBalance > 0)<option value="deposit">From Deposit (bal {{ $currency }}{{ number_format($depositBalance, 2) }})</option>@endif
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
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <input type="text" name="notes" class="input-field" placeholder="Optional">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showPayModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Confirm Payment</button>
                </div>
            </form>
    </x-modal>

    {{-- Edit Payment Modal --}}
    <x-modal show="editPayOpen" title="Edit Payment" max="md">
            <form method="POST" :action="editPayAction" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Payment Method</label>
                    <select name="method" x-model="editPay.method" required class="input-field">
                        @foreach(\App\Modules\Billing\Models\BillPayment::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reference</label>
                    <input type="text" name="reference" x-model="editPay.reference" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount ({{ $currency }})</label>
                    <input type="number" name="amount_paid" x-model="editPay.amount" step="0.01" min="0.01" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Paid At</label>
                    <input type="datetime-local" name="paid_at" x-model="editPay.paid_at" class="input-field">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="editPayOpen = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
    </x-modal>

    {{-- Cancel Bill Modal --}}
    <x-modal show="cancelOpen" title="Cancel Bill" max="md">
        <form method="POST" action="{{ route('web.billing.cancel', $bill->id) }}" class="space-y-4">
            @csrf
            <p class="text-sm text-slate-600">Cancel bill <strong>{{ $bill->bill_number }}</strong>? Recorded payments stay on the ledger — refund them separately if money was collected.</p>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                <input type="text" name="cancel_reason" class="input-field" placeholder="e.g. Duplicate bill, entered in error">
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" @click="cancelOpen = false" class="btn-secondary">Keep Bill</button>
                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white bg-red-600 hover:bg-red-700">Cancel Bill</button>
            </div>
        </form>
    </x-modal>

    {{-- Collect Deposit Modal --}}
    <x-modal show="depositOpen" title="Collect Advance / Deposit" max="md">
        <form method="POST" action="{{ route('web.billing.deposit') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $bill->patient_id }}">
            <p class="text-sm text-slate-500">Current balance: <span class="font-bold text-slate-800">{{ $currency }}{{ number_format($depositBalance, 2) }}</span></p>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Amount ({{ $currency }})</label>
                <input type="number" name="amount" step="0.01" min="0.01" required class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Method</label>
                <select name="method" required class="input-field"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank">Bank Transfer</option><option value="cheque">Cheque</option></select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                <input type="text" name="notes" class="input-field" placeholder="Optional">
            </div>
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" @click="depositOpen = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Collect Deposit</button>
            </div>
        </form>
    </x-modal>
</div>
@endsection
