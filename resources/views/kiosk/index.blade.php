@extends('layouts.kiosk')

@section('title', 'Welcome')
@section('subtitle', 'Self-Service Kiosk')

@section('content')
<div x-data="kioskHome()" class="w-full max-w-2xl text-center">

    {{-- Hospital selector (shown when multiple hospitals, none selected) --}}
    @if(!empty($showSelector) && $showSelector)
    <div class="mb-10">
        <h2 class="text-2xl font-bold text-slate-800 mb-6">Select Hospital</h2>
        <form method="POST" action="{{ route('kiosk.select-hospital') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4">
                @foreach($hospitals as $h)
                <button type="submit" name="hospital_id" value="{{ $h->id }}" class="flex items-center gap-4 p-6 bg-white border-2 border-slate-200 hover:border-blue-400 hover:bg-blue-50 rounded-2xl shadow-sm transition-all text-left">
                    <span class="text-3xl">{{ $h->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                    <div>
                        <p class="text-xl font-bold text-slate-800">{{ $h->name }}</p>
                        <p class="text-sm text-slate-500">{{ $h->city }}{{ $h->state ? ', ' . $h->state : '' }}</p>
                    </div>
                </button>
                @endforeach
            </div>
        </form>
    </div>
    @else

    {{-- Current date/time --}}
    <div class="mb-10">
        <p class="text-3xl font-bold text-slate-800" x-text="currentTime"></p>
        <p class="text-lg text-slate-500 mt-1" x-text="currentDate"></p>
    </div>

    {{-- Main action buttons --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-12">
        {{-- I have an appointment --}}
        <a href="{{ route('kiosk.checkin') }}" class="flex flex-col items-center justify-center p-10 bg-green-500 hover:bg-green-600 text-white rounded-2xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] min-h-[200px]">
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-2xl font-bold">I have an appointment</span>
            <span class="text-base mt-2 opacity-90">Check in with token or phone</span>
        </a>

        {{-- New patient --}}
        <a href="{{ route('kiosk.register') }}" class="flex flex-col items-center justify-center p-10 bg-blue-500 hover:bg-blue-600 text-white rounded-2xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] min-h-[200px]">
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
            <span class="text-2xl font-bold">New Patient</span>
            <span class="text-base mt-2 opacity-90">Start registration</span>
        </a>

        {{-- Lab test --}}
        <a href="{{ route('kiosk.lab') }}" class="flex flex-col items-center justify-center p-10 bg-indigo-500 hover:bg-indigo-600 text-white rounded-2xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] min-h-[200px]">
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            <span class="text-2xl font-bold">Lab Test / Scan</span>
            <span class="text-base mt-2 opacity-90">Book a test — no doctor needed</span>
        </a>

        {{-- Emergency --}}
        <a href="{{ route('kiosk.register', ['emergency' => 1]) }}" class="flex flex-col items-center justify-center p-10 bg-red-600 hover:bg-red-700 text-white rounded-2xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] min-h-[200px]">
            <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
            <span class="text-2xl font-bold">Emergency</span>
            <span class="text-base mt-2 opacity-90">Get seen immediately</span>
        </a>
    </div>

    {{-- Language selector --}}
    <div class="flex items-center justify-center gap-4">
        <span class="text-sm text-slate-400">Language:</span>
        <button @click="setLang('en')" :class="lang === 'en' ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-slate-100 text-slate-600'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">English</button>
        <button @click="setLang('hi')" :class="lang === 'hi' ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-slate-100 text-slate-600'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">हिंदी</button>
        <button @click="setLang('mr')" :class="lang === 'mr' ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-slate-100 text-slate-600'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">मराठी</button>
        <button @click="setLang('kok')" :class="lang === 'kok' ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-slate-100 text-slate-600'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">कोंकणी</button>
        <button @click="setLang('ar')" :class="lang === 'ar' ? 'bg-blue-100 text-blue-800 ring-2 ring-blue-300' : 'bg-slate-100 text-slate-600'" class="px-3 py-2 rounded-lg text-sm font-medium transition-colors">العربية</button>
    </div>

    {{-- Change hospital link (if multiple hospitals) --}}
    @if(isset($hospitals) && $hospitals->count() > 1 && isset($hospital))
    <div class="mt-6">
        <a href="{{ route('kiosk.index', ['reset_hospital' => 1]) }}" class="text-sm text-slate-400 hover:text-slate-600 underline">
            Change Hospital ({{ $hospital->name }})
        </a>
    </div>
    @endif

    @endif
</div>
@endsection

@push('scripts')
<script>
function kioskHome() {
    return {
        lang: 'en',
        currentTime: '',
        currentDate: '',

        init() {
            this.updateClock();
            setInterval(() => this.updateClock(), 1000);
        },

        updateClock() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            this.currentDate = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        },

        setLang(code) {
            this.lang = code;
            // TODO: store preference and reload with translated content
        },
    };
}
</script>
@endpush
