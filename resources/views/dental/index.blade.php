@extends('layouts.app')
@section('title', 'Dental')
@section('page-title', 'Dental')

@php
use App\Modules\Dental\Models\DentalChart;
use App\Modules\Dental\Models\DentalTreatment;
use App\Modules\Dental\Models\DentalProcedure;
$cur = \App\Modules\Core\Services\RegionService::currency();
@endphp

@section('content')
@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

@if(! $patient)
    {{-- Patient picker --}}
    <div class="max-w-xl bg-white rounded-xl border border-slate-200 p-6 mb-6" x-data="dentalSearch()">
        <label class="block text-sm font-semibold text-slate-700 mb-1">Open a patient</label>
        <p class="text-xs text-slate-500 mb-3">Open a patient's odontogram, treatment plan and visit history.</p>
        <input type="text" x-model="q" @input.debounce.300ms="search()" class="input-field" placeholder="Search patient by name or phone…" autocomplete="off">
        <div class="mt-2 divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden" x-show="results.length" style="display:none">
            <template x-for="p in results" :key="p.id">
                <a :href="'/dental?patient=' + p.id" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></a>
            </template>
        </div>
    </div>

    {{-- Fee schedule (procedure master) --}}
    <div x-data="{ modal:false, form:{} , open(p){ this.form = p ? {...p} : {id:'',code:'',name:'',category:'general',default_fee:'',is_active:true}; this.modal=true; } }">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Fee Schedule — dental procedures</h4>
                    <p class="text-xs text-slate-400">The price list dentists chart against. {{ $procedures->count() }} procedures.</p>
                </div>
                <button type="button" @click="open(null)" class="btn-secondary text-sm">+ Add procedure</button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200"><tr>
                        <th class="table-header">Code</th><th class="table-header">Procedure</th><th class="table-header">Category</th><th class="table-header text-right">Fee</th><th class="table-header text-right"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($procedures as $p)
                        <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                            <td class="px-4 py-2 text-xs font-mono text-slate-500">{{ $p->code }}</td>
                            <td class="px-4 py-2 text-sm text-slate-800">{{ $p->name }}</td>
                            <td class="px-4 py-2 text-xs text-slate-500">{{ DentalProcedure::CATEGORIES[$p->category] ?? $p->category }}</td>
                            <td class="px-4 py-2 text-sm text-slate-700 text-right">{{ $cur }}{{ number_format($p->default_fee, 2) }}</td>
                            <td class="px-4 py-2 text-right"><button type="button" @click="open({ id: @js($p->id), code: @js($p->code), name: @js($p->name), category: @js($p->category), default_fee: {{ (float) $p->default_fee }}, is_active: {{ $p->is_active ? 'true' : 'false' }} })" class="text-xs font-medium text-blue-600 hover:text-blue-800">Edit</button></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No procedures in the fee schedule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-modal show="modal" title-expr="form.id ? 'Edit Procedure' : 'Add Procedure'" max="lg">
            <form method="POST" :action="form.id ? '/dental/procedure/' + form.id : '{{ route('web.dental.procedure.store') }}'" class="space-y-4">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Code</label><input type="text" name="code" x-model="form.code" required class="input-field"></div>
                    <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="form.name" required class="input-field"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="form.category" class="input-field">@foreach(DentalProcedure::CATEGORIES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Default fee ({{ $cur }})</label><input type="number" step="0.01" name="default_fee" x-model="form.default_fee" required class="input-field"></div>
                </div>
                <template x-if="form.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
                <div class="flex justify-end gap-2 pt-2"><button type="button" @click="modal=false" class="btn-secondary text-sm">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-modal>
    </div>
@else
    <div x-data="dental()">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">{{ $patient->name }}</h3>
                <p class="text-sm text-slate-500">{{ $patient->phone }}</p>
            </div>
            <a href="{{ route('web.dental.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Change patient</a>
        </div>

        {{-- Tabs --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" @click="tab='chart'" :class="tab==='chart'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Odontogram</button>
            <button type="button" @click="tab='plan'" :class="tab==='plan'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Treatment Plan</button>
            <button type="button" @click="tab='visits'" :class="tab==='visits'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Visit Notes ({{ $visits->count() }})</button>
        </div>

        {{-- ODONTOGRAM --}}
        <form x-show="tab==='chart'" method="POST" action="{{ route('web.dental.chart.save') }}" class="bg-white rounded-xl border border-slate-200 p-5">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            <input type="hidden" name="dentition" :value="dentition">
            <input type="hidden" name="tooth_status" :value="JSON.stringify(status)">
            <input type="hidden" name="notes" :value="chartNotes">

            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="flex gap-1 text-xs">
                    <button type="button" @click="dentition='adult'" :class="dentition==='adult'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">Adult</button>
                    <button type="button" @click="dentition='pediatric'" :class="dentition==='pediatric'?'bg-blue-600 text-white':'bg-slate-100 text-slate-600'" class="px-3 py-1 rounded-lg font-semibold">Pediatric</button>
                </div>
                <button type="submit" class="btn-primary text-sm">Save chart</button>
            </div>

            <div class="flex flex-wrap gap-1.5 mb-4">
                <template x-for="(label, key) in statuses" :key="key">
                    <button type="button" @click="paint=key" :class="paint===key ? 'ring-2 ring-blue-500' : ''" class="flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-200 text-xs">
                        <span class="w-3 h-3 rounded-full border border-slate-300" :style="'background:'+color(key)"></span>
                        <span x-text="label"></span>
                    </button>
                </template>
            </div>

            <div class="space-y-2 overflow-x-auto">
                <div class="flex gap-1 justify-center min-w-max">
                    <template x-for="t in teeth().upper" :key="t">
                        <button type="button" @click="setTooth(t)" class="w-9 h-11 rounded border border-slate-300 flex flex-col items-center justify-center" :style="'background:'+color(status[t])" :title="statuses[status[t]] || 'Healthy'">
                            <span class="font-semibold text-slate-700" style="font-size:10px" x-text="t"></span>
                            <span x-show="status[t]==='missing'" class="text-red-500 text-xs leading-none">×</span>
                        </button>
                    </template>
                </div>
                <div class="flex gap-1 justify-center min-w-max">
                    <template x-for="t in teeth().lower" :key="t">
                        <button type="button" @click="setTooth(t)" class="w-9 h-11 rounded border border-slate-300 flex flex-col items-center justify-center" :style="'background:'+color(status[t])" :title="statuses[status[t]] || 'Healthy'">
                            <span class="font-semibold text-slate-700" style="font-size:10px" x-text="t"></span>
                            <span x-show="status[t]==='missing'" class="text-red-500 text-xs leading-none">×</span>
                        </button>
                    </template>
                </div>
            </div>
            <p class="text-xs text-slate-400 mt-3">Pick a status above, then click teeth to mark them. Click a marked tooth again to clear it.</p>
            <div class="mt-3"><label class="block text-xs font-semibold text-slate-600 mb-1">Chart notes</label><textarea x-model="chartNotes" rows="2" class="input-field" placeholder="General oral findings, hygiene, occlusion…"></textarea></div>
        </form>

        {{-- TREATMENT PLAN --}}
        <div x-show="tab==='plan'" style="display:none">
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Planned / in-progress</p><p class="text-xl font-bold text-amber-600">{{ $cur }}{{ number_format($plan['planned'], 2) }}</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Completed</p><p class="text-xl font-bold text-green-600">{{ $cur }}{{ number_format($plan['completed'], 2) }}</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                    <div><p class="text-xs text-slate-500">Completed, unbilled</p><p class="text-xl font-bold text-blue-600">{{ $cur }}{{ number_format($plan['unbilled'], 2) }}</p></div>
                    @if($plan['unbilled_count'] > 0)
                    <form method="POST" action="{{ route('web.dental.bill') }}" onsubmit="return confirm('Create a bill for {{ $plan['unbilled_count'] }} completed procedure(s)?')">
                        @csrf<input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <button type="submit" class="btn-primary text-xs whitespace-nowrap">Bill {{ $plan['unbilled_count'] }} →</button>
                    </form>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Treatment Plan</h4></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200"><tr>
                            <th class="table-header">Tooth</th><th class="table-header">Procedure</th><th class="table-header text-right">Fee</th><th class="table-header">Status</th><th class="table-header"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($treatments as $t)
                            <tr>
                                <td class="px-4 py-2.5 text-sm text-slate-700">{{ $t->tooth_number ?? '—' }}<span class="block text-xs text-slate-400">{{ $t->surfaces }}</span></td>
                                <td class="px-4 py-2.5 text-sm text-slate-800">{{ $t->procedure }}<span class="block text-xs text-slate-400">{{ $t->notes }}@if($t->bill_id)<span class="text-green-600"> · billed</span>@endif</span></td>
                                <td class="px-4 py-2.5 text-sm text-slate-700 text-right">{{ $cur }}{{ number_format($t->cost, 2) }}</td>
                                <td class="px-4 py-2.5">
                                    @if($t->bill_id)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Completed · billed</span>
                                    @else
                                    <form method="POST" action="{{ route('web.dental.treatment.update', $t->id) }}">@csrf
                                        <select name="status" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white">
                                            @foreach(DentalTreatment::STATUSES as $k => $label)<option value="{{ $k }}" {{ $t->status === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                                        </select>
                                    </form>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @unless($t->bill_id)
                                    <form method="POST" action="{{ route('web.dental.treatment.delete', $t->id) }}" onsubmit="return confirm('Remove this treatment?')">@csrf<button type="submit" class="text-xs text-red-400 hover:text-red-600">Remove</button></form>
                                    @endunless
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No treatments planned.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add treatment from the fee schedule --}}
                <form method="POST" action="{{ route('web.dental.treatment.add') }}" class="p-4 border-t border-slate-200 grid grid-cols-2 sm:grid-cols-6 gap-2 items-end">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Tooth</label><input type="text" name="tooth_number" class="input-field" placeholder="e.g. 26"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Surfaces</label><input type="text" name="surfaces" maxlength="12" class="input-field" placeholder="MOD"></div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Procedure</label>
                        <select name="procedure_id" x-model="pickProc" @change="applyProc()" class="input-field">
                            <option value="">— custom —</option>
                            @foreach($procedures->where('is_active', true) as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $cur }}{{ number_format($p->default_fee, 0) }})</option>@endforeach
                        </select>
                        <input type="text" name="procedure" x-model="procName" required class="input-field mt-1" placeholder="Procedure name">
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Fee ({{ $cur }})</label><input type="number" step="0.01" name="cost" x-model="procCost" class="input-field"></div>
                    <div class="flex gap-2">
                        <select name="status" class="input-field">@foreach(DentalTreatment::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                        <button type="submit" class="btn-primary text-sm whitespace-nowrap">Add</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- VISIT NOTES --}}
        <div x-show="tab==='visits'" style="display:none">
            <div class="flex justify-end mb-3"><button type="button" @click="visitModal=true" class="btn-secondary text-sm">+ New visit note</button></div>
            <div class="space-y-3">
                @forelse($visits as $vz)
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $vz->visit_date->format('D, M d, Y') }}</p>
                        <p class="text-xs text-slate-400">{{ $vz->dentist_name }}</p>
                    </div>
                    @if($vz->chief_complaint)<p class="text-sm text-slate-700"><span class="text-slate-400">Complaint:</span> {{ $vz->chief_complaint }}</p>@endif
                    @if($vz->examination)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Exam:</span> {{ $vz->examination }}</p>@endif
                    @if($vz->procedures_done)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Done:</span> {{ $vz->procedures_done }}</p>@endif
                    @if($vz->advice)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Advice:</span> {{ $vz->advice }}</p>@endif
                    @if($vz->next_visit_date)<p class="text-xs text-blue-600 mt-2">Next visit: {{ $vz->next_visit_date->format('M d, Y') }}</p>@endif
                </div>
                @empty
                <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-400">No visit notes recorded yet.</div>
                @endforelse
            </div>

            <x-modal show="visitModal" title="New Visit Note" max="2xl">
                <form method="POST" action="{{ route('web.dental.visit.add') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Visit date</label><input type="date" name="visit_date" value="{{ now()->toDateString() }}" required class="input-field"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Chief complaint</label><input type="text" name="chief_complaint" maxlength="255" class="input-field" placeholder="e.g. Pain in lower left back tooth"></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Examination</label><textarea name="examination" rows="2" class="input-field" placeholder="Clinical findings"></textarea></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Procedures done today</label><textarea name="procedures_done" rows="2" class="input-field" placeholder="e.g. RCT access opening on 36, temporary restoration"></textarea></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Advice / prescription</label><textarea name="advice" rows="2" class="input-field" placeholder="Medication, home care, precautions"></textarea></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Next visit date</label><input type="date" name="next_visit_date" class="input-field"></div>
                    <div class="flex justify-end gap-2 pt-2"><button type="button" @click="visitModal=false" class="btn-secondary text-sm">Cancel</button><button type="submit" class="btn-primary">Save visit</button></div>
                </form>
            </x-modal>
        </div>
    </div>
