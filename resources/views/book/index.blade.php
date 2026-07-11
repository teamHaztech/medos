@extends('layouts.public')
@section('title', 'Book Appointment')
@section('brand', $hospital->name)
@section('subtitle', 'Book an appointment online')

@section('content')
<form method="POST" action="{{ route('book.store') }}" x-data="booking()" x-init="init()" class="space-y-5">
    @csrf
    <input type="hidden" name="doctor_id" :value="selectedDoctor?.id || ''">
    <input type="hidden" name="date" :value="selectedDate">
    <input type="hidden" name="time" :value="selectedSlot">
    <input type="hidden" name="payment_option" value="hospital">

    @if(session('error'))
    <div class="rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    {{-- Step indicator --}}
    <div class="flex items-center gap-2 text-xs font-medium">
        <template x-for="(label, i) in ['Doctor','Time','Details']" :key="i">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-full flex items-center justify-center" :class="step >= i+1 ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500'" x-text="i+1"></span>
                <span :class="step >= i+1 ? 'text-slate-700' : 'text-slate-400'" x-text="label"></span>
                <span x-show="i < 2" class="w-6 h-px bg-slate-200"></span>
            </div>
        </template>
    </div>

    {{-- STEP 1 — pick doctor --}}
    <div x-show="step === 1">
        <div x-show="departments.length" class="flex flex-wrap gap-1.5 mb-3">
            <button type="button" @click="deptFilter = ''" :class="deptFilter === '' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1 rounded-full text-xs font-semibold">All</button>
            <template x-for="dep in departments" :key="dep">
                <button type="button" @click="deptFilter = dep" :class="deptFilter === dep ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1 rounded-full text-xs font-semibold" x-text="dep"></button>
            </template>
        </div>
        <p x-show="!doctors.length" class="text-sm text-slate-400 py-6 text-center">Loading doctors…</p>
        <div class="space-y-2">
            <template x-for="d in filteredDoctors" :key="d.id">
                <button type="button" @click="pickDoctor(d)" class="w-full flex items-center justify-between bg-white border border-slate-200 hover:border-blue-300 rounded-xl px-4 py-3 text-left">
                    <div>
                        <p class="text-sm font-semibold text-slate-800" x-text="d.name"></p>
                        <p class="text-xs text-slate-500"><span x-text="d.specialization || 'General'"></span><span x-show="d.department" x-text="' · ' + d.department"></span></p>
                    </div>
                    <span class="text-blue-600 text-sm font-medium">Select →</span>
                </button>
            </template>
        </div>
    </div>

    {{-- STEP 2 — pick date + slot --}}
    <div x-show="step === 2" style="display:none">
        <button type="button" @click="step = 1" class="text-sm text-slate-500 mb-3">← Change doctor</button>
        <div class="bg-white border border-slate-200 rounded-xl p-3 mb-3">
            <p class="text-sm font-semibold text-slate-800" x-text="selectedDoctor?.name"></p>
            <p class="text-xs text-slate-500" x-text="selectedDoctor?.department"></p>
        </div>
        <p x-show="loadingSlots" class="text-sm text-slate-400 py-6 text-center">Loading availability…</p>
        <p x-show="!loadingSlots && !calendar.length" class="text-sm text-slate-400 py-6 text-center">No open slots in the next 14 days.</p>
        <div x-show="calendar.length">
            <div class="flex gap-2 overflow-x-auto pb-2 mb-3">
                <template x-for="(day, di) in calendar" :key="day.date">
                    <button type="button" @click="dayIndex = di" :class="dayIndex === di ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-slate-600 border-slate-200'" class="shrink-0 border rounded-lg px-3 py-2 text-center">
                        <span class="block text-xs font-semibold" x-text="day.day"></span>
                        <span class="block text-sm font-bold" x-text="day.dateFmt"></span>
                        <span class="block text-xs" :class="dayIndex === di ? 'text-blue-100' : 'text-slate-400'" x-text="day.available + ' open'"></span>
                    </button>
                </template>
            </div>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                <template x-for="s in (calendar[dayIndex]?.slots || [])" :key="s.time">
                    <button type="button" :disabled="!s.available" @click="pickSlot(s)"
                        :class="s.available ? 'bg-white border-slate-200 text-slate-700 hover:border-blue-400' : 'bg-slate-100 border-slate-100 text-slate-300 cursor-not-allowed line-through'"
                        class="border rounded-lg py-2 text-sm font-medium" x-text="s.display"></button>
                </template>
            </div>
        </div>
    </div>

    {{-- STEP 3 — details + confirm --}}
    <div x-show="step === 3" style="display:none" class="space-y-4">
        <button type="button" @click="step = 2" class="text-sm text-slate-500">← Change time</button>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm">
            <p class="font-semibold text-slate-800" x-text="selectedDoctor?.name"></p>
            <p class="text-slate-600" x-text="selectedDoctor?.department"></p>
            <p class="text-slate-700 mt-1"><span x-text="prettyDate"></span> · <span class="font-semibold" x-text="prettyTime"></span></p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Full name</label>
                <input type="text" name="name" x-model="name" required maxlength="255" class="input-field" placeholder="Your name">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                <input type="tel" name="phone" x-model="phone" required class="input-field" placeholder="10-digit mobile">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason for visit <span class="text-slate-400 font-normal">(optional)</span></label>
            <input type="text" name="complaint" maxlength="1000" class="input-field" placeholder="e.g. fever, follow-up">
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 flex items-center justify-between">
            <span class="text-sm text-slate-600">Consultation fee</span>
            <span class="text-base font-bold text-slate-800">{{ $currency }}{{ number_format($fee, 2) }}</span>
        </div>
        <div class="rounded-lg border border-slate-200 px-4 py-3 text-sm text-slate-600">
            <span class="font-medium text-slate-800">Payment:</span> Pay at the hospital reception on your visit.
        </div>
        <button type="submit" class="btn-primary w-full py-3" :disabled="!canSubmit || submitting" :class="(!canSubmit || submitting) ? 'opacity-40 cursor-not-allowed' : ''" @click="submitting = true">
            <span x-text="submitting ? 'Booking…' : 'Confirm booking'"></span>
        </button>
    </div>
