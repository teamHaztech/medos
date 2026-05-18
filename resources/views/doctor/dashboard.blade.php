@extends('layouts.app')

@section('title', 'Doctor Dashboard')
@section('page-title', 'My Queue')

@section('content')
<div x-data="doctorDashboard()" x-init="init()">

    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-sm text-slate-500">Queue for <span class="font-semibold text-slate-700" x-text="doctorName"></span></p>
            <p class="text-xs text-slate-400 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                Live · <span x-text="pendingCount"></span> waiting
            </p>
        </div>
        <button @click="callNext()" :disabled="!nextPatient" class="btn-success px-5 py-2.5 disabled:opacity-40">Call Next →</button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- Queue --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3">
                {{-- New patient alert --}}
                <template x-if="newPatientAlert">
                    <div class="mb-2 px-3 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium animate-pulse flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New: <span x-text="newPatientAlert"></span>
                    </div>
                </template>

                {{-- Progress bar --}}
                <div class="px-1 mb-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-600"><span x-text="doneCount" class="text-green-600"></span>/<span x-text="queue.length"></span> done</span>
                        <span class="text-xs text-slate-400" x-text="pendingCount + ' remaining'"></span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-1.5">
                        <div class="bg-green-500 h-1.5 rounded-full transition-all" :style="'width:' + (queue.length ? (doneCount/queue.length*100) : 0) + '%'"></div>
                    </div>
                </div>

                <div class="space-y-1 max-h-[calc(100vh-260px)] overflow-y-auto">
                    <template x-for="entry in queue" :key="entry.id">
                        <div @click="entry.status !== 'done' && selectPatient(entry)"
                            class="flex items-center gap-2.5 p-2 rounded-lg transition-all"
                            :class="{
                                'bg-blue-50 ring-1 ring-blue-400': selectedPatient?.id === entry.id && entry.status !== 'done',
                                'bg-slate-50 hover:bg-slate-100 cursor-pointer': entry.status === 'waiting',
                                'bg-amber-50 hover:bg-amber-100 cursor-pointer': entry.status === 'called' && selectedPatient?.id !== entry.id,
                                'bg-white cursor-default': entry.status === 'done',
                            }">

                            {{-- Checkbox circle --}}
                            <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                :class="entry.status === 'done' ? 'bg-green-500 border-green-500' : 'border-slate-300'">
                                <svg x-show="entry.status === 'done'" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>

                            {{-- Patient info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                                        :class="entry.status === 'done' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-700'"
                                        x-text="entry.token"></span>
                                    <span class="text-sm font-medium truncate"
                                        :class="entry.status === 'done' ? 'text-slate-400 line-through' : 'text-slate-900'"
                                        x-text="entry.name"></span>
                                </div>
                                <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                                    <span x-show="entry.urgency==='emergency'" class="px-1 py-0 bg-red-100 text-red-700 rounded text-[9px] font-bold flex-shrink-0">EMERGENCY</span>
                                    <span x-show="entry.urgency==='urgent'" class="px-1 py-0 bg-amber-100 text-amber-700 rounded text-[9px] font-bold flex-shrink-0">PRIORITY</span>
                                    <span x-show="entry.isReferral" class="px-1 py-0 bg-orange-100 text-orange-700 rounded text-[9px] font-bold flex-shrink-0">REF</span>
                                    <p class="text-xs truncate"
                                        :class="entry.status === 'done' ? 'text-slate-300' : 'text-slate-500'"
                                        x-text="entry.complaint"></p>
                                </div>
                            </div>

                            {{-- Right side status --}}
                            <div class="flex-shrink-0 text-right">
                                <template x-if="entry.status === 'done'">
                                    <span class="text-xs font-semibold text-green-600">Done</span>
                                </template>
                                <template x-if="entry.status === 'called'">
                                    <span class="flex items-center gap-1 text-xs font-semibold text-amber-600">
                                        <span class="w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span> Now
                                    </span>
                                </template>
                                <template x-if="entry.status === 'waiting'">
                                    <span class="text-xs text-slate-400" x-text="entry.waitTime"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <p x-show="queue.length===0" class="text-sm text-slate-400 text-center py-8">No patients</p>
                </div>
            </div>
        </div>

        {{-- Right panel --}}
        <div class="lg:col-span-2">

            {{-- Briefing --}}
            <template x-if="selectedPatient && !showConsultation">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900" x-text="selectedPatient.name"></h3>
                            <p class="text-sm text-slate-500" x-text="(selectedPatient.age||'?')+' yrs, '+selectedPatient.gender+' · '+selectedPatient.phone"></p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="urgencyBadge(selectedPatient.urgency)" x-text="selectedPatient.urgency"></span>
                    </div>
                    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg mb-3">
                        <p class="text-xs font-semibold text-blue-600 uppercase mb-0.5">Complaint</p>
                        <p class="text-sm text-slate-800" x-text="selectedPatient.complaint"></p>
                    </div>

                    {{-- Referral banner --}}
                    <template x-if="selectedPatient.isReferral">
                        <div class="p-3 bg-orange-50 border border-orange-200 rounded-lg mb-3">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="text-xs font-bold text-orange-700 uppercase">Referred Patient</span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                                    :class="selectedPatient.referralUrgency==='emergency'?'bg-red-100 text-red-800':selectedPatient.referralUrgency==='priority'?'bg-amber-100 text-amber-800':'bg-green-100 text-green-800'"
                                    x-text="selectedPatient.referralUrgency"></span>
                            </div>
                            <p class="text-sm text-slate-700">From: <span class="font-semibold" x-text="selectedPatient.referralFrom"></span> <span class="text-slate-400" x-text="'(' + selectedPatient.referralDept + ')'"></span></p>
                            <p class="text-sm text-slate-600 mt-1" x-show="selectedPatient.referralReason" x-text="'Reason: ' + selectedPatient.referralReason"></p>
                            <template x-if="selectedPatient.previousDiagnosis?.length">
                                <p class="text-sm text-slate-600 mt-1">Previous Dx: <span class="font-medium" x-text="selectedPatient.previousDiagnosis.join(', ')"></span></p>
                            </template>
                            <p class="text-sm text-slate-500 mt-1" x-show="selectedPatient.previousNotes" x-text="'Notes: ' + selectedPatient.previousNotes"></p>
                        </div>
                    </template>

                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-if="selectedPatient.allergies?.length"><div class="px-2.5 py-1 bg-red-50 border border-red-200 rounded text-xs text-red-800 font-medium" x-text="'⚠ '+selectedPatient.allergies.join(', ')"></div></template>
                        <template x-if="selectedPatient.medications?.length"><div class="px-2.5 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-800 font-medium" x-text="'💊 '+selectedPatient.medications.join(', ')"></div></template>
                        <div class="px-2.5 py-1 rounded text-xs font-medium" :class="selectedPatient.insuranceActive?'bg-green-50 border border-green-200 text-green-800':'bg-slate-50 border border-slate-200 text-slate-600'" x-text="selectedPatient.insuranceActive?'✓ Insured':'Self-pay'"></div>
                        <template x-if="selectedPatient.abha">
                            <div class="px-2.5 py-1 bg-indigo-50 border border-indigo-200 rounded text-xs text-indigo-800 font-medium flex items-center gap-1">
                                <span>🏥</span> ABHA: <span x-text="selectedPatient.abha"></span>
                            </div>
                        </template>
                    </div>
                    <button @click="startConsultation()" class="btn-primary w-full py-3 text-base">Start Consultation</button>
                </div>
            </template>

            {{-- Consultation --}}
            <template x-if="showConsultation">
                @include('doctor._consultation')
            </template>

            {{-- Empty --}}
            <template x-if="!selectedPatient">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-slate-500">Select a patient from the queue</p>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function doctorDashboard() {
    return {
        doctorName: @json($doctorName ?? 'Doctor'),
        queue: @json($queue ?? []),
        otherDoctors: @json($otherDoctors ?? []),
        selectedPatient: null,
        showConsultation: false,
        cTab: 'vitals',

        vitals: { bp:'',temp:'',pulse:'',spo2:'',weight:'',rr:'',examNotes:'' },
        selectedDiagnoses: [], customDiagnosis: '',
        orders: [], testFilter: 'all', allTests: [],
        prescriptions: [], medSearch: '', medResults: [], medSearching: false,
        referral: null, referralReason: '', referralSlot: null, referralDays: [], referralSelectedDay: null, referralLoading: false, referralUrgency: 'normal',
        followUp: null,
        soap: { subjective:'',assessment:'',plan:'' },
        advice: [],

        commonDiagnoses: [
            {code:'J06.9',name:'Upper Resp Infection'},{code:'R50.9',name:'Fever'},{code:'K30',name:'Dyspepsia'},
            {code:'J02.9',name:'Pharyngitis'},{code:'M54.5',name:'Low Back Pain'},{code:'J00',name:'Common Cold'},
            {code:'E11.9',name:'Type 2 Diabetes'},{code:'I10',name:'Hypertension'},{code:'K29.7',name:'Gastritis'},
            {code:'L30.9',name:'Dermatitis'},{code:'H66.9',name:'Otitis Media'},{code:'N39.0',name:'UTI'},
            {code:'R51',name:'Headache'},{code:'A09',name:'Gastroenteritis'},{code:'J18.9',name:'Pneumonia'},
            {code:'M79.3',name:'Myalgia'},{code:'R05',name:'Cough'},{code:'K21',name:'GERD'},
        ],

        assessmentTemplates: [
            {label:'Viral fever',text:'Likely viral fever, self-limiting'},
            {label:'Bacterial infection',text:'Suspected bacterial infection, antibiotics initiated'},
            {label:'Stable chronic',text:'Chronic condition stable on current medications'},
            {label:'Uncontrolled DM',text:'Diabetes poorly controlled, medication adjustment needed'},
            {label:'Hypertension follow-up',text:'Blood pressure within target range'},
            {label:'Needs investigation',text:'Further investigation needed to confirm diagnosis'},
            {label:'Acute exacerbation',text:'Acute exacerbation of underlying condition'},
            {label:'Post-operative',text:'Post-operative recovery progressing well'},
        ],

        planTemplates: [
            {label:'Conservative',text:'Conservative management with symptomatic treatment'},
            {label:'Antibiotics',text:'Start antibiotics as prescribed, complete full course'},
            {label:'Refer specialist',text:'Refer to specialist for further evaluation'},
            {label:'Admit',text:'Admission recommended for observation and management'},
            {label:'Investigations',text:'Order investigations as listed, review on follow-up'},
            {label:'Medication adjust',text:'Adjust medication dosage as prescribed'},
            {label:'Lifestyle advice',text:'Lifestyle modifications advised: diet, exercise, stress management'},
            {label:'Monitor vitals',text:'Monitor vitals closely, return if symptoms worsen'},
        ],

        commonAdvice: ['Drink plenty of fluids','Rest 2-3 days','Avoid oily/spicy food','Complete medicine course','Monitor temperature','Visit ER if worsens','Avoid cold drinks','Light diet','No heavy lifting','Follow up as advised','Stop smoking','Reduce salt intake'],

        get nextPatient() { return this.queue.find(e => e.status === 'waiting'); },
        get pendingCount() { return this.queue.filter(e => e.status !== 'done').length; },
        get doneCount() { return this.queue.filter(e => e.status === 'done').length; },

        get filteredTests() {
            if (this.testFilter === 'all') return this.allTests;
            return this.allTests.filter(t => t.type === this.testFilter);
        },

        async init() {
            // Load tests from DB
            try {
                const res = await fetch('/ajax/tests', { headers: { 'Accept': 'application/json' } });
                if (res.ok) this.allTests = await res.json();
            } catch(e) {}

            // Auto-select: if there's already an active (called) patient, select them
            // Otherwise auto-call the first waiting patient
            const active = this.queue.find(e => e.status === 'called');
            if (active) {
                this.selectedPatient = active;
            } else {
                const first = this.queue.find(e => e.status === 'waiting');
                if (first) {
                    this.callNext();
                }
            }

            // Auto-refresh queue every 5 seconds
            setInterval(() => this.refreshQueue(), 5000);
        },

        selectPatient(e) {
            if (e.status === 'done') return;
            this.selectedPatient = e;
            this.showConsultation = false;
            this.resetConsultation();
        },
        async callNext() {
            const n = this.nextPatient;
            if (n) {
                try {
                    await fetch('/doctor/call-next/' + n.id, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                } catch(e) {}
                n.status = 'called';
                this.selectedPatient = n;
                this.showConsultation = false;
                this.resetConsultation();
            }
        },

        startConsultation() {
            this.showConsultation = true; this.cTab = 'vitals';

            // Pre-fill from complaint
            if (this.selectedPatient?.complaint) {
                this.soap.subjective = this.selectedPatient.complaint;
            }

            // Pre-fill from referral data
            if (this.selectedPatient?.isReferral) {
                const p = this.selectedPatient;
                let subj = p.complaint || '';
                if (p.referralReason) subj += '\n\nReferred by ' + p.referralFrom + ' (' + p.referralDept + '): ' + p.referralReason;
                if (p.previousNotes) subj += '\n\nPrevious notes: ' + p.previousNotes;
                this.soap.subjective = subj.trim();

                // Pre-fill previous diagnoses
                if (p.previousDiagnosis?.length) {
                    p.previousDiagnosis.forEach(code => {
                        const found = this.commonDiagnoses.find(d => d.code === code);
                        if (found && !this.selectedDiagnoses.find(d => d.code === code)) {
                            this.selectedDiagnoses.push({...found});
                        } else if (!found) {
                            this.selectedDiagnoses.push({code: code, name: code});
                        }
                    });
                }
            }
        },

        resetConsultation() {
            this.vitals={bp:'',temp:'',pulse:'',spo2:'',weight:'',rr:'',examNotes:''};
            this.selectedDiagnoses=[];this.customDiagnosis='';
            this.orders=[];this.prescriptions=[];this.medSearch='';this.medResults=[];
            this.referral=null;this.referralReason='';this.referralSlot=null;this.referralDays=[];this.referralSelectedDay=null;this.referralUrgency='normal';
            this.followUp=null;this.soap={subjective:'',assessment:'',plan:''};this.advice=[];
        },

        toggleDiagnosis(d) { const i=this.selectedDiagnoses.findIndex(s=>s.code===d.code); i>=0?this.selectedDiagnoses.splice(i,1):this.selectedDiagnoses.push({...d}); },

        toggleTest(t) { const i=this.orders.findIndex(o=>o.id===t.id); i>=0?this.orders.splice(i,1):this.orders.push({...t}); },

        getTestClass(type) { return {lab:'bg-cyan-50 text-cyan-700',imaging:'bg-purple-50 text-purple-700',procedure:'bg-amber-50 text-amber-700'}[type]||'bg-slate-100'; },
        getTestActiveClass(type) { return {lab:'bg-cyan-500 text-white',imaging:'bg-purple-500 text-white',procedure:'bg-amber-500 text-white'}[type]||'bg-blue-500 text-white'; },

        async searchMedicines() {
            if(this.medSearch.length<2){this.medResults=[];return;}
            this.medSearching=true;
            try {
                const res = await fetch('/ajax/medicines?q='+encodeURIComponent(this.medSearch), {headers:{'Accept':'application/json'}});
                if(res.ok) this.medResults = await res.json();
            } catch(e){}
            this.medSearching=false;
        },

        addMedicineFromDB(med) {
            this.prescriptions.push({
                name: med.name,
                dosage: med.default_dosage||'',
                frequency: med.default_frequency||'',
                duration: med.default_duration||'',
                timing: med.default_timing||'',
                form: med.form||'tablet',
            });
        },

        addCustomMedicine() {
            this.prescriptions.push({ name:this.medSearch, dosage:'', frequency:'', duration:'', timing:'', form:'tablet' });
            this.medSearch=''; this.medResults=[];
        },

        async selectReferral(doc) {
            if(this.referral?.id===doc.id){this.referral=null;this.referralDays=[];this.referralSelectedDay=null;this.referralSlot=null;return;}
            this.referral=doc;this.referralSlot=null;this.referralSelectedDay=null;this.referralLoading=true;
            try {
                const res = await fetch('/ajax/doctor-slots/'+doc.id, {headers:{'Accept':'application/json'}});
                if(res.ok){
                    const data = await res.json();
                    this.referralDays = (data.days||[]).filter(d => d.available > 0);
                    if(this.referralDays.length) this.referralSelectedDay = this.referralDays[0];
                }
            } catch(e){ this.referralDays=[]; }
            this.referralLoading=false;
        },

        toggleAdvice(a) { const i=this.advice.indexOf(a); i>=0?this.advice.splice(i,1):this.advice.push(a); },

        async completeConsultation() {
            if(!confirm('Complete consultation for '+this.selectedPatient.name+'?')) return;

            // Save to database
            try {
                const body = {
                    diagnosis_codes: this.selectedDiagnoses.map(d => d.code),
                    soap_notes: this.soap,
                };
                if (this.followUp) {
                    const days = {'3 days':3,'1 week':7,'2 weeks':14,'1 month':30,'3 months':90}[this.followUp] || 7;
                    body.follow_up_date = new Date(Date.now() + days*86400000).toISOString().split('T')[0];
                }
                body.prescriptions = this.prescriptions;
                body.orders = this.orders;
                if (this.referral) {
                    body.referral = {
                        doctor_id: this.referral.id,
                        urgency: this.referralUrgency,
                        reason: this.referralReason,
                        complaint: this.selectedPatient?.complaint || '',
                        date: this.referralSlot?.date || null,
                        time: this.referralSlot?.time || null,
                    };
                }

                await fetch('/doctor/complete/' + this.selectedPatient.id, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(body),
                });
            } catch(e) { console.error('Failed to save:', e); }

            // Mark current patient as done in UI
            const current = this.queue.find(e => e.id === this.selectedPatient.id);
            if (current) current.status = 'done';

            // Reset consultation
            this.showConsultation = false;
            this.resetConsultation();

            // Auto-select next pending patient
            const next = this.queue.find(e => e.status === 'waiting');
            if (next) {
                // Call next in backend
                try {
                    await fetch('/doctor/call-next/' + next.id, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                } catch(e) {}
                next.status = 'called';
                this.selectedPatient = next;
            } else {
                this.selectedPatient = null;
            }
        },

        async refreshQueue() {
            try {
                const res = await fetch('/doctor/queue-json', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const newQueue = await res.json();

                // Check for new patients added since last refresh
                const oldIds = new Set(this.queue.map(e => e.id));
                const newPatients = newQueue.filter(e => !oldIds.has(e.id) && e.status !== 'done');

                // Preserve local state for patients we've already interacted with
                const merged = newQueue.map(np => {
                    const existing = this.queue.find(e => e.id === np.id);
                    // If we locally marked as 'called' or 'done', keep that
                    if (existing && (existing.status === 'called' || existing.status === 'done')) {
                        return { ...np, status: existing.status };
                    }
                    return np;
                });

                this.queue = merged;

                // Flash notification for new patients
                if (newPatients.length > 0) {
                    this.newPatientAlert = newPatients.map(p => p.name).join(', ');
                    setTimeout(() => { this.newPatientAlert = null; }, 5000);
                }
            } catch(e) { console.log('Queue refresh failed'); }
        },

        newPatientAlert: null,

        getAvailableSlots(day) {
            if (!day) return [];
            const now = new Date();
            const todayStr = now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0');
            const isToday = day.date === todayStr;
            const nowMinutes = now.getHours() * 60 + now.getMinutes();

            return day.slots.filter(s => {
                if (!s.available) return false;
                if (isToday) {
                    // Parse slot time HH:MM and compare
                    const [h, m] = s.time.split(':').map(Number);
                    if (h * 60 + m <= nowMinutes) return false;
                }
                return true;
            });
        },

        urgencyColor(u) { return {emergency:'#ef4444',urgent:'#f97316',semi_urgent:'#eab308',routine:'#22c55e'}[u]||'#22c55e'; },
        urgencyBadge(u) { return {emergency:'bg-red-100 text-red-800',urgent:'bg-orange-100 text-orange-800',semi_urgent:'bg-yellow-100 text-yellow-800',routine:'bg-green-100 text-green-800'}[u]||'bg-green-100 text-green-800'; },
        freqLabel(f) { return {OD:'Once a day',BD:'Twice a day',TDS:'3 times a day',QID:'4 times a day',SOS:'As needed',HS:'At bedtime',STAT:'Immediately (once)'}[f]||f; },
    };
}
</script>
@endpush
