@extends('layouts.app')
@section('title', 'Ophthalmology')
@section('page-title', 'Ophthalmology')

@php
use App\Modules\Ophthalmology\Models\EyeProcedure;
use App\Modules\Ophthalmology\Models\EyeTreatment;
use App\Modules\Ophthalmology\Models\EyeExam;
$cur = \App\Modules\Core\Services\RegionService::currency();
@endphp

@section('content')
@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

@if(! $patient)
    {{-- Ophthalmologist's booked patients — today & upcoming. --}}
    @if($isEyePractitioner ?? false)
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between gap-2">
            <div>
                <h4 class="text-sm font-semibold text-slate-700">Booked Patients — today &amp; upcoming</h4>
                <p class="text-xs text-slate-400">Patients who booked an eye appointment with you. Open one to start the consultation.</p>
            </div>
            <a href="{{ route('web.admin.appointments', ['view' => 'upcoming']) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 whitespace-nowrap">All appointments →</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($appointments as $apt)
                @php
                    $st = is_object($apt->status) ? $apt->status->value : ($apt->status ?? '');
                    $when = \Illuminate\Support\Carbon::parse($apt->slot_start);
                    $badge = $st === 'checked_in' ? 'bg-green-100 text-green-700' : ($st === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700');
                @endphp
                <div class="flex items-center gap-3 px-5 py-3">
                    <div class="text-center shrink-0" style="min-width:4.75rem">
                        <p class="text-sm font-bold text-slate-700">{{ $when->format('g:i A') }}</p>
                        <p class="text-xs {{ $when->isToday() ? 'text-blue-600 font-semibold' : 'text-slate-400' }}">{{ $when->isToday() ? 'Today' : $when->format('d M') }}</p>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $apt->patient?->name ?? 'Patient' }}</p>
                        <p class="text-xs text-slate-400 truncate">{{ $apt->patient?->phone }}{{ $apt->notes ? ' · '.$apt->notes : '' }}</p>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium shrink-0 whitespace-nowrap {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</span>
                    @if($apt->patient_id)
                    <a href="{{ route('web.eye.index', ['patient' => $apt->patient_id]) }}" class="btn-primary text-sm whitespace-nowrap shrink-0">Start Consultation →</a>
                    @endif
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">
                    No eye appointments booked yet. Patients who book you (online, kiosk, or reception) appear here — or open any patient below to examine a walk-in.
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Patient picker --}}
    <div class="max-w-xl bg-white rounded-xl border border-slate-200 p-6 mb-6" x-data="eyeSearch()">
        <label class="block text-sm font-semibold text-slate-700 mb-1">Open a patient</label>
        <p class="text-xs text-slate-500 mb-3">Open a patient's eye exams, refraction / spectacle prescription and treatment plan.</p>
        <input type="text" x-model="q" @input.debounce.300ms="search()" class="input-field" placeholder="Search patient by name or phone…" autocomplete="off">
        <div class="mt-2 divide-y divide-slate-100 border border-slate-200 rounded-lg overflow-hidden" x-show="results.length" style="display:none">
            <template x-for="p in results" :key="p.id">
                <a :href="'/eye?patient=' + p.id" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></a>
            </template>
        </div>
    </div>

    {{-- Fee schedule (procedure master) --}}
    <div x-data="{ modal:false, form:{}, open(p){ this.form = p ? {...p} : {id:'',code:'',name:'',category:'general',default_fee:'',is_active:true}; this.modal=true; } }">
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Fee Schedule — eye procedures</h4>
                    <p class="text-xs text-slate-400">The price list ophthalmologists plan against. {{ $procedures->count() }} procedures.</p>
                </div>
                <button type="button" @click="open(null)" class="btn-primary">+ Add procedure</button>
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
                            <td class="px-4 py-2 text-xs text-slate-500">{{ EyeProcedure::CATEGORIES[$p->category] ?? $p->category }}</td>
                            <td class="px-4 py-2 text-sm text-slate-700 text-right">{{ $cur }}{{ number_format($p->default_fee, 2) }}</td>
                            <td class="px-4 py-2 text-right"><button type="button" @click="open({ id: @js($p->id), code: @js($p->code), name: @js($p->name), category: @js($p->category), default_fee: {{ (float) $p->default_fee }}, is_active: {{ $p->is_active ? 'true' : 'false' }} })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No procedures in the fee schedule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-modal show="modal" title-expr="form.id ? 'Edit Procedure' : 'Add Procedure'" max="lg">
            <form method="POST" :action="form.id ? '/eye/procedure/' + form.id : '{{ route('web.eye.procedure.store') }}'" class="space-y-4">
                @csrf
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Code</label><input type="text" name="code" x-model="form.code" required class="input-field"></div>
                    <div class="col-span-2"><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="form.name" required class="input-field"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="form.category" class="input-field">@foreach(EyeProcedure::CATEGORIES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div><label class="block text-sm font-medium text-slate-700 mb-1">Default fee ({{ $cur }})</label><input type="number" step="0.01" name="default_fee" x-model="form.default_fee" required class="input-field"></div>
                </div>
                <template x-if="form.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
                <div class="flex justify-end gap-2 pt-2"><button type="button" @click="modal=false" class="btn-secondary text-sm">Cancel</button><button type="submit" class="btn-primary">Save</button></div>
            </form>
        </x-modal>
    </div>
@else
    <div x-data="eye()">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">{{ $patient->name }}</h3>
                <p class="text-sm text-slate-500">{{ $patient->phone }}</p>
            </div>
            <a href="{{ route('web.eye.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Change patient</a>
        </div>

        {{-- Latest spectacle prescription — quick reference / hand-out --}}
        @if($lastExam && $lastExam->hasPrescription())
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Latest Prescription — {{ $lastExam->exam_date->format('M d, Y') }}</h4>
                <span class="text-xs text-slate-400">{{ EyeExam::RX_TYPES[$lastExam->rx_type] ?? '' }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                        <th class="px-4 py-2 text-left">Eye</th><th class="px-4 py-2">SPH</th><th class="px-4 py-2">CYL</th><th class="px-4 py-2">AXIS</th><th class="px-4 py-2">ADD</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100 text-center">
                        <tr><td class="px-4 py-2 text-left font-semibold text-slate-700">Right (OD)</td><td>{{ $lastExam->od_sph ?: '—' }}</td><td>{{ $lastExam->od_cyl ?: '—' }}</td><td>{{ $lastExam->od_axis ?: '—' }}</td><td>{{ $lastExam->od_add ?: '—' }}</td></tr>
                        <tr><td class="px-4 py-2 text-left font-semibold text-slate-700">Left (OS)</td><td>{{ $lastExam->os_sph ?: '—' }}</td><td>{{ $lastExam->os_cyl ?: '—' }}</td><td>{{ $lastExam->os_axis ?: '—' }}</td><td>{{ $lastExam->os_add ?: '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
            @if($lastExam->pd)<p class="px-5 py-2 text-xs text-slate-500 border-t border-slate-100">PD: {{ $lastExam->pd }} mm</p>@endif
        </div>
        @endif

        {{-- Tabs --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" @click="tab='exam'" :class="tab==='exam'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">New Exam</button>
            <button type="button" @click="tab='plan'" :class="tab==='plan'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Treatment Plan</button>
            <button type="button" @click="tab='history'" :class="tab==='history'?'bg-blue-600 text-white':'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Exam History ({{ $exams->count() }})</button>
        </div>

        {{-- ================= NEW EXAM ================= --}}
        <form x-show="tab==='exam'" method="POST" action="{{ route('web.eye.exam.save') }}" class="bg-white rounded-xl border border-slate-200 p-5 space-y-5">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Exam date</label><input type="date" name="exam_date" value="{{ now()->toDateString() }}" required class="input-field"></div>
                <div class="sm:col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Chief complaint</label><input type="text" name="chief_complaint" maxlength="255" class="input-field" placeholder="e.g. Blurred distance vision, 6 months"></div>
            </div>

            {{-- Visual acuity + IOP --}}
            <div>
                <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Visual Acuity &amp; IOP</h5>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-slate-200 rounded-lg">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                            <th class="px-3 py-2 text-left">Eye</th><th class="px-3 py-2">VA (unaided)</th><th class="px-3 py-2">VA (aided)</th><th class="px-3 py-2">IOP (mmHg)</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="px-3 py-2 font-semibold text-slate-700">Right (OD)</td>
                                <td class="px-2 py-1"><input type="text" name="va_od_unaided" maxlength="12" class="input-field text-center" placeholder="6/6"></td>
                                <td class="px-2 py-1"><input type="text" name="va_od_aided" maxlength="12" class="input-field text-center" placeholder="6/6"></td>
                                <td class="px-2 py-1"><input type="number" step="0.1" name="iop_od" class="input-field text-center" placeholder="14"></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-semibold text-slate-700">Left (OS)</td>
                                <td class="px-2 py-1"><input type="text" name="va_os_unaided" maxlength="12" class="input-field text-center" placeholder="6/9"></td>
                                <td class="px-2 py-1"><input type="text" name="va_os_aided" maxlength="12" class="input-field text-center" placeholder="6/6"></td>
                                <td class="px-2 py-1"><input type="number" step="0.1" name="iop_os" class="input-field text-center" placeholder="15"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Refraction / spectacle prescription --}}
            <div>
                <h5 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Refraction / Spectacle Prescription</h5>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border border-slate-200 rounded-lg">
                        <thead class="bg-slate-50 text-xs text-slate-500"><tr>
                            <th class="px-3 py-2 text-left">Eye</th><th class="px-3 py-2">SPH</th><th class="px-3 py-2">CYL</th><th class="px-3 py-2">AXIS</th><th class="px-3 py-2">ADD</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <td class="px-3 py-2 font-semibold text-slate-700">Right (OD)</td>
                                <td class="px-2 py-1"><input type="text" name="od_sph" maxlength="10" class="input-field text-center" placeholder="-1.00"></td>
                                <td class="px-2 py-1"><input type="text" name="od_cyl" maxlength="10" class="input-field text-center" placeholder="-0.50"></td>
                                <td class="px-2 py-1"><input type="text" name="od_axis" maxlength="10" class="input-field text-center" placeholder="90"></td>
                                <td class="px-2 py-1"><input type="text" name="od_add" maxlength="10" class="input-field text-center" placeholder="+2.00"></td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 font-semibold text-slate-700">Left (OS)</td>
                                <td class="px-2 py-1"><input type="text" name="os_sph" maxlength="10" class="input-field text-center" placeholder="-1.25"></td>
                                <td class="px-2 py-1"><input type="text" name="os_cyl" maxlength="10" class="input-field text-center" placeholder="-0.75"></td>
                                <td class="px-2 py-1"><input type="text" name="os_axis" maxlength="10" class="input-field text-center" placeholder="85"></td>
                                <td class="px-2 py-1"><input type="text" name="os_add" maxlength="10" class="input-field text-center" placeholder="+2.00"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-2">
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">PD (mm)</label><input type="text" name="pd" maxlength="10" class="input-field" placeholder="62"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Prescription type</label><select name="rx_type" class="input-field"><option value="">—</option>@foreach(EyeExam::RX_TYPES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
            </div>

            {{-- Clinical findings --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Anterior segment</label><textarea name="anterior_segment" rows="2" class="input-field" placeholder="Lids, conjunctiva, cornea, AC, iris, lens…"></textarea></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Posterior segment / Fundus</label><textarea name="posterior_segment" rows="2" class="input-field" placeholder="Disc, macula, vessels, periphery…"></textarea></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Diagnosis</label><textarea name="diagnosis" rows="2" class="input-field" placeholder="e.g. Immature senile cataract OU; Myopia"></textarea></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Advice / plan</label><textarea name="advice" rows="2" class="input-field" placeholder="Medication, glasses, surgery advice, follow-up"></textarea></div>
            </div>
            <div class="flex items-end justify-between gap-3">
                <div class="w-56"><label class="block text-xs font-semibold text-slate-600 mb-1">Next visit date</label><input type="date" name="next_visit_date" class="input-field"></div>
                <button type="submit" class="btn-primary">Save exam</button>
            </div>
        </form>

        {{-- ================= TREATMENT PLAN ================= --}}
        <div x-show="tab==='plan'" style="display:none">
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Planned / in-progress</p><p class="text-xl font-bold text-amber-600">{{ $cur }}{{ number_format($plan['planned'], 2) }}</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Completed</p><p class="text-xl font-bold text-green-600">{{ $cur }}{{ number_format($plan['completed'], 2) }}</p></div>
                <div class="bg-white rounded-xl border border-slate-200 p-4 flex items-center justify-between">
                    <div><p class="text-xs text-slate-500">Completed, unbilled</p><p class="text-xl font-bold text-blue-600">{{ $cur }}{{ number_format($plan['unbilled'], 2) }}</p></div>
                    @if($plan['unbilled_count'] > 0)
                    <form method="POST" action="{{ route('web.eye.bill') }}" onsubmit="return confirm('Create a bill for {{ $plan['unbilled_count'] }} completed procedure(s)?')">
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
                            <th class="table-header">Eye</th><th class="table-header">Procedure</th><th class="table-header text-right">Fee</th><th class="table-header">Status</th><th class="table-header"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($treatments as $t)
                            <tr>
                                <td class="px-4 py-2.5 text-sm text-slate-700">{{ $t->eye ? (EyeTreatment::EYES[$t->eye] ?? strtoupper($t->eye)) : '' }}</td>
                                <td class="px-4 py-2.5 text-sm text-slate-800">{{ $t->procedure }}<span class="block text-xs text-slate-400">{{ $t->notes }}@if($t->bill_id)<span class="text-green-600"> · billed</span>@endif</span></td>
                                <td class="px-4 py-2.5 text-sm text-slate-700 text-right">{{ $cur }}{{ number_format($t->cost, 2) }}</td>
                                <td class="px-4 py-2.5">
                                    @if($t->bill_id)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 whitespace-nowrap">Completed · billed</span>
                                    @else
                                    <form method="POST" action="{{ route('web.eye.treatment.update', $t->id) }}">@csrf
                                        <select name="status" onchange="this.form.submit()" class="text-xs border border-slate-200 rounded-lg px-2 py-1 bg-white">
                                            @foreach(EyeTreatment::STATUSES as $k => $label)<option value="{{ $k }}" {{ $t->status === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                                        </select>
                                    </form>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @unless($t->bill_id)
                                    <form method="POST" action="{{ route('web.eye.treatment.delete', $t->id) }}" onsubmit="return confirm('Remove this procedure?')">@csrf<button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button></form>
                                    @endunless
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No procedures planned.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Add procedure from the fee schedule --}}
                <form method="POST" action="{{ route('web.eye.treatment.add') }}" class="p-4 border-t border-slate-200 grid grid-cols-2 sm:grid-cols-6 gap-2 items-end">
                    @csrf
                    <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                    <div><label class="block text-xs font-semibold text-slate-600 mb-1">Eye</label><select name="eye" class="input-field"><option value="">—</option>@foreach(EyeTreatment::EYES as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
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
                        <select name="status" class="input-field">@foreach(EyeTreatment::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                        <button type="submit" class="btn-primary text-sm whitespace-nowrap">Add</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= EXAM HISTORY ================= --}}
        <div x-show="tab==='history'" style="display:none">
            <div class="space-y-3">
                @forelse($exams as $ex)
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-slate-800">{{ $ex->exam_date->format('D, M d, Y') }}</p>
                        <p class="text-xs text-slate-400">{{ $ex->examiner_name }}</p>
                    </div>
                    @if($ex->chief_complaint)<p class="text-sm text-slate-700"><span class="text-slate-400">Complaint:</span> {{ $ex->chief_complaint }}</p>@endif
                    <div class="flex flex-wrap gap-x-6 gap-y-1 mt-1 text-sm text-slate-600">
                        @if($ex->va_od_unaided || $ex->va_od_aided)<span><span class="text-slate-400">VA OD:</span> {{ $ex->va_od_unaided ?: '—' }}{{ $ex->va_od_aided ? ' / '.$ex->va_od_aided.' cc' : '' }}</span>@endif
                        @if($ex->va_os_unaided || $ex->va_os_aided)<span><span class="text-slate-400">VA OS:</span> {{ $ex->va_os_unaided ?: '—' }}{{ $ex->va_os_aided ? ' / '.$ex->va_os_aided.' cc' : '' }}</span>@endif
                        @if($ex->iop_od !== null)<span><span class="text-slate-400">IOP OD:</span> {{ $ex->iop_od }}</span>@endif
                        @if($ex->iop_os !== null)<span><span class="text-slate-400">IOP OS:</span> {{ $ex->iop_os }}</span>@endif
                    </div>
                    @if($ex->hasPrescription())
                    <p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Rx:</span>
                        OD {{ $ex->od_sph ?: 'plano' }}{{ $ex->od_cyl ? ' / '.$ex->od_cyl.' × '.$ex->od_axis : '' }}{{ $ex->od_add ? ' add '.$ex->od_add : '' }};
                        OS {{ $ex->os_sph ?: 'plano' }}{{ $ex->os_cyl ? ' / '.$ex->os_cyl.' × '.$ex->os_axis : '' }}{{ $ex->os_add ? ' add '.$ex->os_add : '' }}</p>
                    @endif
                    @if($ex->anterior_segment)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Anterior:</span> {{ $ex->anterior_segment }}</p>@endif
                    @if($ex->posterior_segment)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Fundus:</span> {{ $ex->posterior_segment }}</p>@endif
                    @if($ex->diagnosis)<p class="text-sm text-slate-700 mt-1"><span class="text-slate-400">Diagnosis:</span> {{ $ex->diagnosis }}</p>@endif
                    @if($ex->advice)<p class="text-sm text-slate-600 mt-1"><span class="text-slate-400">Advice:</span> {{ $ex->advice }}</p>@endif
                    @if($ex->next_visit_date)<p class="text-xs text-blue-600 mt-2">Next visit: {{ $ex->next_visit_date->format('M d, Y') }}</p>@endif
                </div>
                @empty
                <div class="bg-white rounded-xl border border-slate-200 p-8 text-center text-sm text-slate-400">No exams recorded yet. Use the <strong>New Exam</strong> tab.</div>
                @endforelse
            </div>
        </div>
    </div>
@endif

@push('scripts')
<script>
function eyeSearch() {
    return {
        q: '', results: [],
        async search() {
            if (this.q.trim().length < 2) { this.results = []; return; }
            try { const r = await fetch('/ajax/patients?q=' + encodeURIComponent(this.q.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); this.results = r.ok ? await r.json() : []; }
            catch (e) { this.results = []; }
        },
    };
}
function eye() {
    return {
        tab: 'exam',
        procs: @js($procedures->where('is_active', true)->map(fn($p)=>['id'=>$p->id,'name'=>$p->name,'fee'=>(float)$p->default_fee])->values()),
        pickProc: '', procName: '', procCost: '',
        applyProc() {
            const p = this.procs.find(x => x.id === this.pickProc);
            if (p) { this.procName = p.name; this.procCost = p.fee; }
        },
    };
}
</script>
@endpush
@endsection