</form>

@push('scripts')
<script>
function booking() {
    return {
        step: 1,
        doctors: [], departments: [], deptFilter: '',
        selectedDoctor: null,
        calendar: [], dayIndex: 0, loadingSlots: false,
        selectedDate: '', selectedSlot: '', prettyDate: '', prettyTime: '',
        name: '', phone: '', submitting: false,
        get filteredDoctors() { return this.deptFilter ? this.doctors.filter(d => d.department === this.deptFilter) : this.doctors; },
        get canSubmit() { return this.name.trim() && this.phone.trim() && this.selectedDoctor && this.selectedDate && this.selectedSlot; },
        async init() {
            try {
                const r = await fetch('{{ route('book.doctors') }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                this.doctors = r.ok ? await r.json() : [];
                this.departments = [...new Set(this.doctors.map(d => d.department).filter(Boolean))].sort();
            } catch (e) { this.doctors = []; }
        },
        async pickDoctor(d) {
            this.selectedDoctor = d; this.selectedDate = ''; this.selectedSlot = '';
            this.step = 2; this.loadingSlots = true; this.calendar = []; this.dayIndex = 0;
            try {
                const r = await fetch('/book/slots/' + d.id, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const data = r.ok ? await r.json() : { days: [] };
                this.calendar = data.days || [];
                this.dayIndex = Math.max(0, this.calendar.findIndex(day => day.available > 0));
            } catch (e) { this.calendar = []; }
            this.loadingSlots = false;
        },
        pickSlot(s) {
            const day = this.calendar[this.dayIndex];
            this.selectedDate = day.date; this.selectedSlot = s.time;
            this.prettyDate = day.dayFull + ', ' + day.dateFmt; this.prettyTime = s.display;
            this.step = 3;
        },
    };
}
</script>
@endpush
@endsection
