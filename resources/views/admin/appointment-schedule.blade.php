@extends('layouts.app')
@section('title', 'Book Appointment')
@section('page-title', 'Book Appointment')

@section('content')
<div class="max-w-3xl" x-data="scheduler()">
    <a href="{{ route('web.admin.appointments') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to appointments</a>

    @if(session('error'))<div class="my-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('web.admin.appointments.store') }}" class="mt-3 space-y-6">
        @csrf

        {{-- Patient --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700">1. Patient</h3>
                <div class="flex gap-1 text-xs">
                    <button type="button" @click="mode='existing'" :class="mode==='existing'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">Existing</button>
                    <button type="button" @click="mode='new'" :class="mode==='new'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">New patient</button>
                </div>
            </div>

            {{-- Existing patient search --}}
            <div x-show="mode==='existing'" class="relative">
                <template x-if="selectedPatient">
                    <div class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                        <span class="text-sm font-medium text-slate-800"><span x-text="selectedPatient.name"></span> <span class="text-slate-400" x-text="selectedPatient.phone ? '· '+selectedPatient.phone : ''"></span></span>
                        <button type="button" @click="selectedPatient=null; upcoming=[]" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
                    </div>
                </template>
                <template x-if="!selectedPatient">
                    <div>
                        <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search patient by name or phone...">
                        <div x-show="patientResults.length" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl overflow-y-auto" style="max-height:220px">
                            <template x-for="p in patientResults" :key="p.id">
                                <button type="button" @click="pickPatient(p)" class="w-full flex items-center justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0">
                                    <span class="text-sm font-medium text-slate-800" x-text="p.name"></span>
                                    <span class="text-xs text-slate-400" x-text="p.phone"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <input type="hidden" name="patient_id" :value="selectedPatient ? selectedPatient.id : ''">
            </div>

            {{-- New patient --}}
            <div x-show="mode==='new'" style="display:none" class="grid grid-cols-2 gap-3">
                <input type="text" name="new_name" x-model="newName" class="input-field" placeholder="Full name">
                <input type="text" name="new_phone" x-model="newPhone" class="input-field" placeholder="Phone (10-digit)">
            </div>
        </div>

        {{-- Duplicate / existing-appointment warning --}}
        <div x-show="upcoming.length" style="display:none" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm font-semibold text-amber-800">⚠ This patient already has <span x-text="upcoming.length"></span> upcoming appointment(s):</p>
            <ul class="mt-1 text-xs text-amber-700 space-y-0.5">
                <template x-for="u in upcoming" :key="u.token">
                    <li>• <span x-text="u.doctor"></span> — <span x-text="u.when"></span> <span class="text-amber-500" x-text="u.token ? '(Token '+u.token+')' : ''"></span></li>
                </template>
            </ul>
        </div>

        {{-- Doctor + timing --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">2. Doctor &amp; time</h3>
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Doctor *</label>
                    <select name="doctor_id" x-model="doctorId" @change="loadSlots()" required class="input-field">
                        <option value="">Select doctor…</option>
                        @foreach($doctors as $d)<option value="{{ $d->id }}" @selected($prefillDoctor === $d->id)>{{ $d->name }}{{ $d->department ? ' · '.$d->department : '' }}</option>@endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="walk_in_now" value="1" x-model="walkNow" class="rounded border-slate-300 text-blue-600">
                        Walk-in now (check in immediately)
                    </label>
                </div>
            </div>

            {{-- Availability calendar --}}
            <div x-show="!walkNow && doctorId" style="display:none">
                <div x-show="loading" class="text-sm text-slate-400 py-4">Loading availability…</div>
                <template x-if="!loading && days.length">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs text-slate-400">Pick a day, then a slot</p>
                            <button type="button" @click="nextAvailable()" class="text-xs font-semibold text-blue-600 hover:text-blue-700">⚡ Next available</button>
                        </div>
                        <div class="flex gap-1 overflow-x-auto pb-2">
                            <template x-for="day in days" :key="day.date">
                                <button type="button" @click="selectedDay = day; selectedSlot = null"
                                    :class="selectedDay?.date===day.date ? 'bg-blue-500 text-white' : (day.available>0 ? 'bg-white border border-slate-200 text-slate-700 hover:bg-blue-50' : 'bg-slate-100 text-slate-400')"
                                    class="flex flex-col items-center px-3 py-2 rounded-lg text-center flex-shrink-0" style="min-width:60px">
                                    <span class="font-semibold" style="font-size:10px" x-text="day.day"></span>
                                    <span class="text-sm font-bold" x-text="day.dateFmt.split(' ')[1]"></span>
                                    <span style="font-size:10px" :class="day.available>0?'text-green-600':'text-red-400'" x-text="day.available+' free'"></span>
                                </button>
                            </template>
                        </div>
                        <template x-if="selectedDay">
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="slot in selectedDay.slots" :key="slot.time">
                                    <button type="button" :disabled="!slot.available"
                                        @click="selectedSlot = {date: selectedDay.date, time: slot.time, display: slot.display}"
                                        :class="selectedSlot && selectedSlot.date===selectedDay.date && selectedSlot.time===slot.time ? 'bg-green-500 text-white ring-2 ring-green-300' : (slot.available ? 'bg-white border border-slate-200 text-slate-700 hover:bg-green-50' : 'bg-slate-100 text-slate-300 line-through cursor-not-allowed')"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium" x-text="slot.display"></button>
                                </template>
                                <p x-show="selectedDay.slots.filter(s=>s.available).length===0" class="text-xs text-slate-400 py-2">No free slots this day.</p>
                            </div>
                        </template>
                        <template x-if="selectedSlot">
                            <p class="mt-2 text-sm text-green-700 font-semibold">✓ <span x-text="selectedSlot.display + ', ' + selectedDay.dateFmt"></span></p>
                        </template>
                    </div>
                </template>
                <p x-show="!loading && doctorId && days.length===0" class="text-sm text-slate-400 py-3">This doctor has no schedule configured. Use "Walk-in now", or set slots in Admin → Slots.</p>
            </div>
            <input type="hidden" name="slot_start" :value="slotStart">
        </div>

        {{-- Reason --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-3">3. Reason (optional)</h3>
            <input type="text" name="reason" class="input-field" placeholder="Chief complaint / reason for visit">
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary px-6 py-2.5" :disabled="!canBook" :class="!canBook ? 'opacity-40 cursor-not-allowed' : ''">Book Appointment</button>
            <a href="{{ route('web.admin.appointments') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
function scheduler() {
    return {
        mode: 'existing',
        patientSearch: '', patientResults: [], selectedPatient: null,
        newName: '', newPhone: '',
        doctorId: '{{ $prefillDoctor ?? '' }}',
        walkNow: false,
        days: [], selectedDay: null, selectedSlot: null, loading: false,
        upcoming: [],

        get slotStart() { return this.selectedSlot ? (this.selectedSlot.date + ' ' + this.selectedSlot.time) : ''; },
        get canBook() {
            const hasPatient = this.mode === 'existing' ? !!this.selectedPatient : (this.newName.trim() && this.newPhone.trim());
            const hasTiming = this.walkNow || !!this.selectedSlot;
            return hasPatient && this.doctorId && hasTiming;
        },
        async searchPatients() {
            if (this.patientSearch.length < 2) { this.patientResults = []; return; }
            try { const r = await fetch('/ajax/patients?q='+encodeURIComponent(this.patientSearch), {headers:{'Accept':'application/json'}}); if (r.ok) this.patientResults = await r.json(); } catch(e){}
        },
        pickPatient(p) { this.selectedPatient = p; this.patientResults = []; this.patientSearch = ''; this.loadUpcoming(p.id); },
        async loadUpcoming(patientId) {
            this.upcoming = [];
            if (!patientId) return;
            try {
                const r = await fetch('/ajax/patient-upcoming?patient='+encodeURIComponent(patientId), {headers:{'Accept':'application/json'}});
                if (r.ok) { const d = await r.json(); this.upcoming = d.upcoming || []; }
            } catch(e){}
        },
        async loadSlots() {
            this.days = []; this.selectedDay = null; this.selectedSlot = null;
            if (!this.doctorId) return;
            this.loading = true;
            try {
                const r = await fetch('/ajax/doctor-slots/'+this.doctorId, {headers:{'Accept':'application/json'}});
                if (r.ok) { const d = await r.json(); this.days = d.days || []; this.selectedDay = this.days.find(x => x.available > 0) || this.days[0] || null; }
            } catch(e){}
            this.loading = false;
        },
        nextAvailable() {
            const day = this.days.find(d => d.available > 0);
            if (!day) return;
            const slot = day.slots.find(s => s.available);
            if (!slot) return;
            this.selectedDay = day;
            this.selectedSlot = { date: day.date, time: slot.time, display: slot.display };
        },
        init() { if (this.doctorId) this.loadSlots(); if (this.selectedPatient) this.loadUpcoming(this.selectedPatient.id); }
    };
}
</script>
@endpush
@endsection
