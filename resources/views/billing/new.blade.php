@extends('layouts.app')
@section('title', 'New Bill')
@section('page-title', 'Billing')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<div class="max-w-4xl" x-data="billBuilder()">
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.billing.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Bills</a>
        <a href="{{ route('web.billing.services') }}" class="ml-auto text-sm text-blue-600 hover:text-blue-700">Manage service master →</a>
    </div>

    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('web.billing.bill.store') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="patient_id" :value="patient ? patient.id : ''">
        <input type="hidden" name="encounter_id" value="{{ $encounterId }}">

        {{-- Patient --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">Patient</h3>
            <template x-if="patient">
                <div class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="text-sm"><span class="font-medium text-slate-800" x-text="patient.name"></span> <span class="text-slate-400" x-text="patient.phone ? '· '+patient.phone : ''"></span>
                        <span x-show="depositBalance > 0" class="ml-2 text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700" x-text="'Deposit: {{ $cur }}' + depositBalance.toFixed(2)"></span>
                    </div>
                    @if(!$encounterId)<button type="button" @click="patient=null; depositBalance=0" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>@endif
                </div>
            </template>
            <template x-if="!patient">
                <div class="relative">
                    <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search patient by name or phone…">
                    <div x-show="patientResults.length" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl overflow-y-auto" style="max-height:220px">
                        <template x-for="p in patientResults" :key="p.id">
                            <button type="button" @click="pickPatient(p)" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                <span class="text-sm font-medium text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Line items --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700">Charges</h3>
            </div>

            {{-- Service picker --}}
            <div class="relative mb-3">
                <input type="text" x-model="svcQuery" class="input-field" placeholder="Add from service master — type to search…">
                <div x-show="svcQuery.length" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl overflow-y-auto" style="max-height:220px">
                    <template x-for="s in filteredServices" :key="s.id">
                        <button type="button" @click="addService(s)" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                            <span class="text-sm text-slate-800"><span x-text="s.name"></span> <span class="text-xs text-slate-400" x-text="s.category"></span></span>
                            <span class="text-xs font-medium text-slate-600" x-text="'{{ $cur }}'+Number(s.price).toFixed(2)"></span>
                        </button>
                    </template>
                    <button type="button" @click="addCustom()" class="w-full p-2.5 text-left text-sm text-blue-600 hover:bg-blue-50">+ Add “<span x-text="svcQuery"></span>” as a custom charge</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs text-slate-400 uppercase border-b border-slate-200"><tr>
                        <th class="text-left py-2">Description</th><th class="text-right w-20">Qty</th><th class="text-right w-28">Rate</th><th class="text-center w-16">Tax</th><th class="text-right w-28">Amount</th><th class="w-8"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(it, i) in items" :key="i">
                            <tr>
                                <td class="py-1.5 pr-2"><input type="text" x-model="it.description" :name="'items['+i+'][description]'" required class="input-field text-sm py-1.5"></td>
                                <td class="py-1.5"><input type="number" step="0.01" min="0.01" x-model.number="it.quantity" :name="'items['+i+'][quantity]'" class="input-field text-sm py-1.5 text-right"></td>
                                <td class="py-1.5"><input type="number" step="0.01" min="0" x-model.number="it.unit_price" :name="'items['+i+'][unit_price]'" class="input-field text-sm py-1.5 text-right"></td>
                                <td class="py-1.5 text-center"><input type="checkbox" x-model="it.taxable" class="rounded border-slate-300 text-blue-600"><input type="hidden" :name="'items['+i+'][taxable]'" :value="it.taxable ? 1 : 0"><input type="hidden" :name="'items['+i+'][category]'" :value="it.category || ''"></td>
                                <td class="py-1.5 text-right font-medium text-slate-700" x-text="'{{ $cur }}'+(it.quantity*it.unit_price||0).toFixed(2)"></td>
                                <td class="py-1.5 text-right"><button type="button" @click="items.splice(i,1)" class="text-red-400 hover:text-red-600">&times;</button></td>
                            </tr>
                        </template>
                        <tr x-show="!items.length"><td colspan="6" class="py-6 text-center text-slate-400">No charges yet — add from the service master above.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Totals --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Discount ({{ $cur }})</label>
                    <input type="number" step="0.01" min="0" name="discount_amount" x-model.number="discount" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Discount reason</label>
                    <input type="text" name="discount_reason" x-model="discountReason" class="input-field" placeholder="e.g. Staff concession">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tax / GST rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" x-model.number="taxRate" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Insurance covered ({{ $cur }})</label>
                    <input type="number" step="0.01" min="0" name="insurance_covered" x-model.number="insurance" class="input-field">
                </div>
            </div>
            <div class="bg-slate-50 rounded-lg p-4 space-y-2 text-sm self-start">
                <div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-medium">{{ $cur }}<span x-text="subtotal.toFixed(2)"></span></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Tax (<span x-text="taxRate||0"></span>% on taxable)</span><span class="font-medium">{{ $cur }}<span x-text="taxAmount.toFixed(2)"></span></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Discount</span><span class="font-medium text-red-600">− {{ $cur }}<span x-text="Number(discount||0).toFixed(2)"></span></span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2"><span class="font-semibold text-slate-700">Total</span><span class="font-bold text-slate-900">{{ $cur }}<span x-text="total.toFixed(2)"></span></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Insurance covered</span><span class="font-medium">− {{ $cur }}<span x-text="Number(insurance||0).toFixed(2)"></span></span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2"><span class="font-semibold text-slate-700">Patient payable</span><span class="font-bold text-blue-700 text-base">{{ $cur }}<span x-text="payable.toFixed(2)"></span></span></div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-6" :disabled="!canSave" :class="!canSave ? 'opacity-40 cursor-not-allowed' : ''">Create Bill</button>
            <a href="{{ route('web.billing.index') }}" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function billBuilder() {
    return {
        patient: @js($prefillPatient ? ['id'=>$prefillPatient->id,'name'=>$prefillPatient->name,'phone'=>$prefillPatient->phone] : null),
        depositBalance: {{ (float) $depositBalance }},
        patientSearch: '', patientResults: [],
        allServices: @js($services),
        svcQuery: '',
        items: [],
        discount: 0, discountReason: '', taxRate: {{ (float) $taxRate }}, insurance: 0,

        get filteredServices() {
            const q = this.svcQuery.toLowerCase().trim();
            if (!q) return [];
            return this.allServices.filter(s => (s.name + ' ' + (s.code||'')).toLowerCase().includes(q)).slice(0, 12);
        },
        get subtotal() { return this.items.reduce((a, it) => a + (Number(it.quantity)||0) * (Number(it.unit_price)||0), 0); },
        get taxableBase() { return this.items.filter(it => it.taxable).reduce((a, it) => a + (Number(it.quantity)||0) * (Number(it.unit_price)||0), 0); },
        get taxAmount() { return this.taxableBase * (Number(this.taxRate)||0) / 100; },
        get total() { return Math.max(0, this.subtotal + this.taxAmount - (Number(this.discount)||0)); },
        get payable() { return Math.max(0, this.total - (Number(this.insurance)||0)); },
        get canSave() { return this.patient && this.items.length > 0; },

        async searchPatients() {
            if (this.patientSearch.length < 2) { this.patientResults = []; return; }
            try { const r = await fetch('/ajax/patients?q='+encodeURIComponent(this.patientSearch), {headers:{'Accept':'application/json'}}); if (r.ok) this.patientResults = await r.json(); } catch(e){}
        },
        async pickPatient(p) {
            this.patient = p; this.patientResults = []; this.patientSearch = '';
            this.depositBalance = 0;
        },
        addService(s) {
            this.items.push({ description: s.name, category: s.category, quantity: 1, unit_price: Number(s.price)||0, taxable: !!s.is_taxable });
            this.svcQuery = '';
        },
        addCustom() {
            this.items.push({ description: this.svcQuery, category: 'other', quantity: 1, unit_price: 0, taxable: false });
            this.svcQuery = '';
        },
    };
}
</script>
@endpush
@endsection
