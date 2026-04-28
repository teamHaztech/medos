<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $doctor->name }} — Room Display</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white antialiased" x-data="roomDisplay()" x-init="init()">

    {{-- Tap to activate audio --}}
    <div x-show="!audioEnabled" x-transition @click="enableAudio()"
        class="fixed inset-0 z-50 bg-slate-900/95 flex flex-col items-center justify-center cursor-pointer">
        <svg class="w-20 h-20 text-blue-400 mb-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
        <p class="text-2xl font-bold text-white mb-2">Tap to Enable Announcements</p>
        <p class="text-slate-400">Touch anywhere to activate</p>
        <p class="text-sm text-slate-500 mt-3">स्क्रीन टच करें · स्क्रीन टच करा · स्क्रीन हाताळात · اضغط على الشاشة</p>
    </div>

    {{-- Header --}}
    <header class="bg-white/5 backdrop-blur border-b border-white/10 px-6 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center text-xl font-black">
                    {{ strtoupper(substr(explode(' ', $doctor->name)[1] ?? $doctor->name, 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">{{ $doctor->name }}</h1>
                    <p class="text-sm text-blue-300">{{ $doctor->department }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold text-white tabular-nums" x-text="currentTime"></p>
                <p class="text-xs text-slate-400" x-text="currentDate"></p>
            </div>
        </div>
    </header>

    <main class="p-6">

        {{-- NOW SERVING — full width hero --}}
        <div class="mb-6">
            <template x-if="currentPatient">
                <div class="bg-gradient-to-r from-green-500/20 to-emerald-500/10 border border-green-500/30 rounded-2xl p-6 flex items-center gap-6">
                    <div class="flex-shrink-0 text-center">
                        <p class="text-xs font-bold text-green-400 uppercase tracking-widest mb-1">Now Serving</p>
                        <div class="w-24 h-24 bg-green-500 rounded-2xl flex items-center justify-center shadow-lg shadow-green-500/30">
                            <p class="text-2xl font-black text-white" x-text="currentPatient.token"></p>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-3xl font-bold text-white" x-text="currentPatient.name"></p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                            <span class="text-sm text-green-300">In Consultation</span>
                            <template x-if="currentPatient.urgency === 'emergency'">
                                <span class="px-2 py-0.5 bg-red-500 text-white rounded text-xs font-bold ml-2">EMERGENCY</span>
                            </template>
                            <template x-if="currentPatient.urgency === 'urgent'">
                                <span class="px-2 py-0.5 bg-amber-500 text-white rounded text-xs font-bold ml-2">PRIORITY</span>
                            </template>
                            <template x-if="currentPatient.isReferral">
                                <span class="px-2 py-0.5 bg-orange-500 text-white rounded text-xs font-bold ml-2">REFERRED</span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="!currentPatient">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6 text-center">
                    <p class="text-slate-500 text-lg">Waiting for next patient...</p>
                    <p class="text-slate-600 text-sm mt-1">अगले मरीज़ का इंतज़ार · पुढील रुग्णाची वाट · फुडल्या पेशंटाची वाट</p>
                </div>
            </template>
        </div>

        {{-- NEXT UP + WAITING --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Next Up (highlighted) --}}
            <div>
                <p class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-3">Next Up / अगला</p>
                <template x-if="waitingList.length > 0">
                    <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-5 text-center">
                        <div class="w-20 h-20 bg-blue-500 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg shadow-blue-500/20">
                            <p class="text-xl font-black text-white" x-text="waitingList[0].token"></p>
                        </div>
                        <p class="text-2xl font-bold text-white" x-text="waitingList[0].name"></p>
                        <p class="text-sm text-blue-300 mt-1">Please be ready / तैयार रहें</p>
                        <div class="flex items-center justify-center gap-2 mt-2">
                            <template x-if="waitingList[0].urgency === 'emergency'">
                                <span class="px-2 py-0.5 bg-red-500 text-white rounded text-xs font-bold">EMERGENCY</span>
                            </template>
                            <template x-if="waitingList[0].isReferral">
                                <span class="px-2 py-0.5 bg-orange-500 text-white rounded text-xs font-bold">REF</span>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="waitingList.length === 0">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-5 text-center">
                        <p class="text-slate-500">No one waiting</p>
                    </div>
                </template>
            </div>

            {{-- Waiting Queue --}}
            <div class="lg:col-span-2">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Queue / कतार / रांग</p>
                    <p class="text-xs text-slate-500"><span x-text="waitingList.length"></span> waiting · <span x-text="doneList.length"></span> done</p>
                </div>

                <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
                    <template x-if="waitingList.length > 1">
                        <div class="divide-y divide-white/5">
                            <template x-for="(patient, idx) in waitingList.slice(1)" :key="patient.token">
                                <div class="flex items-center gap-4 px-5 py-3">
                                    <div class="w-8 h-8 bg-slate-700 rounded-lg flex items-center justify-center text-sm font-bold text-slate-300 flex-shrink-0" x-text="idx + 2"></div>
                                    <div class="w-16 text-center">
                                        <span class="text-sm font-bold text-slate-300" x-text="patient.token"></span>
                                    </div>
                                    <p class="text-base font-medium text-slate-200 flex-1" x-text="patient.name"></p>
                                    <div class="flex gap-1 flex-shrink-0">
                                        <span x-show="patient.urgency==='emergency'" class="px-1.5 py-0.5 bg-red-500/20 text-red-400 rounded text-[10px] font-bold">EMRG</span>
                                        <span x-show="patient.urgency==='urgent'" class="px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-[10px] font-bold">PRI</span>
                                        <span x-show="patient.isReferral" class="px-1.5 py-0.5 bg-orange-500/20 text-orange-400 rounded text-[10px] font-bold">REF</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="waitingList.length <= 1">
                        <div class="px-5 py-8 text-center">
                            <p class="text-slate-600">No more patients in queue</p>
                        </div>
                    </template>
                </div>

                {{-- Progress --}}
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex-1 bg-white/10 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full transition-all" :style="'width:' + (allPatients.length ? doneList.length / allPatients.length * 100 : 0) + '%'"></div>
                    </div>
                    <span class="text-xs text-slate-500 tabular-nums" x-text="doneList.length + '/' + allPatients.length + ' done'"></span>
                </div>
            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="fixed bottom-0 inset-x-0 bg-white/5 border-t border-white/10 px-6 py-2">
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                Live · Refreshes every 10s
            </span>
            <span>Last updated: <span x-text="lastUpdated"></span></span>
        </div>
    </footer>

    <script>
    function roomDisplay() {
        return {
            allPatients: @json($appointments),
            previousCurrent: null,
            audioEnabled: false,
            audioCtx: null,
            speechQueue: [],
            announcing: false,
            currentTime: '', currentDate: '', lastUpdated: '',

            get currentPatient() { return this.allPatients.find(p => p.status === 'in_progress') || null; },
            get waitingList() { return this.allPatients.filter(p => p.status === 'checked_in'); },
            get doneList() { return this.allPatients.filter(p => p.status === 'completed'); },

            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);
                this.previousCurrent = this.currentPatient?.token || null;
                this.lastUpdated = new Date().toLocaleTimeString();
                setInterval(() => this.refresh(), 10000);
            },

            enableAudio() {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
                if ('speechSynthesis' in window) { const w = new SpeechSynthesisUtterance(''); w.volume = 0; speechSynthesis.speak(w); }
                this.playBeep();
                this.audioEnabled = true;
                setTimeout(() => this.speak('Room display activated.', 'en-GB', 0.8, null), 1000);
            },

            updateClock() {
                const now = new Date();
                this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                this.currentDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            },

            async refresh() {
                // Don't refresh while announcing — prevents re-trigger
                if (this.announcing) return;

                try {
                    const res = await fetch(window.location.href);
                    const html = await res.text();
                    const match = html.match(/allPatients:\s*(\[.*?\]),\s*previousCurrent/s);
                    if (match) {
                        const newData = JSON.parse(match[1]);
                        const newCurrent = newData.find(p => p.status === 'in_progress');
                        if (newCurrent && newCurrent.token !== this.previousCurrent) {
                            this.previousCurrent = newCurrent.token;
                            this.allPatients = newData;
                            if (this.audioEnabled) this.announce(newCurrent);
                        } else {
                            this.allPatients = newData;
                        }
                    }
                } catch(e) {}
                this.lastUpdated = new Date().toLocaleTimeString();
            },

            announce(patient) {
                const name = patient.name;
                const token = patient.token;

                this.announcing = true;
                if ('speechSynthesis' in window) speechSynthesis.cancel();

                // Combine each language into ONE utterance (no breaking mid-sentence)
                this.speechQueue = [];

                this.speechQueue.push({ beep: true, delay: 0 });

                // English — one full sentence
                this.speechQueue.push({
                    text: `Attention please. Token number ${token}. .... ${name}. .... Please come in. The doctor is ready for you.`,
                    lang: 'en-GB', rate: 0.8, delay: 1500
                });

                // Hindi — one full sentence
                this.speechQueue.push({
                    text: `ध्यान दीजिए। टोकन नंबर ${token}। .... ${name}। .... कृपया अंदर आइए। डॉक्टर साहब आपका इंतज़ार कर रहे हैं।`,
                    lang: 'hi-IN', rate: 0.85, delay: 1500
                });

                // Marathi — one full sentence
                this.speechQueue.push({
                    text: `कृपया लक्ष द्या। टोकन नंबर ${token}। .... ${name}। .... कृपया आत या। डॉक्टर तुमची वाट पाहत आहेत।`,
                    lang: 'hi-IN', rate: 0.85, delay: 1500
                });

                // Konkani — one full sentence
                this.speechQueue.push({
                    text: `लक्ष दिवचें। टोकन नंबर ${token}। .... ${name}। .... उपकार करून भितर येयात। डॉक्टर तुमकां रावताती।`,
                    lang: 'hi-IN', rate: 0.85, delay: 1500
                });

                // Arabic — one full sentence
                this.speechQueue.push({
                    text: `انتباه من فضلك. رقم التذكرة ${token}. .... ${name}. .... يرجى الدخول. الطبيب في انتظارك.`,
                    lang: 'ar-SA', rate: 0.85, delay: 1500
                });

                this.speechQueue.push({ beep: true, delay: 1000 });
                this.playSpeechQueue();
            },

            playSpeechQueue() {
                if (this.speechQueue.length === 0) {
                    this.announcing = false;
                    return;
                }
                const item = this.speechQueue.shift();
                setTimeout(() => {
                    if (item.beep) {
                        this.playBeep();
                        setTimeout(() => this.playSpeechQueue(), 800);
                    } else {
                        this.speak(item.text, item.lang, item.rate, () => {
                            this.playSpeechQueue();
                        });
                    }
                }, item.delay || 0);
            },

            speak(text, lang, rate, onEnd) {
                if (!('speechSynthesis' in window)) { if (onEnd) onEnd(); return; }

                const u = new SpeechSynthesisUtterance(text);
                u.lang = lang;
                u.rate = rate || 0.85;
                u.pitch = 1;
                u.volume = 1;

                const voices = speechSynthesis.getVoices();
                const match = voices.find(v => v.lang.startsWith(lang.split('-')[0]) && v.name.toLowerCase().includes('female'))
                    || voices.find(v => v.lang.startsWith(lang.split('-')[0]))
                    || voices.find(v => v.lang === lang);
                if (match) u.voice = match;

                let done = false;
                // Safety: if onend never fires (Chrome bug), force move after 15s
                const timer = setTimeout(() => {
                    if (!done) { done = true; if (onEnd) onEnd(); }
                }, 15000);

                u.onend = () => { if (!done) { done = true; clearTimeout(timer); if (onEnd) onEnd(); } };
                u.onerror = () => { if (!done) { done = true; clearTimeout(timer); if (onEnd) onEnd(); } };

                speechSynthesis.speak(u);

                // Chrome workaround: keep speechSynthesis alive by resuming every 5s
                const keepAlive = setInterval(() => {
                    if (done) { clearInterval(keepAlive); return; }
                    if (speechSynthesis.speaking) speechSynthesis.resume();
                }, 5000);
            },

            playBeep() {
                if (!this.audioCtx) return;
                try {
                    const ctx = this.audioCtx;
                    if (ctx.state === 'suspended') ctx.resume();
                    [0, 0.25, 0.5].forEach(delay => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.connect(gain); gain.connect(ctx.destination);
                        osc.frequency.value = 880; osc.type = 'sine';
                        gain.gain.setValueAtTime(0.4, ctx.currentTime + delay);
                        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + delay + 0.2);
                        osc.start(ctx.currentTime + delay); osc.stop(ctx.currentTime + delay + 0.2);
                    });
                } catch(e) {}
            },
        };
    }
    </script>
</body>
</html>
