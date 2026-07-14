@extends('layouts.app')
@section('title', 'Payment & Token')
@section('page-title', 'Payment & Token Counter')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<div class="max-w-3xl" x-data="counter()">
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('web.admin.counter.issue') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="patient_id" :value="mode==='existing' && selectedPatient ? selectedPatient.id : ''">
        <input type="hidden" name="doctor_id" :value="doctorId">

        {{-- 1. Patient --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700">1. Patient</h3>
                <div class="flex gap-1 text-xs">
                    <button type="button" @click="mode='existing'" :class="mode==='existing'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">Existing</button>
                    <button type="button" @click="mode='new'" :class="mode==='new'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">New patient</button>
                </div>
            </div>
            <div x-show="mode==='existing'" class="relative">
                <div x-show="selectedPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <span class="text-sm font-medium text-slate-800"><span x-text="selectedPatient?.name"></span> <span class="text-slate-400" x-text="selectedPatient?.phone ? '· '+selectedPatient.phone : ''"></span></span>
                    <button type="button" @click="selectedPatient=null" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
                </div>
                <div x-show="!selectedPatient">
                    <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" @keydown.enter.prevent="searchPatients()" class="input-field" placeholder="Search patient by name or phone…" autocomplete="off">
                    <p x-show="searching" style="display:none" class="text-xs text-slate-400 mt-1">Searching…</p>
                    <div x-show="patientResults.length" class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl overflow-y-auto" style="display:none; max-height:220px">
                        <template x-for="p in patientResults" :key="p.id">
                            <button type="button" @click="pickPatient(p)" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                <span class="text-sm font-medium text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span>
                            </button>
                        </template>
                    </div>
                    <p x-show="patientSearch.length >= 2 && !searching && !patientResults.length" style="display:none" class="text-xs text-slate-400 mt-1">No match — use "New patient" to register.</p>
                </div>
            </div>
            <div x-show="mode==='new'" style="display:none" class="grid grid-cols-2 gap-3">
                <input type="text" name="new_name" x-model="newName" class="input-field" placeholder="Full name">
                <input type="text" name="new_phone" x-model="newPhone" class="input-field" placeholder="Phone (10-digit)">
            </div>
        </div>

        {{-- 2. Doctor (optional) --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">2. Doctor <span class="text-slate-400 font-normal">(optional — issues a queue token &amp; checks the patient in)</span></h3>
            <select x-model="doctorId" @change="onDoctor()" class="input-field">
                <option value="">No doctor — service payment only</option>
                @foreach($doctors as $d)<option value="{{ $d->id }}" data-name="{{ $d->name }}">{{ $d->name }}{{ $d->department ? ' · '.$d->department : '' }}</option>@endforeach
            </select>
        </div>

        {{-- 3. Charges --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">3. Charges</h3>
            <div class="relative mb-3">
                <input type="text" x-model="svcQuery" class="input-field" placeholder="Add fee/service from master — e.g. Consultation…">
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
            <div class="space-y-2">
                <template x-for="(it, i) in items" :key="i">
                    <div class="flex items-center justify-between gap-3 px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg">
                        <span class="text-sm font-medium text-slate-800 flex-1 min-w-0 truncate" x-text="it.description"></span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-400">{{ $cur }}</span>
                            <input type="number" step="0.01" min="0" x-model.number="it.price" class="input-field w-24 text-sm py-1 text-right">
                            <button type="button" @click="items.splice(i,1)" class="text-red-400 hover:text-red-600 px-1 text-lg leading-none">&times;</button>
                        </div>
                        <input type="hidden" :name="'items['+i+'][description]'" :value="it.description">
                        <input type="hidden" :name="'items['+i+'][price]'" :value="it.price">
                        <input type="hidden" :name="'items['+i+'][quantity]'" value="1">
                    </div>
                </template>
                <p x-show="!items.length" class="text-sm text-slate-400 py-2">No charges yet — pick a doctor (auto-adds the consultation fee) or search a service above.</p>
            </div>
        </div>

        {{-- 4. Payment --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">4. Payment</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Method</label>
                    <select name="method" class="input-field"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank">Bank Transfer</option><option value="cheque">Cheque</option></select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reference (optional)</label>
                    <input type="text" name="reference" class="input-field">
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between bg-slate-50 rounded-lg px-4 py-3">
                <span class="text-sm font-semibold text-slate-700">Total to collect</span>
                <span class="text-xl font-bold text-blue-700">{{ $cur }}<span x-text="total.toFixed(2)"></span></span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-6 py-2.5" :disabled="!canIssue" :class="!canIssue ? 'opacity-40 cursor-not-allowed' : ''">Collect &amp; Issue Token</button>
            <a href="{{ route('web.admin.queue') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function counter() {
    return {
        mode: 'existing',
        patientSearch: '', patientResults: [], selectedPatient: null, searching: false,
        newName: '', newPhone: '',
        doctorId: '',
        allServices: @js($services),
        svcQuery: '', items: [],

        get filteredServices() {
            const q = this.svcQuery.toLowerCase().trim();
            if (!q) return [];
            return this.allServices.filter(s => s.name.toLowerCase().includes(q)).slice(0, 12);
        },
        get total() { return this.items.reduce((a, it) => a + (Number(it.price)||0), 0); },
        get canIssue() {
            const hasPatient = this.mode === 'existing' ? !!this.selectedPatient : (this.newName.trim() && this.newPhone.trim());
            return hasPatient && this.total > 0;
        },
        async searchPatients() {
            if (this.patientSearch.trim().length < 2) { this.patientResults = []; this.searching = false; return; }
            this.searching = true;
            try {
                const r = await fetch('/ajax/patients?q=' + encodeURIComponent(this.patientSearch.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                this.patientResults = r.ok ? (await r.json()) : [];
            } catch (e) { this.patientResults = []; }
            this.searching = false;
        },
        pickPatient(p) { this.selectedPatient = p; this.patientResults = []; this.patientSearch = ''; },
        onDoctor() {
            // Auto-suggest a consultation charge when a doctor is picked and none added yet.
            if (this.doctorId && !this.items.length) {
                const c = this.allServices.find(s => s.category === 'consultation');
                if (c) this.items.push({ description: c.name, price: Number(c.price)||0 });
            }
        },
        addService(s) { this.items.push({ description: s.name, price: Number(s.price)||0 }); this.svcQuery = ''; },
        addCustom() { this.items.push({ description: this.svcQuery, price: 0 }); this.svcQuery = ''; },
    };
}
</script>
@endpush
@endsection
