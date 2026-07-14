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
                <button type="button" @click="open(null)" class="btn-primary text-sm">+ Add procedure</button>
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
                            <td class="px-4 py-2 text-right"><button type="button" @click="open({ id: @js($p->id), code: @js($p->code), name: @js($p->name), category: @js($p->category), default_fee: {{ (float) $p->default_fee }}, is_active: {{ $p->is_active ? 'true' : 'false' }} })" class="text-sm font-medium text-blue-600 hover:text-blue-800">Edit</button></td>
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
            <button type="button" @click="tab='toolkit'" :class="tab==='toolkit'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Chairside Toolkit</button>
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

        {{-- CHAIRSIDE TOOLKIT --}}
        <div x-show="tab==='toolkit'" style="display:none" x-data="dentalCalc()">
            {{-- Local anaesthetic maximum-dose calculator --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
                <div class="px-5 py-3 border-b border-slate-200">
                    <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Local Anaesthetic — Maximum Safe Dose</h4>
                    <p class="text-xs text-slate-400">Weight-based limit &amp; number of cartridges. Critical for children &amp; small adults.</p>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Patient weight (kg)</label>
                            <input type="number" min="1" max="200" x-model.number="weight" class="input-field">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Anaesthetic agent</label>
                            <select x-model="agent" class="input-field">
                                <template x-for="(a, key) in agents" :key="key">
                                    <option :value="key" x-text="a.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-slate-200 p-4 text-center">
                            <p class="text-xs text-slate-500">By weight</p>
                            <p class="text-lg font-bold text-slate-700"><span x-text="r(byWeight)"></span><span class="text-xs font-normal text-slate-400"> mg</span></p>
                            <p class="text-xs text-slate-400"><span x-text="ag.perKg"></span> mg/kg</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4 text-center">
                            <p class="text-xs text-slate-500">Absolute cap</p>
                            <p class="text-lg font-bold text-slate-700"><span x-text="ag.cap"></span><span class="text-xs font-normal text-slate-400"> mg</span></p>
                            <p class="text-xs" :class="capped ? 'text-amber-600 font-semibold' : 'text-slate-400'" x-text="capped ? 'cap applies' : ''"></p>
                        </div>
                        <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-4 text-center">
                            <p class="text-xs text-blue-600 font-semibold">Max dose</p>
                            <p class="text-2xl font-bold text-blue-700"><span x-text="r(maxMg)"></span><span class="text-xs font-normal text-blue-400"> mg</span></p>
                        </div>
                        <div class="rounded-xl border-2 border-green-200 bg-green-50 p-4 text-center">
                            <p class="text-xs text-green-700 font-semibold">Max cartridges</p>
                            <p class="text-2xl font-bold text-green-700" x-text="maxCart"></p>
                            <p class="text-xs text-green-600/70"><span x-text="ag.mgCart"></span> mg / 1.8 ml</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400 mt-4 leading-relaxed">Guidance only. Reduce the limit for cardiac, hepatic, elderly and pregnant patients, and always cross-check the current formulary. Cartridge = 1.8 ml. Cartridge count is rounded down to the safe whole number.</p>
                </div>
            </div>

            {{-- Reference: LA agents --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
                <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Local Anaesthetics — Quick Reference</h4></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200"><tr>
                            <th class="table-header">Agent</th><th class="table-header text-right">Max mg/kg</th><th class="table-header text-right">Absolute cap</th><th class="table-header text-right">mg / cartridge</th><th class="table-header">Pulpal duration</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr><td class="px-4 py-2 text-slate-800">Lidocaine 2% + epi 1:100k</td><td class="px-4 py-2 text-right text-slate-600">7</td><td class="px-4 py-2 text-right text-slate-600">500 mg</td><td class="px-4 py-2 text-right text-slate-600">36 mg</td><td class="px-4 py-2 text-slate-500">~60–90 min</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Articaine 4% + epi 1:100k</td><td class="px-4 py-2 text-right text-slate-600">7</td><td class="px-4 py-2 text-right text-slate-600">500 mg</td><td class="px-4 py-2 text-right text-slate-600">72 mg</td><td class="px-4 py-2 text-slate-500">~60–75 min</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Mepivacaine 3% plain</td><td class="px-4 py-2 text-right text-slate-600">6.6</td><td class="px-4 py-2 text-right text-slate-600">400 mg</td><td class="px-4 py-2 text-right text-slate-600">54 mg</td><td class="px-4 py-2 text-slate-500">~20–40 min (no epi)</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Prilocaine 4% plain</td><td class="px-4 py-2 text-right text-slate-600">8</td><td class="px-4 py-2 text-right text-slate-600">600 mg</td><td class="px-4 py-2 text-right text-slate-600">72 mg</td><td class="px-4 py-2 text-slate-500">~40–60 min</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Bupivacaine 0.5% + epi</td><td class="px-4 py-2 text-right text-slate-600">1.3</td><td class="px-4 py-2 text-right text-slate-600">90 mg</td><td class="px-4 py-2 text-right text-slate-600">9 mg</td><td class="px-4 py-2 text-slate-500">~90–180 min (long)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Reference: common dental prescriptions --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
                <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Common Dental Prescriptions — Adult</h4></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200"><tr>
                            <th class="table-header">Drug</th><th class="table-header">Indication</th><th class="table-header">Typical adult dose</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr><td class="px-4 py-2 text-slate-800">Amoxicillin</td><td class="px-4 py-2 text-slate-500">Odontogenic infection</td><td class="px-4 py-2 text-slate-600">500 mg TDS × 5 days</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Amoxicillin + Clavulanate</td><td class="px-4 py-2 text-slate-500">Spreading / resistant</td><td class="px-4 py-2 text-slate-600">625 mg TDS × 5 days</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Metronidazole</td><td class="px-4 py-2 text-slate-500">Anaerobic (± amoxicillin)</td><td class="px-4 py-2 text-slate-600">400 mg TDS × 5 days</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Azithromycin</td><td class="px-4 py-2 text-slate-500">Penicillin allergy</td><td class="px-4 py-2 text-slate-600">500 mg OD × 3 days</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Clindamycin</td><td class="px-4 py-2 text-slate-500">Penicillin allergy / severe</td><td class="px-4 py-2 text-slate-600">300 mg TDS × 5 days</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Ibuprofen</td><td class="px-4 py-2 text-slate-500">Pain / inflammation</td><td class="px-4 py-2 text-slate-600">400 mg TDS after food</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Paracetamol</td><td class="px-4 py-2 text-slate-500">Pain (max 4 g/day)</td><td class="px-4 py-2 text-slate-600">500–1000 mg QDS</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Chlorhexidine 0.2%</td><td class="px-4 py-2 text-slate-500">Antiseptic mouth rinse</td><td class="px-4 py-2 text-slate-600">10 ml BD rinse</td></tr>
                            <tr><td class="px-4 py-2 text-slate-800">Amoxicillin (prophylaxis)</td><td class="px-4 py-2 text-slate-500">Endocarditis-risk cover</td><td class="px-4 py-2 text-slate-600">2 g single dose, 1 h pre-op</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Reference: FDI tooth notation --}}
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200"><h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">FDI Tooth Notation</h4></div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="space-y-1.5">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Permanent (adult)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">11–18</span> — upper right (Q1)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">21–28</span> — upper left (Q2)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">31–38</span> — lower left (Q3)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">41–48</span> — lower right (Q4)</p>
                    </div>
                    <div class="space-y-1.5">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Primary (child)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">51–55</span> — upper right (Q5)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">61–65</span> — upper left (Q6)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">71–75</span> — lower left (Q7)</p>
                        <p class="text-slate-600"><span class="font-mono font-semibold text-slate-800">81–85</span> — lower right (Q8)</p>
                    </div>
                    <div class="sm:col-span-2 pt-2 border-t border-slate-100">
                        <p class="text-xs text-slate-500"><span class="font-semibold text-slate-600">Surfaces:</span> M mesial · O occlusal · D distal · B buccal · L lingual · I incisal — number from the midline, tooth 1 (central incisor) to 8 (third molar).</p>
                    </div>
                </div>
            </div>
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
function dentalCalc() {
    return {
        weight: 60,
        agent: 'lidocaine',
        agents: {
            lidocaine:   { label: 'Lidocaine 2% + epi 1:100k', perKg: 7,   cap: 500, mgCart: 36 },
            articaine:   { label: 'Articaine 4% + epi 1:100k', perKg: 7,   cap: 500, mgCart: 72 },
            mepivacaine: { label: 'Mepivacaine 3% plain',      perKg: 6.6, cap: 400, mgCart: 54 },
            prilocaine:  { label: 'Prilocaine 4% plain',       perKg: 8,   cap: 600, mgCart: 72 },
            bupivacaine: { label: 'Bupivacaine 0.5% + epi',    perKg: 1.3, cap: 90,  mgCart: 9  },
        },
        get w() { return parseFloat(this.weight) || 0; },
        get ag() { return this.agents[this.agent]; },
        get byWeight() { return this.ag.perKg * this.w; },
        get maxMg() { return Math.min(this.byWeight, this.ag.cap); },
        get capped() { return this.byWeight > this.ag.cap; },
        get maxCart() { return this.ag.mgCart ? Math.floor(this.maxMg / this.ag.mgCart) : 0; },
        r(n) { return Math.round(n); },
    };
}
</script>
@endpush
@endsection
