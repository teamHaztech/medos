@extends('layouts.app')
@section('title', 'Information Desk')
@section('page-title', 'Patient Information Desk')

@section('content')
<div class="max-w-3xl" x-data="infoDesk()">

    {{-- Smart inquiry box --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <label class="block text-sm font-semibold text-slate-700 mb-1">Inquiry</label>
        <p class="text-xs text-slate-500 mb-3">Type a patient's <strong>name</strong> or <strong>phone</strong>, or a <strong>token</strong> (e.g. PED-001) — the desk answers automatically.</p>
        <div class="relative">
            <input type="text" x-model="q" @input.debounce.300ms="search()" @keydown.enter.prevent="search()"
                   class="input-field text-base" placeholder="Search name, phone, or token…" autocomplete="off" autofocus>
            <p x-show="searching" style="display:none" class="text-xs text-slate-400 mt-1">Searching…</p>
        </div>

        {{-- Patient match list --}}
        <template x-if="result && result.type === 'patients'">
            <div class="mt-3">
                <template x-if="result.results.length === 0">
                    <p class="text-sm text-slate-400 py-2">No patient found. Try a different spelling or phone number.</p>
                </template>
                <div class="divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden" x-show="result.results.length">
                    <template x-for="p in result.results" :key="p.id">
                        <button type="button" @click="pick(p.id)" class="w-full flex items-center justify-between px-4 py-2.5 hover:bg-blue-50 text-left">
                            <span class="text-sm font-medium text-slate-800" x-text="p.name"></span>
                            <span class="text-xs text-slate-400" x-text="p.phone"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Token answer --}}
        <template x-if="result && result.type === 'token'">
            <div class="mt-3">
                <template x-if="!result.token">
                    <p class="text-sm text-slate-400 py-2">No active token matches “<span x-text="result.query"></span>”.</p>
                </template>
                <template x-if="result.token">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-blue-800" x-text="result.token.token"></span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white border border-blue-200 text-blue-700 capitalize" x-text="(result.token.status||'').replace('_',' ')"></span>
                        </div>
                        <p class="text-sm text-slate-700 mt-1"><span x-text="result.token.patient"></span> · <span x-text="result.token.doctor"></span> <span class="text-slate-400" x-text="result.token.department ? '('+result.token.department+')' : ''"></span></p>
                        <p class="text-xs text-slate-500 mt-0.5" x-text="result.token.time"></p>
                        <p x-show="result.token.position" class="text-sm font-semibold text-slate-800 mt-2">Now serving position — <span x-text="result.token.position"></span> in queue</p>
                        <button type="button" @click="pick(result.token.patient_id)" class="mt-2 text-xs font-medium text-blue-600 hover:text-blue-800">View full record →</button>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Full patient snapshot --}}
    <div x-show="loading" style="display:none" class="text-center text-sm text-slate-400 py-6">Loading record…</div>

    <template x-if="snapshot && snapshot.patient">
        <div class="mt-6 space-y-4">
            {{-- Identity --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800" x-text="snapshot.patient.name"></h3>
                        <p class="text-sm text-slate-500">
                            <span x-show="snapshot.patient.age !== null" x-text="snapshot.patient.age + ' yrs'"></span>
                            <span x-show="snapshot.patient.gender" x-text="' · ' + snapshot.patient.gender"></span>
                            <span x-show="snapshot.patient.blood_group" x-text="' · ' + snapshot.patient.blood_group"></span>
                        </p>
                        <p class="text-sm text-slate-500" x-text="snapshot.patient.phone"></p>
                        <p x-show="snapshot.patient.health_id" class="text-xs text-slate-400 mt-0.5" x-text="'Health ID: ' + snapshot.patient.health_id"></p>
                    </div>
                    <button type="button" @click="reset()" class="btn-secondary">New inquiry</button>
                </div>
                <p x-show="snapshot.last_visit" class="text-xs text-slate-400 mt-2" x-text="'Last visit: ' + snapshot.last_visit"></p>
            </div>

            {{-- Admission (if admitted) --}}
            <template x-if="snapshot.admission">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-700 mb-2">Currently Admitted</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm text-slate-700">
                        <p><span class="text-slate-500">Ward:</span> <span class="font-medium" x-text="snapshot.admission.ward || '—'"></span></p>
                        <p><span class="text-slate-500">Bed:</span> <span class="font-medium" x-text="snapshot.admission.bed || '—'"></span></p>
                        <p><span class="text-slate-500">Doctor:</span> <span class="font-medium" x-text="snapshot.admission.doctor || '—'"></span></p>
                        <p><span class="text-slate-500">Since:</span> <span class="font-medium" x-text="snapshot.admission.since || '—'"></span></p>
                    </div>
                    <p x-show="snapshot.admission.reason" class="text-xs text-slate-500 mt-2" x-text="'Reason: ' + snapshot.admission.reason"></p>
                </div>
            </template>

            {{-- Appointments + queue --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Appointments &amp; Queue</h4>
                <template x-if="snapshot.appointments.length === 0">
                    <p class="text-sm text-slate-400">No upcoming appointments.</p>
                </template>
                <div class="space-y-2">
                    <template x-for="(a, i) in snapshot.appointments" :key="i">
                        <div class="flex items-center justify-between border border-slate-100 rounded-lg px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-slate-800 truncate"><span x-text="a.doctor || 'Doctor'"></span> <span class="text-slate-400 text-xs" x-text="a.department ? '· '+a.department : ''"></span></p>
                                <p class="text-xs text-slate-500"><span x-text="a.date"></span> at <span x-text="a.time"></span> <span x-show="a.token" class="text-slate-400" x-text="'· '+a.token"></span></p>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 capitalize" x-text="(a.status||'').replace('_',' ')"></span>
                                <p x-show="a.position" class="text-xs font-semibold text-blue-700 mt-1" x-text="'#'+a.position+' in queue'"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Billing --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Billing</h4>
                <template x-if="snapshot.billing.outstanding > 0">
                    <p class="text-sm"><span class="font-bold text-red-600" x-text="snapshot.billing.currency + Number(snapshot.billing.outstanding).toFixed(2)"></span> <span class="text-slate-500">outstanding across <span x-text="snapshot.billing.pending_bills"></span> bill(s)</span></p>
                </template>
                <template x-if="snapshot.billing.outstanding <= 0">
                    <p class="text-sm text-green-600 font-medium">No dues.</p>
                </template>
            </div>
        </div>
    </template>

    {{-- Doctor / department directory --}}
    <div class="mt-6" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-slate-800">
            <span x-text="open ? '▾' : '▸'"></span> Doctor &amp; Department Directory
        </button>
        <div x-show="open" style="display:none" class="mt-3 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            @if($departments->count())
            <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap gap-1.5">
                @foreach($departments as $dep)
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $dep }}</span>
                @endforeach
            </div>
            @endif
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Doctor</th>
                        <th class="table-header">Department</th>
                        <th class="table-header">Today</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($doctors as $d)
                    <tr>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $d->name }}<span class="text-xs text-slate-400 block">{{ $d->specialization }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ $d->department ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm">
                            @if($d->today)<span class="text-green-700">{{ $d->today }}</span>@else<span class="text-slate-400">Off today</span>@endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-sm text-slate-400">No doctors listed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function infoDesk() {
    return {
        q: '', searching: false, result: null, snapshot: null, loading: false,
        async search() {
            const q = this.q.trim();
            this.snapshot = null;
            if (q.length < 2) { this.result = null; return; }
            this.searching = true;
            try {
                const r = await fetch('/ajax/info-desk?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                this.result = r.ok ? await r.json() : null;
            } catch (e) { this.result = null; }
            this.searching = false;
        },
        async pick(id) {
            if (!id) return;
            this.loading = true; this.snapshot = null; this.result = null;
            try {
                const r = await fetch('/ajax/info-desk/patient/' + id, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                this.snapshot = r.ok ? await r.json() : null;
            } catch (e) { this.snapshot = null; }
            this.loading = false;
        },
        reset() { this.q = ''; this.result = null; this.snapshot = null; },
    };
}
</script>
@endpush
@endsection