@endif

@push('scripts')
<script>
function dentalSearch() {
    return {
        q: '', results: [],
        async search() {
            if (this.q.trim().length < 2) { this.results = []; return; }
            try { const r = await fetch('/ajax/patients?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); this.results = r.ok ? await r.json() : []; }
            catch (e) { this.results = []; }
        },
    };
}
function dental() {
    return {
        tab: 'chart',
        visitModal: false,
        dentition: @js($chart->dentition ?? 'adult'),
        status: @js((object)($chart?->tooth_status ?? [])),
        chartNotes: @js($chart->notes ?? ''),
        paint: 'caries',
        statuses: @js(DentalChart::STATUSES),
        _teeth: { adultUpper: @js(DentalChart::ADULT_UPPER), adultLower: @js(DentalChart::ADULT_LOWER), childUpper: @js(DentalChart::CHILD_UPPER), childLower: @js(DentalChart::CHILD_LOWER) },
        colors: { healthy:'#ffffff', caries:'#fecaca', filled:'#bfdbfe', crown:'#fde68a', root_canal:'#e9d5ff', implant:'#99f6e4', extract_planned:'#fed7aa', missing:'#e2e8f0' },
        procs: @js($procedures->where('is_active', true)->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'fee'=>(float)$p->default_fee])->values()),
        pickProc: '', procName: '', procCost: '',
        teeth() { return this.dentition === 'adult' ? { upper: this._teeth.adultUpper, lower: this._teeth.adultLower } : { upper: this._teeth.childUpper, lower: this._teeth.childLower }; },
        color(s) { return this.colors[s] || '#ffffff'; },
        setTooth(t) {
            if (this.status[t] === this.paint) { delete this.status[t]; } else { this.status[t] = this.paint; }
            this.status = { ...this.status };
        },
        applyProc() {
            const p = this.procs.find(x => x.id === this.pickProc);
            if (p) { this.procName = p.name; this.procCost = p.fee; }
        },
    };
}
</script>
@endpush
@endsection
