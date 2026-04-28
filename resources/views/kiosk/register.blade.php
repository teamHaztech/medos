@extends('layouts.kiosk')

@section('title', 'New Patient')
@section('subtitle', 'Quick Registration')

@section('content')
<div x-data="kioskRegister()" class="w-full max-w-3xl">

    {{-- STEP 1: Phone Number --}}
    <template x-if="step === 1 && !result">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-slate-800 mb-1">Enter Phone Number</h2>
            <p class="text-base text-slate-500 mb-6">अपना फोन नंबर डालें / तुमचा फोन नंबर टाका / तुमचो फोन नंबर दिवचो</p>
            <form @submit.prevent="checkPhone()">
                <input type="tel" x-model="phone" autofocus inputmode="numeric" maxlength="10"
                    class="w-full text-center text-4xl font-bold py-4 px-6 border-2 border-slate-300 rounded-2xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none tracking-widest"
                    placeholder="98765 43210">
                <template x-if="phoneError"><p class="text-red-600 mt-2 font-semibold" x-text="phoneError"></p></template>
                <button type="submit" :disabled="phone.replace(/\D/g,'').length < 10 || phoneLoading"
                    class="w-full mt-4 py-4 text-xl font-bold bg-blue-500 hover:bg-blue-600 text-white rounded-2xl shadow-lg disabled:opacity-40 active:scale-[0.98]">
                    <span x-show="!phoneLoading">Next →</span><span x-show="phoneLoading">Checking...</span>
                </button>
            </form>
            <div class="mt-3 text-center" x-show="window.MEDOS_REGION?.healthId?.enabled">
                <p class="text-xs text-slate-400 mb-2">or</p>
                <button type="button" @click="step = 'abha'" class="text-blue-600 text-sm font-semibold hover:underline">
                    <span x-text="'Enter ' + (window.MEDOS_REGION?.healthId?.field_label || 'Health ID') + ' / ' + (window.MEDOS_REGION?.healthId?.field_label_local || '')"></span>
                </button>
            </div>
            <button @click="doEmergency()" class="w-full mt-3 py-4 text-xl font-bold bg-red-500 hover:bg-red-600 text-white rounded-2xl shadow-lg active:scale-[0.98] flex items-center justify-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                EMERGENCY / आपातकालीन / आणीबाणी
            </button>
            <a href="{{ route('kiosk.index') }}" class="block text-center text-slate-400 hover:text-slate-600 mt-4">← Back</a>
        </div>
    </template>

    {{-- STEP ABHA: Enter ABHA Health ID --}}
    <template x-if="step === 'abha' && !result">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-slate-800 mb-1" x-text="'Enter ' + (window.MEDOS_REGION?.healthId?.field_label || 'Health ID')"></h2>
            <p class="text-base text-slate-500 mb-6" x-text="window.MEDOS_REGION?.healthId?.field_label_local || ''"></p>
            <div>
                <input type="text" x-model="abhaNumber" autofocus inputmode="numeric" maxlength="17"
                    class="w-full text-center text-3xl font-bold py-4 px-6 border-2 border-slate-300 rounded-2xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none tracking-widest"
                    placeholder="XX-XXXX-XXXX-XXXX"
                    @input="abhaError = null">
                <p class="text-xs text-slate-400 mt-2" x-text="window.MEDOS_REGION?.healthId?.format || '14-digit number'"></p>
                <template x-if="abhaError"><p class="text-red-600 mt-2 font-semibold" x-text="abhaError"></p></template>
                <button type="button" @click="verifyAbha()" :disabled="abhaNumber.replace(/\D/g,'').length < 14 || abhaVerifying"
                    class="w-full mt-4 py-4 text-xl font-bold bg-indigo-500 hover:bg-indigo-600 text-white rounded-2xl shadow-lg disabled:opacity-40 active:scale-[0.98]">
                    <span x-show="!abhaVerifying">Verify ABHA / आभा सत्यापित करें</span>
                    <span x-show="abhaVerifying">Verifying...</span>
                </button>
            </div>
            <button @click="step = 1; abhaNumber = ''; abhaError = null;" class="block mx-auto text-slate-400 hover:text-slate-600 mt-4">
                Don't have ABHA? Use phone number / फोन नंबर से आगे बढ़ें ←
            </button>
        </div>
    </template>

    {{-- STEP 2: Name (new patients only) --}}
    <template x-if="step === 2 && !result">
        <div class="text-center">
            <h2 class="text-3xl font-bold text-slate-800 mb-1">Your Name</h2>
            <p class="text-base text-slate-500 mb-6">अपना नाम लिखें / तुमचे नाव लिहा / तुमचें नांव बरोवचें</p>
            <form @submit.prevent="step = 3">
                <input type="text" x-model="name" autofocus class="w-full text-center text-2xl font-bold py-4 px-6 border-2 border-slate-300 rounded-2xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none" placeholder="Full Name / पूरा नाम / पूर्ण नाव">
                <div class="grid grid-cols-3 gap-3 mt-4">
                    <button type="button" @click="gender='male'" :class="gender==='male'?'bg-blue-500 text-white':'bg-slate-100 text-slate-700'" class="py-3 rounded-xl font-semibold">Male</button>
                    <button type="button" @click="gender='female'" :class="gender==='female'?'bg-pink-500 text-white':'bg-slate-100 text-slate-700'" class="py-3 rounded-xl font-semibold">Female</button>
                    <button type="button" @click="gender='other'" :class="gender==='other'?'bg-purple-500 text-white':'bg-slate-100 text-slate-700'" class="py-3 rounded-xl font-semibold">Other</button>
                </div>
                <button type="submit" :disabled="!name" class="w-full mt-4 py-4 text-xl font-bold bg-blue-500 hover:bg-blue-600 text-white rounded-2xl shadow-lg disabled:opacity-40 active:scale-[0.98]">Next →</button>
            </form>
            <button @click="step=1" class="block mx-auto text-slate-400 hover:text-slate-600 mt-3">← Back</button>
        </div>
    </template>

    {{-- STEP 3: Select Problem --}}
    <template x-if="step === 3 && !result">
        <div>
            <p class="text-center text-base text-slate-500 mb-1" x-show="existingPatient">Welcome back, <span class="font-bold text-slate-800" x-text="name"></span>!</p>
            <h2 class="text-center text-2xl font-bold text-slate-800 mb-4">What's the problem?</h2>

            <div class="bg-white rounded-xl border border-slate-200 p-4 mb-3">
                <div class="grid grid-cols-4 gap-2">
                    <template x-for="p in problems" :key="p.id">
                        <button @click="pickProblem(p)"
                            :class="selectedProblem?.id === p.id ? 'ring-2 ring-blue-400 bg-blue-50' : 'bg-slate-50 hover:bg-slate-100'"
                            class="flex flex-col items-center p-3 rounded-xl transition-all active:scale-95 text-center">
                            <span class="text-2xl" x-text="p.icon"></span>
                            <span class="text-xs font-semibold text-slate-700 mt-1 leading-tight" x-text="p.label"></span>
                            <span class="text-[10px] text-slate-400 leading-tight" x-text="p.hindi + ' · ' + p.marathi"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Custom complaint for "Other" --}}
            <div x-show="selectedProblem?.id === 'other'" class="mb-3">
                <input type="text" x-model="customComplaint" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Describe your problem...">
            </div>

            <button @click="step = 4; loadMatchingDoctors()" :disabled="!selectedProblem || (selectedProblem.id === 'other' && !customComplaint)"
                class="w-full py-4 text-xl font-bold bg-blue-500 hover:bg-blue-600 text-white rounded-2xl shadow-lg disabled:opacity-40 active:scale-[0.98]">
                Find Doctor →
            </button>
            <button @click="step = existingPatient ? 1 : 2" class="block mx-auto text-slate-400 hover:text-slate-600 mt-3">← Back</button>
        </div>
    </template>

    {{-- STEP 4: Choose Doctor --}}
    <template x-if="step === 4 && !result">
        <div>
            <h2 class="text-center text-2xl font-bold text-slate-800 mb-1">Choose a Doctor</h2>
            <p class="text-center text-sm text-slate-500 mb-1">डॉक्टर चुनें / डॉक्टर निवडा / दोतोर वेंचात</p>
            <p class="text-center text-sm text-slate-500 mb-4">
                For: <span class="font-semibold text-blue-600" x-text="selectedProblem?.label"></span>
            </p>

            <template x-if="doctorsLoading">
                <p class="text-center text-slate-400 py-8">Finding available doctors...</p>
            </template>

            <template x-if="!doctorsLoading">
                <div>
                    {{-- Recommended doctors --}}
                    <template x-if="recommendedDocs.length">
                        <div class="mb-3">
                            <p class="text-xs font-semibold text-green-600 uppercase mb-2 px-1">Recommended for your problem</p>
                            <div class="space-y-2">
                                <template x-for="doc in recommendedDocs" :key="doc.id">
                                    <button @click="selectedDoctor = doc"
                                        :class="selectedDoctor?.id === doc.id ? 'ring-2 ring-green-400 bg-green-50' : 'bg-white hover:bg-green-50'"
                                        class="w-full flex items-center gap-3 p-3 rounded-xl border border-slate-200 transition-all active:scale-[0.98] text-left">
                                        <div class="w-11 h-11 bg-green-100 text-green-700 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0" x-text="doc.name.split(' ').pop()[0]"></div>
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-slate-800" x-text="doc.name"></p>
                                            <p class="text-xs text-slate-500" x-text="doc.department"></p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="text-xs font-semibold" :class="doc.queue === 0 ? 'text-green-600' : doc.queue < 3 ? 'text-blue-600' : 'text-amber-600'" x-text="doc.queue === 0 ? 'No wait' : doc.queue + ' in queue'"></p>
                                            <p class="text-[10px] text-slate-400" x-text="'~' + doc.duration + ' min/patient'"></p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Other doctors --}}
                    <template x-if="otherDocs.length">
                        <div class="mb-3">
                            <p class="text-xs font-semibold text-slate-400 uppercase mb-2 px-1">Other available doctors</p>
                            <div class="space-y-1.5">
                                <template x-for="doc in otherDocs" :key="doc.id">
                                    <button @click="selectedDoctor = doc"
                                        :class="selectedDoctor?.id === doc.id ? 'ring-2 ring-blue-400 bg-blue-50' : 'bg-white hover:bg-slate-50'"
                                        class="w-full flex items-center gap-3 p-2.5 rounded-xl border border-slate-200 transition-all active:scale-[0.98] text-left">
                                        <div class="w-9 h-9 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" x-text="doc.name.split(' ').pop()[0]"></div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-slate-700" x-text="doc.name"></p>
                                            <p class="text-xs text-slate-400" x-text="doc.department"></p>
                                        </div>
                                        <span class="text-xs text-slate-400" x-text="doc.queue + ' in queue'"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Submit --}}
                    <button @click="submitRegistration()" :disabled="!selectedDoctor || loading"
                        class="w-full mt-3 py-4 text-xl font-bold bg-green-500 hover:bg-green-600 text-white rounded-2xl shadow-lg disabled:opacity-40 active:scale-[0.98]">
                        <span x-show="!loading">Get Token ✓</span>
                        <span x-show="loading">Processing...</span>
                    </button>
                </div>
            </template>

            <template x-if="error"><p class="text-red-600 text-center mt-2 font-semibold" x-text="error"></p></template>
            <button @click="step = 3; selectedDoctor = null;" class="block mx-auto text-slate-400 hover:text-slate-600 mt-3">← Change Problem</button>
        </div>
    </template>

    {{-- SUCCESS --}}
    <template x-if="result">
        <div class="text-center p-6 bg-green-50 border-2 border-green-200 rounded-2xl" x-init="startAutoReset()">
            <svg class="w-16 h-16 mx-auto text-green-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h2 class="text-2xl font-bold text-green-800 mb-1" x-text="'Welcome, ' + result.name + '!'"></h2>
            <div class="bg-white rounded-xl p-4 my-4 inline-block min-w-[200px]">
                <p class="text-slate-500 text-xs">Your Token / आपका टोकन</p>
                <p class="text-4xl font-black text-blue-600 my-1" x-text="result.token"></p>
            </div>
            <div class="space-y-1 text-lg text-slate-700">
                <p>Doctor: <span class="font-bold" x-text="result.doctor"></span></p>
                <p>Department: <span x-text="result.department"></span></p>
                <p>Queue: <span class="font-bold text-green-700">#<span x-text="result.position"></span></span> &middot; Wait: <span class="font-bold text-blue-600" x-text="result.wait"></span></p>
            </div>
            <div class="mt-4 p-3 bg-white rounded-xl">
                <p class="text-xl font-bold text-slate-800">Please go to Waiting Area</p>
                <p class="text-sm text-slate-500">कृपया प्रतीक्षा क्षेत्र में जाएं / कृपया प्रतीक्षा क्षेत्रात जा / उपकार करून वाट पळोवची जागो वचात</p>
            </div>
            <div class="mt-4">
                <div class="w-full bg-green-200 rounded-full h-1.5">
                    <div class="bg-green-500 h-1.5 rounded-full transition-all" :style="'width:'+countdown/20*100+'%'"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1">Resets in <span x-text="countdown"></span>s</p>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function kioskRegister() {
    return {
        step: 1,
        phone: '', phoneError: null, phoneLoading: false,
        abhaNumber: '', abhaVerifying: false, abhaError: null,
        name: '', gender: '', existingPatient: false,
        selectedProblem: null, customComplaint: '',
        selectedDoctor: null,
        recommendedDocs: [], otherDocs: [], doctorsLoading: false,
        loading: false, error: null, result: null,
        countdown: 20, timer: null,

        problems: [
            { id: 'general',  icon: '🩺', label: 'Checkup',      hindi: 'जांच',      marathi: 'तपासणी',    konkani: 'तपासणी',     complaint: 'general checkup' },
            { id: 'fever',    icon: '🤒', label: 'Fever/Cold',    hindi: 'बुखार',     marathi: 'ताप/सर्दी', konkani: 'ताप/शेळ',    complaint: 'fever and cold' },
            { id: 'stomach',  icon: '🤢', label: 'Stomach',       hindi: 'पेट दर्द',  marathi: 'पोटदुखी',   konkani: 'पोटदुखी',    complaint: 'stomach pain digestion' },
            { id: 'heart',    icon: '❤️', label: 'Heart/BP',      hindi: 'दिल/बीपी',  marathi: 'हृदय/बीपी', konkani: 'काळीज/बीपी', complaint: 'heart problem blood pressure' },
            { id: 'bone',     icon: '🦴', label: 'Bone/Joint',    hindi: 'हड्डी/जोड़', marathi: 'हाड/सांधे', konkani: 'हाड/सांदो',  complaint: 'bone joint pain' },
            { id: 'skin',     icon: '🧴', label: 'Skin',          hindi: 'त्वचा',     marathi: 'त्वचा',     konkani: 'कातडी',      complaint: 'skin rash itching' },
            { id: 'eye',      icon: '👁️', label: 'Eye',           hindi: 'आंख',       marathi: 'डोळे',      konkani: 'दोळे',       complaint: 'eye vision problem' },
            { id: 'ent',      icon: '👂', label: 'Ear/Nose',      hindi: 'कान/नाक',   marathi: 'कान/नाक',   konkani: 'कान/नाक',    complaint: 'ear nose throat' },
            { id: 'dental',   icon: '🦷', label: 'Dental',        hindi: 'दांत',       marathi: 'दात',       konkani: 'दांत',       complaint: 'dental tooth pain' },
            { id: 'child',    icon: '👶', label: 'Child',          hindi: 'बच्चा',     marathi: 'बालक',      konkani: 'भुरगें',     complaint: 'child pediatric' },
            { id: 'women',    icon: '🤰', label: 'Women',         hindi: 'महिला',     marathi: 'स्त्री',    konkani: 'बायल',       complaint: 'women health gynecology' },
            { id: 'diabetes', icon: '💉', label: 'Diabetes',       hindi: 'शुगर',      marathi: 'मधुमेह',    konkani: 'शुगर',       complaint: 'diabetes sugar checkup' },
            { id: 'head',     icon: '🤕', label: 'Headache',      hindi: 'सिरदर्द',   marathi: 'डोकेदुखी',  konkani: 'तकलीदुखी',   complaint: 'headache migraine' },
            { id: 'breathing',icon: '😮‍💨', label: 'Breathing',    hindi: 'सांस',      marathi: 'श्वास',     konkani: 'श्वास',      complaint: 'breathing difficulty' },
            { id: 'allergy',  icon: '🤧', label: 'Allergy',       hindi: 'एलर्जी',    marathi: 'अॅलर्जी',   konkani: 'अॅलर्जी',    complaint: 'allergy reaction' },
            { id: 'other',    icon: '❓', label: 'Other',          hindi: 'अन्य',      marathi: 'इतर',       konkani: 'हेर',        complaint: 'other' },
        ],

        async checkPhone() {
            this.phoneError = null;
            let cleaned = this.phone.replace(/\D/g, '');
            if (cleaned.length < 10) { this.phoneError = 'Enter 10 digit number'; return; }
            this.phone = cleaned.slice(-10);
            this.phoneLoading = true;
            try {
                const res = await fetch('{{ route("kiosk.check-phone") }}?phone=' + this.phone, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.exists) { this.name = data.name; this.gender = data.gender || ''; this.existingPatient = true; this.step = 3; }
                else { this.existingPatient = false; this.step = 2; }
            } catch { this.step = 2; }
            finally { this.phoneLoading = false; }
        },

        async verifyAbha() {
            this.abhaError = null;
            let cleaned = this.abhaNumber.replace(/\D/g, '');
            if (cleaned.length < 14) { this.abhaError = 'Enter 14 digit ABHA number'; return; }
            cleaned = cleaned.slice(0, 14);
            this.abhaVerifying = true;
            try {
                const res = await fetch('{{ route("kiosk.verify-abha") }}?abha=' + cleaned, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.exists || data.profile) {
                    const profile = data.profile || {};
                    this.name = profile.name || data.name || '';
                    this.gender = profile.gender || data.gender || '';
                    this.phone = profile.phone || data.phone || '';
                    this.existingPatient = !!data.exists;
                    this.step = 3;
                } else {
                    this.abhaError = data.message || 'ABHA number not found. Please check and try again.';
                }
            } catch {
                this.abhaError = 'Verification failed. Please try again or use phone number.';
            }
            finally { this.abhaVerifying = false; }
        },

        pickProblem(p) { this.selectedProblem = p; },

        async loadMatchingDoctors() {
            this.doctorsLoading = true;
            this.selectedDoctor = null;
            this.recommendedDocs = [];
            this.otherDocs = [];
            const complaint = this.selectedProblem.id === 'other' ? this.customComplaint : this.selectedProblem.complaint;
            try {
                const res = await fetch('{{ route("kiosk.match-doctors") }}?complaint=' + encodeURIComponent(complaint), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.recommendedDocs = data.recommended || [];
                this.otherDocs = data.others || [];
                // Auto-select the recommended doctor with shortest queue
                if (this.recommendedDocs.length) {
                    const best = [...this.recommendedDocs].sort((a,b) => a.queue - b.queue)[0];
                    this.selectedDoctor = best;
                }
            } catch {}
            this.doctorsLoading = false;
        },

        doEmergency() {
            if (!this.phone || this.phone.replace(/\D/g,'').length < 10) { this.name = 'Emergency Walk-in'; this.phone = '0000000000'; }
            if (!this.name) this.name = 'Emergency Walk-in';
            this.selectedProblem = { id: 'emergency', complaint: 'emergency urgent' };
            this.step = 4;
            this.loadMatchingDoctors().then(() => {
                // Auto-select first available and submit
                if (this.recommendedDocs.length) this.selectedDoctor = this.recommendedDocs[0];
                else if (this.otherDocs.length) this.selectedDoctor = this.otherDocs[0];
                if (this.selectedDoctor) this.submitRegistration();
            });
        },

        async submitRegistration() {
            this.loading = true; this.error = null;
            const complaint = this.selectedProblem?.id === 'other' ? (this.customComplaint || 'general consultation') : (this.selectedProblem?.complaint || 'general consultation');
            try {
                const res = await fetch('{{ route("kiosk.register.process") }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({
                        name: this.name, phone: this.phone, gender: this.gender || null,
                        complaint: complaint, language: 'hi',
                        is_emergency: this.selectedProblem?.id === 'emergency',
                        doctor_id: this.selectedDoctor?.id || null,
                        abha_number: this.abhaNumber ? this.abhaNumber.replace(/\D/g, '').slice(0, 14) : null,
                    }),
                });
                const data = await res.json();
                if (res.ok && data.success) { this.result = data; }
                else { this.error = data.message || (data.errors ? Object.values(data.errors).flat().join('. ') : 'Failed'); }
            } catch { this.error = 'Something went wrong.'; }
            finally { this.loading = false; }
        },

        startAutoReset() {
            this.countdown = 20;
            this.timer = setInterval(() => { this.countdown--; if (this.countdown <= 0) { clearInterval(this.timer); window.location.href = '{{ route("kiosk.index") }}'; } }, 1000);
        },
    };
}
</script>
@endpush
