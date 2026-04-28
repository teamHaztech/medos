@extends('layouts.app')
@section('title', 'Doctor Slots')
@section('page-title', 'Doctor Schedule & Slots')

@section('content')
<div x-data="slotsManager()" x-init="init()">

    {{-- Doctor selector --}}
    <div class="flex flex-wrap gap-2 mb-4">
        <template x-for="doc in doctors" :key="doc.id">
            <button @click="selectDoctor(doc)"
                :class="selectedDoctor?.id===doc.id ? 'bg-blue-500 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-medium transition-all">
                <span x-text="doc.name"></span>
                <span class="text-xs opacity-60 ml-1" x-text="doc.department"></span>
            </button>
        </template>
    </div>

    <template x-if="selectedDoctor">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- LEFT: Weekly Schedule Editor --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-700 mb-1">Weekly Schedule</h3>
                    <p class="text-xs text-slate-500 mb-3" x-text="selectedDoctor.name"></p>

                    {{-- Consultation duration --}}
                    <div class="mb-4">
                        <label class="text-xs font-semibold text-slate-500">Slot Duration (minutes)</label>
                        <div class="flex gap-1.5 mt-1">
                            <template x-for="d in [10,15,20,30,45,60]" :key="d">
                                <button @click="slotDuration=d" :class="slotDuration===d?'bg-blue-500 text-white':'bg-slate-100 text-slate-700'" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all" x-text="d+'m'"></button>
                            </template>
                        </div>
                    </div>

                    {{-- Days --}}
                    <div class="space-y-2">
                        <template x-for="day in weekDays" :key="day.key">
                            <div class="border border-slate-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" :checked="schedule[day.key]?.length > 0" @change="toggleDay(day.key)" class="rounded border-slate-300 text-blue-500">
                                        <span class="text-sm font-semibold" x-text="day.label"></span>
                                    </label>
                                    <button x-show="schedule[day.key]?.length" @click="addBlock(day.key)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">+ Add Block</button>
                                </div>

                                <template x-if="schedule[day.key]?.length">
                                    <div class="space-y-1.5">
                                        <template x-for="(block, bi) in schedule[day.key]" :key="bi">
                                            <div class="flex items-center gap-2">
                                                <input type="time" x-model="block.start" class="text-xs border border-slate-300 rounded px-2 py-1 w-24">
                                                <span class="text-xs text-slate-400">to</span>
                                                <input type="time" x-model="block.end" class="text-xs border border-slate-300 rounded px-2 py-1 w-24">
                                                <button @click="schedule[day.key].splice(bi,1)" class="text-red-400 hover:text-red-600 text-sm">&times;</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <p x-show="!schedule[day.key]?.length" class="text-xs text-slate-400 italic">Day off</p>
                            </div>
                        </template>
                    </div>

                    <button @click="saveSchedule()" :disabled="saving" class="btn-primary w-full mt-4 py-2.5" x-text="saving ? 'Saving...' : 'Save Schedule'"></button>
                    <p x-show="saveMsg" class="text-center text-sm mt-2" :class="saveOk?'text-green-600':'text-red-600'" x-text="saveMsg"></p>
                </div>
            </div>

            {{-- RIGHT: Calendar Preview (next 14 days) --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                    <h3 class="text-sm font-semibold text-slate-700 mb-3">Slot Preview — Next 14 Days</h3>

                    <template x-if="previewLoading">
                        <p class="text-sm text-slate-400 text-center py-8">Loading...</p>
                    </template>

                    <div class="space-y-3" x-show="!previewLoading">
                        <template x-for="day in previewDays" :key="day.date">
                            <div class="border border-slate-200 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <span class="text-sm font-semibold text-slate-800" x-text="day.dayFull + ', ' + day.dateFmt"></span>
                                        <span x-show="day.is_today" class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-medium">Today</span>
                                    </div>
                                    <span class="text-xs font-medium" :class="day.available > 0 ? 'text-green-600' : 'text-red-500'" x-text="day.available + '/' + day.total + ' available'"></span>
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="slot in filterSlots(day)" :key="slot.time">
                                        <span class="px-2 py-1 rounded text-xs font-medium"
                                            :class="slot.available ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-400 border border-red-100 line-through'"
                                            x-text="slot.display">
                                        </span>
                                    </template>
                                </div>
                                <p x-show="day.is_today" class="text-[10px] text-slate-400 mt-1">Past time slots hidden</p>
                            </div>
                        </template>
                        <p x-show="previewDays.length===0" class="text-sm text-slate-400 text-center py-8">No schedule set for this doctor</p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <template x-if="!selectedDoctor">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
            <p class="text-slate-500">Select a doctor above to manage their schedule</p>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function slotsManager() {
    return {
        doctors: {!! json_encode($doctors->map(function($d) { return ['id'=>$d->id,'name'=>$d->name,'department'=>$d->department,'schedule'=>is_array($d->schedule) ? $d->schedule : json_decode($d->schedule ?? '{}', true),'duration'=>$d->consultation_duration_default??15]; })->values()) !!},
        selectedDoctor: null,
        schedule: {},
        slotDuration: 15,
        saving: false, saveMsg: '', saveOk: false,
        previewDays: [], previewLoading: false,

        weekDays: [
            {key:'monday',label:'Monday'},{key:'tuesday',label:'Tuesday'},{key:'wednesday',label:'Wednesday'},
            {key:'thursday',label:'Thursday'},{key:'friday',label:'Friday'},{key:'saturday',label:'Saturday'},{key:'sunday',label:'Sunday'},
        ],

        init() {},

        filterSlots(day) {
            const now = new Date();
            const todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
            const isToday = day.date === todayStr;
            const nowMinutes = now.getHours() * 60 + now.getMinutes();

            return day.slots.filter(s => {
                if (isToday && !s.booked) {
                    const [h, m] = s.time.split(':').map(Number);
                    if (h * 60 + m <= nowMinutes) return false;
                }
                return s.available || s.booked;
            });
        },

        selectDoctor(doc) {
            this.selectedDoctor = doc;
            this.schedule = JSON.parse(JSON.stringify(doc.schedule || {}));
            this.slotDuration = doc.duration || 15;
            this.saveMsg = '';
            this.loadPreview();
        },

        toggleDay(day) {
            if (this.schedule[day]?.length) {
                this.schedule[day] = [];
            } else {
                this.schedule[day] = [{start:'09:00',end:'13:00'},{start:'14:00',end:'17:00'}];
            }
        },

        addBlock(day) {
            if (!this.schedule[day]) this.schedule[day] = [];
            this.schedule[day].push({start:'14:00',end:'17:00'});
        },

        async saveSchedule() {
            this.saving = true; this.saveMsg = '';
            try {
                const res = await fetch('/admin/slots/' + this.selectedDoctor.id, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ schedule: this.schedule, consultation_duration_default: this.slotDuration }),
                });
                const data = await res.json();
                this.saveOk = data.success;
                this.saveMsg = data.message || (data.success ? 'Saved!' : 'Failed');
                // Update local doctor data
                const doc = this.doctors.find(d => d.id === this.selectedDoctor.id);
                if (doc) { doc.schedule = JSON.parse(JSON.stringify(this.schedule)); doc.duration = this.slotDuration; }
                this.loadPreview();
            } catch(e) { this.saveOk = false; this.saveMsg = 'Error saving schedule'; }
            this.saving = false;
        },

        async loadPreview() {
            if (!this.selectedDoctor) return;
            this.previewLoading = true;
            try {
                const res = await fetch('/ajax/doctor-slots/' + this.selectedDoctor.id, { headers: { 'Accept': 'application/json' } });
                if (res.ok) { const data = await res.json(); this.previewDays = data.days || []; }
            } catch(e) {}
            this.previewLoading = false;
        },
    };
}
</script>
@endpush
