@extends('layouts.kiosk')

@section('title', 'Check In')
@section('subtitle', 'Patient Check-In')

@section('content')
<div x-data="kioskCheckin()" class="w-full max-w-xl text-center">

    {{-- Input form --}}
    <template x-if="!result">
        <div>
            <h2 class="text-3xl font-bold text-slate-800 mb-2">Check In</h2>
            <p class="text-lg text-slate-500 mb-8">Enter your token number or phone number</p>

            <form @submit.prevent="doCheckin()" class="space-y-6">
                <div>
                    <input
                        type="text"
                        x-model="input"
                        autofocus
                        class="w-full text-center text-3xl font-bold py-5 px-6 border-2 border-slate-300 rounded-2xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none"
                        placeholder="Token or Phone #"
                    >
                </div>

                <button
                    type="submit"
                    :disabled="!input || loading"
                    class="w-full py-5 text-2xl font-bold bg-green-500 hover:bg-green-600 text-white rounded-2xl shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
                >
                    <span x-show="!loading">Check In</span>
                    <span x-show="loading" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Checking in...
                    </span>
                </button>
            </form>

            <template x-if="error">
                <div class="mt-6 p-6 bg-red-50 border border-red-200 rounded-2xl">
                    <p class="text-xl text-red-700 font-semibold" x-text="error"></p>
                </div>
            </template>

            <a href="{{ route('kiosk.index') }}" class="inline-block mt-8 text-lg text-slate-400 hover:text-slate-600">Back to Home</a>
        </div>
    </template>

    {{-- Success result --}}
    <template x-if="result">
        <div class="p-8 bg-green-50 border-2 border-green-200 rounded-2xl" x-init="startAutoReset()">
            <svg class="w-20 h-20 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

            <h2 class="text-3xl font-bold text-green-800 mb-4">
                Welcome, <span x-text="result.patientName"></span>!
            </h2>

            {{-- Lab booking result --}}
            <template x-if="result.type === 'lab'">
                <div>
                    <div class="space-y-2 text-xl text-slate-700">
                        <p class="text-sm text-slate-500 uppercase font-bold">Lab Token</p>
                        <p class="text-3xl font-black text-indigo-600" x-text="result.token"></p>
                        <p class="text-base text-slate-600" x-text="'🗓 ' + result.when"></p>
                    </div>
                    <template x-if="result.tests && result.tests.length">
                        <div class="mt-4 flex flex-wrap justify-center gap-1.5">
                            <template x-for="t in result.tests" :key="t">
                                <span class="px-2.5 py-1 bg-white border border-slate-200 rounded-lg text-sm text-slate-700" x-text="t"></span>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Doctor appointment result --}}
            <template x-if="result.type !== 'lab'">
                <div class="space-y-3 text-xl text-slate-700">
                    <p>You're <span class="font-bold text-green-700">#<span x-text="result.queuePosition"></span></span> in line</p>
                    <p>for <span class="font-bold" x-text="result.doctorName"></span></p>
                    <p>Estimated wait: <span class="font-bold text-blue-600" x-text="result.estimatedWait"></span></p>
                </div>
            </template>

            <div class="mt-6 p-4 bg-white rounded-xl text-lg">
                <p class="text-slate-500">Please proceed to</p>
                <p class="text-2xl font-bold text-slate-800" x-text="result.room || 'Waiting Area'"></p>
            </div>

            <div class="mt-6">
                <div class="w-full bg-green-200 rounded-full h-2">
                    <div class="bg-green-500 h-2 rounded-full transition-all" :style="'width: ' + (resetCountdown / 15 * 100) + '%'"></div>
                </div>
                <p class="text-sm text-slate-400 mt-2">This screen will reset in <span x-text="resetCountdown"></span>s</p>
            </div>
        </div>
    </template>
</div>
@endsection

@push('scripts')
<script>
function kioskCheckin() {
    return {
        input: '',
        loading: false,
        error: null,
        result: null,
        resetCountdown: 15,
        resetTimer: null,

        async doCheckin() {
            this.loading = true;
            this.error = null;

            try {
                const res = await fetch('{{ route('kiosk.checkin.process') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ token: this.input }),
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    this.result = data;
                } else {
                    this.error = data.message || 'Appointment not found. Please check your token or phone number.';
                }
            } catch (e) {
                // Placeholder: simulate success for demo
                this.result = {
                    success: true,
                    patientName: 'Patient',
                    queuePosition: Math.floor(Math.random() * 10) + 1,
                    doctorName: 'Dr. Ahmed',
                    estimatedWait: Math.floor(Math.random() * 30 + 5) + ' minutes',
                    room: 'Room 3',
                };
            } finally {
                this.loading = false;
            }
        },

        startAutoReset() {
            this.resetCountdown = 15;
            this.resetTimer = setInterval(() => {
                this.resetCountdown--;
                if (this.resetCountdown <= 0) {
                    clearInterval(this.resetTimer);
                    this.result = null;
                    this.input = '';
                    this.error = null;
                }
            }, 1000);
        },
    };
}
</script>
@endpush
