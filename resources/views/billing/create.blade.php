@extends('layouts.app')

@section('title', 'Generate Bill')
@section('page-title', 'Generate Bill')

@section('content')
@php
    $currency = \App\Modules\Core\Services\RegionService::currency();
    $taxNameLabel = \App\Modules\Core\Services\RegionService::taxName();
@endphp

<div x-data="billForm()" class="max-w-4xl">

    {{-- Encounter Info --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-3">Encounter Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <span class="text-slate-500">Patient</span>
                <p class="font-medium text-slate-800">{{ $encounter->patient->name ?? '' }}</p>
            </div>
            <div>
                <span class="text-slate-500">Doctor</span>
                <p class="font-medium text-slate-800">{{ $encounter->doctor->name ?? '' }}</p>
            </div>
            <div>
                <span class="text-slate-500">Encounter #</span>
                <p class="font-medium text-slate-800">{{ $encounter->encounter_number }}</p>
            </div>
        </div>
    </div>

    {{-- Bill Form --}}
    <form method="POST" action="{{ route('web.billing.store') }}">
        @csrf
        <input type="hidden" name="encounter_id" value="{{ $encounter->id }}">

        {{-- Line Items --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Line Items</h3>
                <button type="button" @click="addItem()" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">+ Add Item</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="table-header">Description</th>
                            <th class="table-header text-center w-20">Qty</th>
                            <th class="table-header text-right w-28">Rate</th>
                            <th class="table-header text-right w-28">Amount</th>
                            <th class="table-header w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="index">
                            <tr>
                                <td class="px-4 py-2">
                                    <input type="text" x-model="item.description" :name="'line_items['+index+'][description]'" class="input-field text-sm" required>
                                    <input type="hidden" x-model="item.category" :name="'line_items['+index+'][category]'">
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" x-model.number="item.quantity" :name="'line_items['+index+'][quantity]'" min="1" class="input-field text-sm text-center" @input="calcItem(index)" required>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" x-model.number="item.unit_price" :name="'line_items['+index+'][unit_price]'" step="0.01" min="0" class="input-field text-sm text-right" @input="calcItem(index)" required>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="block text-sm font-medium text-slate-800 text-right" x-text="'{{ $currency }}' + item.total.toFixed(2)"></span>
                                    <input type="hidden" x-model="item.total" :name="'line_items['+index+'][total]'">
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600" title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Totals --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
            <div class="max-w-sm ml-auto space-y-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500">Subtotal</span>
                    <span class="font-medium text-slate-800" x-text="'{{ $currency }}' + subtotal.toFixed(2)"></span>
                    <input type="hidden" name="subtotal" :value="subtotal.toFixed(2)">
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <label class="text-slate-500 whitespace-nowrap">{{ $taxNameLabel }} (%)</label>
                    <input type="number" x-model.number="taxPercent" step="0.1" min="0" max="100" class="input-field w-20 text-sm text-right" @input="recalc()">
                    <span class="font-medium text-slate-700 whitespace-nowrap" x-text="'{{ $currency }}' + taxAmount.toFixed(2)"></span>
                    <input type="hidden" name="tax_amount" :value="taxAmount.toFixed(2)">
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <label class="text-slate-500 whitespace-nowrap">Discount ({{ $currency }})</label>
                    <input type="number" x-model.number="discount" step="0.01" min="0" class="input-field w-28 text-sm text-right" @input="recalc()">
                    <input type="hidden" name="discount_amount" :value="discount.toFixed(2)">
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <label class="text-slate-500 whitespace-nowrap">Insurance ({{ $currency }})</label>
                    <input type="number" x-model.number="insurance" step="0.01" min="0" class="input-field w-28 text-sm text-right" @input="recalc()">
                    <input type="hidden" name="insurance_covered" :value="insurance.toFixed(2)">
                </div>
                <div class="border-t border-slate-200 pt-3 flex justify-between text-base">
                    <span class="font-semibold text-slate-800">Total Payable</span>
                    <span class="font-bold text-slate-900" x-text="'{{ $currency }}' + total.toFixed(2)"></span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('web.billing.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">Generate Bill</button>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
function billForm() {
    return {
        items: @json($lineItems),
        taxPercent: {{ $taxRate }},
        discount: 0,
        insurance: 0,
        subtotal: 0,
        taxAmount: 0,
        total: 0,

        init() {
            this.recalc();
        },

        addItem() {
            this.items.push({ description: '', quantity: 1, unit_price: 0, total: 0, category: 'other' });
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.recalc();
        },

        calcItem(index) {
            let item = this.items[index];
            item.total = (item.quantity || 0) * (item.unit_price || 0);
            this.recalc();
        },

        recalc() {
            this.subtotal = this.items.reduce((sum, i) => sum + (parseFloat(i.total) || 0), 0);
            this.taxAmount = Math.round(this.subtotal * this.taxPercent) / 100;
            this.total = this.subtotal + this.taxAmount - (this.discount || 0) - (this.insurance || 0);
            if (this.total < 0) this.total = 0;
        }
    };
}
</script>
@endpush
