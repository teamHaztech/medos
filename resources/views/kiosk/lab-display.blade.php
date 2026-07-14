<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $label }} — {{ $hospital->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white antialiased" x-data="labDisplay()" x-init="init()">

    {{-- Tap to activate audio --}}
    <div x-show="!audioEnabled" x-transition @click="enableAudio()"
        class="fixed inset-0 z-50 bg-slate-900/95 flex flex-col items-center justify-center cursor-pointer">
        <svg class="w-20 h-20 text-cyan-400 mb-6 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/></svg>
        <p class="text-2xl font-bold text-white mb-2">Tap to Enable Announcements</p>
        <p class="text-slate-400">Touch anywhere to activate</p>
        <p class="text-sm text-slate-500 mt-3">स्क्रीन टच करें · स्क्रीन टच करा · स्क्रीन हाताळात · اضغط على الشاشة</p>
    </div>

    {{-- Header --}}
    <header class="bg-white/5 backdrop-blur border-b border-white/10 px-6 py-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-cyan-500 rounded-xl flex items-center justify-center text-2xl">🧪</div>
                <div>
                    <h1 class="text-xl font-bold text-white">{{ $label }}</h1>
                    <p class="text-sm text-cyan-300">{{ $hospital->name }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold text-white tabular-nums" x-text="currentTime"></p>
                <p class="text-xs text-slate-400" x-text="currentDate"></p>
            </div>
        </div>
    </header>

    <main class="p-6 pb-28">

        {{-- NOW SERVING --}}
        <div class="mb-6">
            <template x-if="currentPatient">
                <div class="bg-gradient-to-r from-cyan-500/20 to-teal-500/10 border border-cyan-500/30 rounded-2xl p-6 flex items-center gap-6">
                    <div class="flex-shrink-0 text-center">
                        <p class="text-xs font-bold text-cyan-400 uppercase tracking-widest mb-1">Now at Counter</p>
                        <div class="bg-cyan-500 rounded-2xl flex items-center justify-center shadow-lg shadow-cyan-500/30" style="min-width:6rem;height:6rem;padding:0 0.85rem">
                            <p class="text-2xl font-black text-white" style="white-space:nowrap" x-text="currentPatient.token"></p>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem">
                            <div>
                                <p class="text-3xl font-bold text-white" x-text="currentPatient.name"></p>
                                <p class="text-sm text-cyan-200 mt-1" x-text="currentPatient.tests"></p>
                            </div>
                            <button x-show="audioEnabled" @click="announce(currentPatient)" type="button"
                                title="Announce again"
                                style="flex-shrink:0;display:flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:.6rem;padding:.4rem .7rem;font-size:.8rem;font-weight:600;cursor:pointer">
                                🔊 <span>Announce</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-2 h-2 bg-cyan-400 rounded-full animate-pulse"></span>
                            <span class="text-sm text-cyan-300">Sample collection in progress</span>
                            <template x-if="currentPatient.urgency === 'emergency'">
                                <span class="px-2 py-0.5 bg-red-500 text-white rounded text-xs font-bold ml-2">STAT</span>
                            </template>
                            <template x-if="currentPatient.urgency === 'urgent'">
                                <span class="px-2 py-0.5 bg-amber-500 text-white rounded text-xs font-bold ml-2">URGENT</span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="!currentPatient">
                <div class="bg-white/5 border border-white/10 rounded-2xl p-6 text-center">
                    <p class="text-slate-500 text-lg">Waiting for next sample...</p>
                    <p class="text-slate-600 text-sm mt-1">अगले मरीज़ का इंतज़ार · पुढील रुग्णाची वाट</p>
                </div>
            </template>
        </div>

        {{-- NEXT UP + WAITING --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- Next Up --}}
            <div>
                <p class="text-xs font-bold text-cyan-400 uppercase tracking-widest mb-3">Next Up / अगला</p>
                <template x-if="waitingList.length > 0">
                    <div class="bg-cyan-500/10 border border-cyan-500/30 rounded-2xl p-5 text-center">
                        <div class="bg-cyan-500 rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-lg shadow-cyan-500/20" style="min-width:5rem;height:5rem;padding:0 0.85rem;width:fit-content">
                            <p class="text-xl font-black text-white" style="white-space:nowrap" x-text="waitingList[0].token"></p>
                        </div>
                        <p class="text-2xl font-bold text-white" x-text="waitingList[0].name"></p>
                        <p class="text-sm text-cyan-200 mt-1" x-text="waitingList[0].tests"></p>
                        <p class="text-sm text-cyan-300 mt-1">Please be ready / तैयार रहें</p>
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
                            <template x-for="(patient, idx) in waitingList.slice(1)" :key="patient.token + '-' + idx">
                                <div class="flex items-center gap-4 px-5 py-3">
                                    <div class="w-8 h-8 bg-slate-700 rounded-lg flex items-center justify-center text-sm font-bold text-slate-300 flex-shrink-0" x-text="idx + 2"></div>
                                    <div class="text-center flex-shrink-0" style="min-width:5rem">
                                        <span class="text-sm font-bold text-slate-300" style="white-space:nowrap" x-text="patient.token"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-base font-medium text-slate-200" x-text="patient.name"></p>
                                        <p class="text-xs text-slate-400 truncate" x-text="patient.tests"></p>
                                    </div>
                                    <div class="flex gap-1 flex-shrink-0">
                                        <span x-show="patient.urgency==='emergency'" class="px-1.5 py-0.5 bg-red-500/20 text-red-400 rounded text-xs font-bold">STAT</span>
                                        <span x-show="patient.urgency==='urgent'" class="px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded text-xs font-bold">URG</span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="waitingList.length <= 1">
                        <div class="px-5 py-8 text-center">
                            <p class="text-slate-600">No more samples in queue</p>
                        </div>
                    </template>
                </div>

                {{-- Progress --}}
                <div class="mt-4 flex items-center gap-3">
                    <div class="flex-1 bg-white/10 rounded-full h-2">
                        <div class="bg-cyan-500 h-2 rounded-full transition-all" :style="'width:' + (allPatients.length ? doneList.length / allPatients.length * 100 : 0) + '%'"></div>
                    </div>
                    <span class="text-xs text-slate-500 tabular-nums" x-text="doneList.length + '/' + allPatients.length + ' done'"></span>
                </div>
            </div>
        </div>
    </main>

    {{-- Footer --}}
    <footer class="fixed bottom-0 inset-x-0 bg-slate-900/95 backdrop-blur border-t border-white/10 px-6 py-2 z-40">
        <div class="flex items-center justify-between text-xs text-slate-500">
            <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-cyan-500 rounded-full animate-pulse"></span>
                Live · Refreshes every 10s
            </span>
            <span>{{ $label }} · {{ $hospital->name }}</span>
            <span>Last updated: <span x-text="lastUpdated"></span></span>
        </div>
    </footer>

    <script>
    function labDisplay() {
        return {
            allPatients: @json($orders),
            announcedTokens: [],
            audioEnabled: false,
            audioCtx: null,
            speechQueue: [],
            announcing: false,
            currentTime: '', currentDate: '', lastUpdated: '',

            get currentPatient() { return this.allPatients.find(p => p.status === 'in_progress') || null; },
            get waitingList() {
                const cur = this.currentPatient;
                return this.allPatients.filter(p => p !== cur && p.status !== 'completed');
            },
            get doneList() { return this.allPatients.filter(p => p.status === 'completed'); },

            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);
                this.announcedTokens = this.allPatients.filter(p => p.status === 'in_progress').map(p => p.token);
                this.lastUpdated = new Date().toLocaleTimeString();
                if ('speechSynthesis' in window) {
                    speechSynthesis.getVoices();
                    speechSynthesis.onvoiceschanged = () => speechSynthesis.getVoices();
                }
                setInterval(() => this.refresh(), 10000);
            },

            enableAudio() {
                this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
                if ('speechSynthesis' in window) { const w = new SpeechSynthesisUtterance(''); w.volume = 0; speechSynthesis.speak(w); }
                this.playBeep();
                this.audioEnabled = true;
                setTimeout(() => {
                    const cur = this.currentPatient;
                    if (cur) this.announce(cur);
                    else this.speak('Lab display activated.', 'en-GB', 0.8, null);
                }, 1000);
            },

            updateClock() {
                const now = new Date();
                this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                this.currentDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            },

            async refresh() {
                if (this.announcing) return;
                try {
                    const res = await fetch(window.location.href);
                    const html = await res.text();
                    const match = html.match(/allPatients:\s*(\[.*?\]),\s*announcedTokens/s);
                    if (match) {
                        const newData = JSON.parse(match[1]);
                        this.allPatients = newData;
                        const nowServing = newData.filter(p => p.status === 'in_progress');
                        const fresh = nowServing.find(p => !this.announcedTokens.includes(p.token));
                        if (fresh) {
                            this.announcedTokens.push(fresh.token);
                            if (this.audioEnabled) this.announce(fresh);
                        }
                        this.announcedTokens = this.announcedTokens.filter(t => nowServing.some(p => p.token === t));
                    }
                } catch(e) {}
                this.lastUpdated = new Date().toLocaleTimeString();
            },

            announce(patient) {
                const name = patient.name;
                const token = patient.token;
                this.announcing = true;
                if ('speechSynthesis' in window) speechSynthesis.cancel();
                this.speechQueue = [];
                this.speechQueue.push({ beep: true, delay: 0 });
                this.speechQueue.push({
                    text: `Attention please. Token number ${token}. .... ${name}. .... Please proceed to the sample collection counter.`,
                    lang: 'en-GB', rate: 0.8, delay: 1500
                });
                this.speechQueue.push({
                    text: `ध्यान दीजिए। टोकन नंबर ${token}। .... ${name}। .... कृपया सैंपल कलेक्शन काउंटर पर आइए।`,
                    lang: 'hi-IN', rate: 0.85, delay: 1500
                });
                this.speechQueue.push({
                    text: `कृपया लक्ष द्या। टोकन नंबर ${token}। .... ${name}। .... कृपया सॅंपल कलेक्शन काउंटरवर या।`,
                    lang: 'hi-IN', rate: 0.85, delay: 1500
                });
                this.speechQueue.push({
                    text: `انتباه من فضلك. رقم التذكرة ${token}. .... ${name}. .... يرجى التوجه إلى مكتب جمع العينات.`,
                    lang: 'ar-SA', rate: 0.85, delay: 1500
                });
                this.speechQueue.push({ beep: true, delay: 1000 });
                this.playSpeechQueue();
            },

            playSpeechQueue() {
                if (this.speechQueue.length === 0) { this.announcing = false; return; }
                const item = this.speechQueue.shift();
                setTimeout(() => {
                    if (item.beep) {
                        this.playBeep();
                        setTimeout(() => this.playSpeechQueue(), 800);
                    } else {
                        this.speak(item.text, item.lang, item.rate, () => this.playSpeechQueue());
                    }
                }, item.delay || 0);
            },

            speak(text, lang, rate, onEnd) {
                if (!('speechSynthesis' in window)) { if (onEnd) onEnd(); return; }
                const u = new SpeechSynthesisUtterance(text);
                u.lang = lang; u.rate = rate || 0.85; u.pitch = 1; u.volume = 1;
                const voices = speechSynthesis.getVoices();
                const match = voices.find(v => v.lang.startsWith(lang.split('-')[0]) && v.name.toLowerCase().includes('female'))
                    || voices.find(v => v.lang.startsWith(lang.split('-')[0]))
                    || voices.find(v => v.lang === lang);
                if (match) u.voice = match;
                let done = false;
                const timer = setTimeout(() => { if (!done) { done = true; if (onEnd) onEnd(); } }, 15000);
                u.onend = () => { if (!done) { done = true; clearTimeout(timer); if (onEnd) onEnd(); } };
                u.onerror = () => { if (!done) { done = true; clearTimeout(timer); if (onEnd) onEnd(); } };
                speechSynthesis.speak(u);
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
