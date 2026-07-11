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

    {{-- Staff-only quick action (hidden on public/TV view) --}}
    @auth
    <a href="{{ url('/admin/queue') }}" style="position:fixed; bottom:5rem; right:1.5rem;" class="z-50 px-5 py-3 rounded-full bg-blue-600 hover:bg-blue-700 text-white font-semibold shadow-xl text-sm">+ Add to Queue</a>
    @endauth

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
                                <div class="px-4 py-2 bg-green-500 text-white rounded-xl text-2xl font-black whitespace-nowrap flex-shrink-0" x-text="doctor.current.token"></div>
                                <p class="text-lg font-medium text-slate-200 truncate min-w-0" x-text="doctor.current.name"></p>
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
                                    <div class="px-3 py-2 bg-slate-600 text-white rounded-lg text-base font-bold whitespace-nowrap flex-shrink-0" x-text="patient.token"></div>
                                    <p class="text-base font-medium text-slate-300 truncate min-w-0" x-text="patient.name"></p>
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
            doctors: @json($doctors ?? []),

            init() {
                this.updateClock();
                setInterval(() => this.updateClock(), 1000);
                this.lastUpdated = new Date().toLocaleTimeString();

                // Auto-refresh from the live queue every 30 seconds
                setInterval(() => this.refreshQueue(), 30000);
            },

            updateClock() {
                const now = new Date();
                this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                this.currentDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            },

            async refreshQueue() {
                try {
                    const res = await fetch('{{ route('kiosk.queue-display.json') }}', { headers: { 'Accept': 'application/json' } });
                    if (res.ok) {
                        const data = await res.json();
                        this.doctors = data.doctors || [];
                    }
                } catch(e) {
                    // Keep showing the last successful snapshot until the next tick.
                }
                this.lastUpdated = new Date().toLocaleTimeString();
            },
        };
    }
    </script>
</body>
</html>
