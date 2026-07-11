@extends('layouts.app')
@section('title', 'Immunization')
@section('page-title', 'Immunization')

@php
use App\Modules\Vaccination\Models\Vaccine;
use App\Modules\Vaccination\Models\PatientVaccination;
@endphp

@section('content')
<div x-data="vax()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Doses given today</p><p class="text-2xl font-bold text-slate-800">{{ $counts['given_today'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Total doses recorded</p><p class="text-2xl font-bold text-blue-600">{{ $counts['total'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">AEFI reported</p><p class="text-2xl font-bold {{ $counts['aefi'] ? 'text-red-600' : 'text-slate-800' }}">{{ $counts['aefi'] }}</p></div>
    </div>

    @if(! $patient)
        {{-- Patient picker --}}
        <div class="max-w-xl bg-white rounded-xl border border-slate-200 p-6 mb-6" x-data="vaxSearch()">
            <label class="block text-sm font-semibold text-slate-700 mb-1">Open a patient's immunization record</label>
            <p class="text-xs text-slate-500 mb-3">See the due schedule computed from date of birth, record doses, and print a certificate.</p>
            <input type="text" x-model="q" @input.debounce.300ms="search()" class="input-field" placeholder="Search patient by name or phone…" autocomplete="off">
            <div class="mt-2 divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden" x-show="results.length" style="display:none">
                <template x-for="p in results" :key="p.id">
                    <a :href="'/vaccination?patient=' + p.id" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></a>
                </template>
            </div>
        </div>

        {{-- Recent administrations --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Recent doses given</h4></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200"><tr>
                        <th class="table-header">Patient</th><th class="table-header">Vaccine</th><th class="table-header text-center">Dose</th><th class="table-header">Date</th><th class="table-header">Batch</th><th class="table-header">Given by</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($records as $r)
                        <tr class="{{ $r->has_aefi ? 'bg-red-50/40' : '' }}">
                            <td class="px-4 py-2 text-sm text-slate-800">{{ $r->patient?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-slate-700">{{ $r->vaccine?->name ?? '—' }}@if($r->has_aefi)<span class="ml-1 text-xs text-red-600">⚠ AEFI</span>@endif</td>
                            <td class="px-4 py-2 text-sm text-slate-600 text-center">{{ $r->dose_number }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ optional($r->given_date)->format('M d, Y') }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $r->batch_number ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $r->given_by_name ?? '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">No doses recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Vaccine master / National schedule --}}
        <div x-data="{ modal:false, form:{}, open(v){ this.form = v ? {...v} : {id:'',name:'',code:'',category:'routine',route:'im',total_doses:1,dose_interval_days:'',is_active:true}; this.modal=true; } }">
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Vaccine Master & National Schedule</h4><p class="text-xs text-slate-400">{{ $vaccines->count() }} vaccines. Scheduled ones drive the DOB-based due list.</p></div>
                    <button type="button" @click="open(null)" class="btn-secondary text-sm">+ Add vaccine</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200"><tr>
                            <th class="table-header">Vaccine</th><th class="table-header">Category</th><th class="table-header">Route</th><th class="table-header text-center">Doses</th><th class="table-header">Schedule (age at dose)</th><th class="table-header text-right"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($vaccines as $vac)
                            <tr class="{{ $vac->is_active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2 text-sm text-slate-800">{{ $vac->name }}<span class="text-xs text-slate-400"> {{ $vac->code }}</span></td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ Vaccine::CATEGORIES[$vac->category] ?? $vac->category }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">{{ Vaccine::ROUTES[$vac->route] ?? $vac->route }}</td>
                                <td class="px-4 py-2 text-sm text-slate-600 text-center">{{ $vac->total_doses }}</td>
                                <td class="px-4 py-2 text-xs text-slate-500">
                                    @if($vac->isScheduled())
                                        {{ collect($vac->age_schedule)->map(fn($s) => $s['label'])->implode(' · ') }}
                                    @else
                                        <span class="text-slate-400">On-demand</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right"><button type="button" @click="open({ id: @js($vac->id), name: @js($vac->name), code: @js($vac->code ?? ''), category: @js($vac->category), route: @js($vac->route), total_doses: {{ $vac->total_doses }}, dose_interval_days: {{ $vac->dose_interval_days ?: "''" }}, is_active: {{ $vac->is_active ? 'true' : 'false' }} })" class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <x-modal show="modal" title-expr="form.id ? 'Edit Vaccine' : 'Add Vaccine'" max="lg">
                <form method="POST" :action="form.id ? '/vaccination/vaccines/' + form.id : '{{ route('web.vaccination.vaccines.store') }}'" class="space-y-4">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="form.name" required class="input-field"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Code</label><input type="text" name="code" x-model="form.code" class="input-field"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="form.category" class="input-field">@foreach(Vaccine::CATEGORIES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Route</label><select name="route" x-model="form.route" class="input-field">@foreach(Vaccine::ROUTES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Total doses</label><input type="number" name="total_doses" x-model="form.total_doses" min="1" required class="input-field"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Dose interval (days) <span class="text-slate-400 font-normal">on-demand only</span></label><input type="number" name="dose_interval_days" x-model="form.dose_interval_days" min="1" class="input-field"></div>
                    </div>
                    <template x-if="form.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
                    <div class="flex justify-end gap-2 pt-2"><button type="button" @click="modal=false" class="btn-secondary text-sm">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
                </form>
            </x-modal>
        </div>
    @else
        {{-- Patient immunization record --}}
        @php
            $dob = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth) : null;
            $ageLabel = $dob ? $dob->diff(now())->format('%yy %mm') : ($patient->age_approximate ? $patient->age_approximate.'y (approx)' : 'DOB unknown');
        @endphp
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">{{ $patient->name }}</h3>
                <p class="text-sm text-slate-500">{{ $patient->phone }} · {{ $ageLabel }}@if($dob) · DOB {{ $dob->format('d M Y') }}@endif</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('web.vaccination.certificate', $patient->id) }}" target="_blank" class="text-sm text-blue-600 hover:text-blue-800">Certificate ↗</a>
                <button type="button" @click="openRecord()" class="btn-primary text-sm">+ Record dose</button>
                <a href="{{ route('web.vaccination.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Change patient</a>
            </div>
        </div>

        @unless($dob)
            <div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">This patient has no date of birth on file, so the age-based schedule can't be computed. Doses can still be recorded manually.</div>
        @endunless

        @if($dob)
        {{-- schedule summary --}}
        <div class="grid grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-slate-200 p-3"><p class="text-xs text-slate-500">Overdue</p><p class="text-xl font-bold text-red-600">{{ $scheduleSummary['overdue'] }}</p></div>
            <div class="bg-white rounded-xl border border-slate-200 p-3"><p class="text-xs text-slate-500">Due (next 30d)</p><p class="text-xl font-bold text-amber-600">{{ $scheduleSummary['due'] }}</p></div>
            <div class="bg-white rounded-xl border border-slate-200 p-3"><p class="text-xs text-slate-500">Upcoming</p><p class="text-xl font-bold text-slate-600">{{ $scheduleSummary['upcoming'] }}</p></div>
            <div class="bg-white rounded-xl border border-slate-200 p-3"><p class="text-xs text-slate-500">Given</p><p class="text-xl font-bold text-green-600">{{ $scheduleSummary['given'] }}</p></div>
        </div>

        {{-- DOB-driven schedule --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Immunization Schedule — due from date of birth</h4></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200"><tr>
                        <th class="table-header">Vaccine</th><th class="table-header">Dose (age)</th><th class="table-header">Due date</th><th class="table-header">Status</th><th class="table-header text-right"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($schedule as $row)
                        @php
                            $badge = ['overdue'=>'bg-red-100 text-red-700','due'=>'bg-amber-100 text-amber-700','upcoming'=>'bg-slate-100 text-slate-600','given'=>'bg-green-100 text-green-700'][$row['status']];
                            $rowbg = $row['status']==='overdue' ? 'bg-red-50/40' : ($row['status']==='due' ? 'bg-amber-50/30' : '');
                        @endphp
                        <tr class="{{ $rowbg }}">
                            <td class="px-4 py-2.5 text-sm text-slate-800">{{ $row['vaccine'] }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">Dose {{ $row['dose'] }} <span class="text-slate-400">· {{ $row['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-sm text-slate-700">{{ $row['due_date']->format('d M Y') }}</td>
                            <td class="px-4 py-2.5">
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $badge }}">{{ ucfirst($row['status']) }}</span>
                                @if($row['status']==='given')<span class="block text-xs text-slate-400 mt-0.5">on {{ optional($row['given_date'])->format('d M Y') }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if($row['status'] !== 'given')
                                <button type="button" @click="openRecord({ vaccine_id: @js($row['vaccine_id']), dose_number: {{ $row['dose'] }}, route: @js($row['route']) })" class="text-xs font-semibold text-blue-600 hover:text-blue-800">Record →</button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No scheduled vaccines apply.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Given history --}}
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Dose history ({{ $history->count() }})</h4></div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200"><tr>
                        <th class="table-header">Vaccine</th><th class="table-header text-center">Dose</th><th class="table-header">Date</th><th class="table-header">Route / Site</th><th class="table-header">Batch / Mfr</th><th class="table-header">AEFI</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($history as $h)
                        <tr>
                            <td class="px-4 py-2 text-sm text-slate-800">{{ $h->vaccine?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600 text-center">{{ $h->dose_number }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ optional($h->given_date)->format('d M Y') }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ Vaccine::ROUTES[$h->route] ?? $h->route ?? '—' }}<span class="block">{{ PatientVaccination::SITES[$h->site] ?? '' }}</span></td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ $h->batch_number ?? '—' }}<span class="block text-slate-400">{{ $h->manufacturer }}</span></td>
                            <td class="px-4 py-2 text-xs">@if($h->has_aefi)<span class="text-red-600" title="{{ $h->aefi_notes }}">⚠ {{ \Illuminate\Support\Str::limit($h->aefi_notes, 30) ?: 'Reported' }}</span>@else<span class="text-slate-300">—</span>@endif</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-sm text-slate-400">No doses recorded for this patient.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Record dose modal --}}
        <x-modal show="recordModal" title="Record Dose" max="2xl">
            <form method="POST" action="{{ route('web.vaccination.record') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Vaccine</label>
                        <select name="vaccine_id" x-model="rec.vaccine_id" @change="onVaccine()" required class="input-field">
                            <option value="">Select…</option>
                            @foreach($vaccines->where('is_active', true) as $vac)<option value="{{ $vac->id }}">{{ $vac->name }}</option>@endforeach
                        </select>
                    </div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Dose number</label><input type="number" name="dose_number" x-model="rec.dose_number" min="1" required class="input-field"></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Given date</label><input type="date" name="given_date" value="{{ now()->toDateString() }}" required class="input-field"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Route</label><select name="route" x-model="rec.route" class="input-field">@foreach(Vaccine::ROUTES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Site</label><select name="site" class="input-field"><option value="">—</option>@foreach(PatientVaccination::SITES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Batch / lot no.</label><input type="text" name="batch_number" maxlength="60" class="input-field"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Manufacturer</label><input type="text" name="manufacturer" maxlength="120" class="input-field"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Expiry date</label><input type="date" name="expiry_date" class="input-field"></div>
                </div>
                <div class="rounded-lg border border-slate-200 p-3" x-data="{ aefi:false }">
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="has_aefi" value="1" x-model="aefi" class="rounded border-slate-300"><span class="text-slate-700 font-medium">Adverse event following immunization (AEFI)</span></label>
                    <div x-show="aefi" style="display:none" class="mt-2"><input type="text" name="aefi_notes" maxlength="500" class="input-field" placeholder="Describe reaction (e.g. fever, local swelling, anaphylaxis)"></div>
                </div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Notes</label><input type="text" name="notes" maxlength="500" class="input-field"></div>
                <div class="flex justify-end gap-2 pt-2"><button type="button" @click="recordModal=false" class="btn-secondary text-sm">Cancel</button><button type="submit" class="btn-primary" :disabled="!rec.vaccine_id" :class="!rec.vaccine_id ? 'opacity-40' : ''">Record dose</button></div>
            </form>
        </x-modal>
    @endif
</div>

@push('scripts')
<script>
function vaxSearch() {
    return {
        q: '', results: [],
        async search() {
            if (this.q.trim().length < 2) { this.results = []; return; }
            try { const r = await fetch('/ajax/patients?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); this.results = r.ok ? await r.json() : []; }
            catch (e) { this.results = []; }
        },
    };
}
function vax() {
    return {
        recordModal: false,
        rec: { vaccine_id: '', dose_number: 1, route: 'im' },
        vaccines: @js($vaccines->where('is_active', true)->map(fn($v)=>['id'=>$v->id,'route'=>$v->route])->values()),
        openRecord(prefill) {
            this.rec = prefill ? { vaccine_id: prefill.vaccine_id, dose_number: prefill.dose_number, route: prefill.route || 'im' } : { vaccine_id: '', dose_number: 1, route: 'im' };
            this.recordModal = true;
        },
        onVaccine() {
            const v = this.vaccines.find(x => x.id === this.rec.vaccine_id);
            if (v) this.rec.route = v.route;
        },
    };
}
</script>
@endpush
@endsection
