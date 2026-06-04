@extends('layouts.kiosk')

@section('title', 'Book a Lab Test')
@section('subtitle', 'Lab Tests & Scans')

@section('content')
<div x-data="kioskLab()" class="w-full max-w-2xl">

    {{-- STEP 1: identify --}}
    <template x-if="step === 1 && !result">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-slate-800 mb-1 text-center">Book a Lab Test / Scan</h2>
            <p class="text-slate-500 text-center mb-6">No doctor appointment needed</p>
            <label class="block text-sm font-semibold text-slate-600 mb-1">Mobile number</label>
            <input type="tel" x-model="phone" maxlength="13" placeholder="10-digit number" class="input-field text-lg" @keydown.enter="checkPhone()">
            <p x-show="phoneError" x-text="phoneError" class="text-red-500 text-sm mt-1"></p>
            <input x-show="existingPatient === false" type="text" x-model="name" placeholder="Your full name" class="input-field text-lg mt-3">
            <button @click="next1()" :disabled="phoneLoading" class="btn-primary w-full mt-5 py-3 text-lg disabled:opacity-50">
                <span x-show="!phoneLoading">Continue →</span><span x-show="phoneLoading">Checking…</span>
            </button>
            <a href="{{ route('kiosk.index') }}" class="block text-center text-slate-400 hover:text-slate-600 mt-4">← Back</a>
        </div>
    </template>

    {{-- STEP 2: pick tests --}}
    <template x-if="step === 2 && !result">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-slate-800 mb-3">Select tests</h2>
            <div class="flex flex-wrap gap-1.5 mb-3">
                <template x-for="f in ['all','lab','imaging','procedure']" :key="f">
                    <button @click="filter = f" :class="filter === f ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold capitalize" x-text="f === 'all' ? 'All' : f"></button>
                </template>
            </div>
            <div class="max-h-[45vh] overflow-y-auto flex flex-wrap gap-2 content-start">
                <template x-for="t in filteredTests" :key="t.name">
                    <button @click="toggle(t)" :class="picked(t) ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200'" class="px-3 py-2 rounded-lg text-sm font-medium border text-left">
                        <span x-text="t.name"></span> <span class="opacity-70" x-text="'₹' + t.price"></span>
                    </button>
                </template>
            </div>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100">
                <p class="text-sm text-slate-600"><span class="font-semibold" x-text="cart.length"></span> selected · <span class="font-semibold" x-text="'₹' + total()"></span></p>
                <div class="flex gap-2">
                    <button @click="step = 1" class="text-sm text-slate-500 px-3 py-2">← Back</button>
                    <button @click="step = 3" :disabled="!cart.length" class="btn-primary px-5 py-2.5 disabled:opacity-40">Next →</button>
                </div>
            </div>
        </div>
    </template>

    {{-- STEP 3: pick slot --}}
    <template x-if="step === 3 && !result">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h2 class="text-xl font-bold text-slate-800 mb-3">Pick a time</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[45vh] overflow-y-auto">
                <template x-for="s in slots" :key="s.start">
                    <button @click="chosenSlot = s.start; chosenLabel = s.label" :class="chosenSlot === s.start ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700 border-slate-200'" class="px-3 py-2 rounded-lg text-sm font-medium border" x-text="s.label"></button>
                </template>
                <button @click="chosenSlot = ''; chosenLabel = 'Walk-in today'" :class="chosenSlot === '' && chosenLabel ? 'bg-green-600 text-white border-green-600' : 'bg-white text-slate-700 border-slate-200'" class="px-3 py-2 rounded-lg text-sm font-medium border">Walk-in today</button>
            </div>
            <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100">
                <button @click="step = 2" class="text-sm text-slate-500 px-3 py-2">← Back</button>
                <button @click="submit()" :disabled="!chosenLabel || submitting" class="btn-primary px-5 py-2.5 disabled:opacity-40">
                    <span x-show="!submitting">Confirm booking</span><span x-show="submitting">Booking…</span>
                </button>
            </div>
            <p x-show="error" x-text="error" class="text-red-500 text-sm mt-2"></p>
        </div>
    </template>

    {{-- RESULT --}}
    <template x-if="result">
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-1">Booking Confirmed</h2>
            <p class="text-slate-500 mb-4" x-text="result.name"></p>
            <div class="bg-slate-50 rounded-xl p-5 mb-4">
                <p class="text-xs font-bold text-slate-400 uppercase">Your Lab Token</p>
                <p class="text-4xl font-black text-indigo-600 my-1" x-text="result.token"></p>
                <p class="text-sm text-slate-600" x-text="'🗓 ' + result.when"></p>
                <p class="text-sm text-slate-600" x-text="'Total: ₹' + result.total"></p>
            </div>
            <p class="text-sm text-slate-500 mb-5">Show this token at the lab / sample-collection counter.</p>
            <a href="{{ route('kiosk.index') }}" class="btn-primary inline-block px-8 py-3">Done</a>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function kioskLab() {
    return {
        step: 1,
        phone: '', name: '', existingPatient: null, phoneError: null, phoneLoading: false,
        allTests: @json($tests),
        slots: @json($slots),
        filter: 'all', cart: [],
        chosenSlot: '', chosenLabel: '',
        submitting: false, error: null, result: null,

        get filteredTests() { return this.filter === 'all' ? this.allTests : this.allTests.filter(t => t.type === this.filter); },
        picked(t) { return this.cart.some(c => c.name === t.name); },
        toggle(t) { const i = this.cart.findIndex(c => c.name === t.name); i >= 0 ? this.cart.splice(i,1) : this.cart.push({ name: t.name, price: t.price, type: t.type }); },
        total() { return this.cart.reduce((s,t) => s + (parseFloat(t.price)||0), 0); },

        async checkPhone() {
            this.phoneError = null;
            const cleaned = this.phone.replace(/\D/g,'');
            if (cleaned.length < 10) { this.phoneError = 'Enter a 10-digit number'; return false; }
            this.phone = cleaned.slice(-10);
            this.phoneLoading = true;
            try {
                const res = await fetch('{{ route("kiosk.check-phone") }}?phone=' + this.phone, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.exists) { this.name = data.name; this.existingPatient = true; }
                else { this.existingPatient = false; }
            } catch { this.existingPatient = false; }
            this.phoneLoading = false;
            return true;
        },
        async next1() {
            if (this.existingPatient === null) { const ok = await this.checkPhone(); if (!ok) return; }
            if (this.existingPatient === false && !this.name.trim()) { this.phoneError = 'Please enter your name'; return; }
            if (this.phone.replace(/\D/g,'').length < 10) { this.phoneError = 'Enter a 10-digit number'; return; }
            this.step = 2;
        },
        async submit() {
            if (!this.cart.length || !this.chosenLabel) return;
            this.submitting = true; this.error = null;
            try {
                const res = await fetch('{{ route("kiosk.lab.process") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ name: this.name || 'Patient', phone: this.phone, tests: this.cart, scheduled_for: this.chosenSlot || null }),
                });
                const data = await res.json();
                if (data.success) { this.result = data; }
                else { this.error = data.message || 'Booking failed. Please try again.'; }
            } catch(e) { this.error = 'Booking failed. Please try again.'; }
            this.submitting = false;
        },
    };
}
</script>
@endpush
