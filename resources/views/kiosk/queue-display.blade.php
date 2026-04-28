<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Queue Display - MedOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-900 text-white antialiased" x-data="queueDisplay()" x-init="init()">

    {{-- Hospital branding --}}
    <header class="bg-slate-800 border-b border-slate-700 px-8 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-blue-500 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ config('app.hospital_name', 'MedOS Hospital') }}</h1>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-white" x-text="currentTime"></p>
                <p class="text-sm text-slate-400" x-text="currentDate"></p>
            </div>
        </div>
    </header>

    {{-- Queue heading --}}
    <div class="px-8 py-4 bg-slate-800/50">
        <h2 class="text-3xl font-bold text-center text-white">Patient Queue</h2>
    </div>

    {{-- Doctor queues grid --}}
    <main class="p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <template x-for="doctor in doctors" :key="doctor.id">
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden">
                    {{-- Doctor header --}}
                    <div class="bg-slate-700 px-6 py-4">
                        <h3 class="text-xl font-bold text-white" x-text="doctor.name"></h3>
                        <p class="text-sm text-slate-400" x-text="doctor.department"></p>
                    </div>

                    {{-- Current patient --}}
                    <div class="px-6 py-4 border-b border-slate-700">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Now Serving</p>
                        <template x-if="doctor.current">
                            <div class="flex items-center gap-4 p-4 bg-green-500/20 border border-green-500/40 rounded-xl">
                                <div class="w-16 h-16 bg-green-500 text-white rounded-xl flex items-center justify-center text-2xl font-black" x-text="doctor.current.token"></div>
                                <div>
                                    <p class="text-2xl font-bold text-green-400" x-text="doctor.current.token"></p>
                                    <p class="text-sm text-slate-400" x-text="doctor.current.name"></p>
                                </div>
                            </div>
                        </template>
                        <template x-if="!doctor.current">
                            <div class="p-4 bg-slate-700/50 rounded-xl text-center">
                                <p class="text-lg text-slate-500">--</p>
                            </div>
                        </template>
                    </div>

                    {{-- Waiting --}}
                    <div class="px-6 py-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Up Next</p>
                        <div class="space-y-2">
                            <template x-for="patient in doctor.waiting.slice(0, 3)" :key="patient.token">
                                <div class="flex items-center gap-3 p-3 bg-slate-700/50 rounded-lg">
                                    <div class="w-12 h-12 bg-slate-600 text-white rounded-lg flex items-center justify-center text-lg font-bold" x-text="patient.token"></div>
                                    <div>
                                        <p class="text-base font-medium text-slate-300" x-text="patient.token"></p>
                                        <p class="text-sm text-slate-500" x-text="patient.name"></p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="doctor.waiting.length === 0">
                                <p class="text-sm text-slate-600 text-center py-2">No patients waiting</p>
                            </template>
                            <template x-if="doctor.waiting.length > 3">
                                <p class="text-sm text-slate-500 text-center">+ <span x-text="doctor.waiting.length - 3"></span> more waiting</p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="doctors.length === 0">
            <div class="text-center py-20">
                <p class="text-2xl text-slate-600">No active queues at this time</p>
            </div>
        </template>
    </main>

    {{-- Footer --}}
    <footer class="fixed bottom-0 inset-x-0 bg-slate-800 border-t border-slate-700 px-8 py-3">
        <div class="flex items-center justify-between text-sm text-slate-500">
            <span>Auto-refreshes every 30 seconds</span>
            <div class="flex items-center gap-4">
                <span>Last updated: <span x-text="lastUpdated"></span></span>
                <a href="{{ route('kiosk.index') }}" class="text-blue-400 hover:text-blue-300">Kiosk</a>
            </div>
        </div>
    </footer>

    <script>
    function queueDisplay() {
        return {
            currentTime: '',
            currentDate: '',
            lastUpdated: '',
            // TODO: fetch from API
            doctors: @json($doctors ?? []),

            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);

                // Load placeholder data if empty
                if (this.doctors.length === 0) {
                    this.doctors = [
                        {
                            id: 1, name: 'Dr. Sarah Ahmed', department: 'General Medicine',
                            current: { token: 'A-05', name: 'Aisha Khan' },
                            waiting: [
                                { token: 'A-06', name: 'Rahul M.' },
                                { token: 'A-07', name: 'Fatima S.' },
                                { token: 'A-08', name: 'John D.' },
                            ]
                        },
                        {
                            id: 2, name: 'Dr. Raj Patel', department: 'Cardiology',
                            current: { token: 'B-03', name: 'Mohammed Ali' },
                            waiting: [
                                { token: 'B-04', name: 'Sara J.' },
                                { token: 'B-05', name: 'Priya S.' },
                            ]
                        },
                        {
                            id: 3, name: 'Dr. Fatima Al-Hassan', department: 'Pediatrics',
                            current: { token: 'C-02', name: 'Ahmad Jr.' },
                            waiting: [
                                { token: 'C-03', name: 'Zara K.' },
                            ]
                        },
                        {
                            id: 4, name: 'Dr. John Smith', department: 'Orthopedics',
                            current: null,
                            waiting: [
                                { token: 'D-01', name: 'Rajesh P.' },
                                { token: 'D-02', name: 'Amina H.' },
                                { token: 'D-03', name: 'Vikram S.' },
                                { token: 'D-04', name: 'Noor A.' },
                            ]
                        },
                    ];
                }

                this.lastUpdated = new Date().toLocaleTimeString();

                // Auto-refresh every 30 seconds
                setInterval(() => this.refreshQueue(), 30000);
            },

            updateClock() {
                const now = new Date();
                this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                this.currentDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            },

            async refreshQueue() {
                try {
                    const res = await fetch('/api/queue/display');
                    if (res.ok) {
                        const data = await res.json();
                        this.doctors = data.doctors || data;
                    }
                } catch(e) {
                    // Silent fail - keep showing stale data
                    console.log('Queue refresh failed, will retry in 30s');
                }
                this.lastUpdated = new Date().toLocaleTimeString();
            },
        };
    }
    </script>
</body>
</html>
