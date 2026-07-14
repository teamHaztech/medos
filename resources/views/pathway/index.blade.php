@extends('layouts.app')
@section('title', 'Clinical Pathways')
@section('page-title', 'Clinical Pathways')

@php use App\Modules\Pathway\Models\PathwayTemplate; use App\Modules\Pathway\Models\PatientPathway; @endphp

@section('content')
<div x-data="pathways()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Active</p><p class="text-2xl font-bold text-blue-700">{{ $counts['active'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Completed</p><p class="text-2xl font-bold text-green-600">{{ $counts['completed'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Templates</p><p class="text-2xl font-bold text-slate-800">{{ $counts['templates'] }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button type="button" @click="tab='enrollments'" :class="tab==='enrollments' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Enrollments</button>
        <button type="button" @click="tab='templates'" :class="tab==='templates' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Pathway Templates</button>
        <button type="button" @click="openEnroll()" class="ml-auto btn-primary">+ Enroll patient</button>
    </div>

    {{-- ENROLLMENTS --}}
    <div x-show="tab==='enrollments'" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Patient</th><th class="table-header">Pathway</th><th class="table-header">Progress</th><th class="table-header">Status</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($enrollments as $e)
                    @php $pct = $e->progressPercent(); $st = $e->status; $stCls = ['active'=>'bg-blue-100 text-blue-700','completed'=>'bg-green-100 text-green-700','discontinued'=>'bg-slate-100 text-slate-500'][$st] ?? 'bg-slate-100'; @endphp
                    <tr class="{{ $st === 'discontinued' ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $e->patient?->name ?? '' }}<span class="block text-xs text-slate-400">{{ $e->patient?->phone }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-700">{{ $e->template?->name ?? '' }}</td>
                        <td class="px-4 py-2.5 w-48">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-blue-500 rounded-full" style="width: {{ $pct }}%"></div></div>
                                <span class="text-xs text-slate-500">{{ $e->doneCount() }}/{{ $e->totalSteps() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $stCls }}">{{ PatientPathway::STATUSES[$st] ?? $st }}</span></td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" @click="openManage({{ \Illuminate\Support\Js::from(['id'=>$e->id,'patient'=>$e->patient?->name,'template'=>$e->template?->name,'steps'=>$e->template?->steps ?? [],'completed'=>array_map('intval', $e->completed_steps ?? [])]) }})" class="text-xs font-medium text-blue-600 hover:text-blue-800">Manage</button>
                            @if($st !== 'discontinued')
                            <form method="POST" action="{{ route('web.pathways.status', $e->id) }}" class="inline">@csrf<input type="hidden" name="status" value="discontinued"><button type="submit" class="text-xs text-slate-400 hover:text-slate-600">Stop</button></form>
                            @else
                            <form method="POST" action="{{ route('web.pathways.status', $e->id) }}" class="inline">@csrf<input type="hidden" name="status" value="active"><button type="submit" class="text-xs text-blue-500 hover:text-blue-700">Resume</button></form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No patients on a pathway yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TEMPLATES --}}
    <div x-show="tab==='templates'" style="display:none">
        <div class="flex justify-end mb-3"><button type="button" @click="openTemplate()" class="btn-primary">+ Add template</button></div>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Pathway</th><th class="table-header">Category</th><th class="table-header text-center">Steps</th><th class="table-header text-center">Active</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($templates as $t)
                    <tr class="{{ $t->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $t->name }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ PathwayTemplate::CATEGORIES[$t->category] ?? $t->category }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600 text-center">{{ count($t->steps ?? []) }}</td>
                        <td class="px-4 py-2.5 text-center">@if($t->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Yes</span>@else<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">No</span>@endif</td>
                        <td class="px-4 py-2.5 text-right"><button type="button" @click="openTemplate({{ \Illuminate\Support\Js::from(['id'=>$t->id,'name'=>$t->name,'category'=>$t->category,'steps_text'=>implode(chr(10), $t->steps ?? []),'is_active'=>$t->is_active]) }})" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Enroll modal --}}
    <x-modal show="enrollModal" title="Enroll on Pathway" max="lg">
        <form method="POST" action="{{ route('web.pathways.enroll') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" :value="selectedPatient?.id || ''">
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-1">Patient</label>
                <div x-show="selectedPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <span class="text-sm text-slate-800"><span x-text="selectedPatient?.name"></span> <span class="text-slate-400" x-text="selectedPatient?.phone"></span></span>
                    <button type="button" @click="selectedPatient=null" class="text-slate-400 text-lg leading-none">&times;</button>
                </div>
                <div x-show="!selectedPatient">
                    <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search patient by name or phone…" autocomplete="off">
                    <div x-show="patientResults.length" class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl" style="display:none">
                        <template x-for="p in patientResults" :key="p.id"><button type="button" @click="pickPatient(p)" class="w-full flex justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></button></template>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Pathway</label>
                <select name="template_id" x-model="templateId" required class="input-field">
                    <option value="">Select…</option>
                    @foreach($templates->where('is_active', true) as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ count($t->steps ?? []) }} steps)</option>@endforeach
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="enrollModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!selectedPatient || !templateId" :class="(!selectedPatient || !templateId) ? 'opacity-40' : ''">Enroll</button>
            </div>
        </form>
    </x-modal>

    {{-- Manage (checklist) modal --}}
    <x-modal show="manageModal" title-expr="mp.template" max="lg">
        <form method="POST" :action="'/pathways/' + mp.id + '/steps'" class="space-y-3">
            @csrf
            <p class="text-sm text-slate-500"><span class="font-semibold text-slate-800" x-text="mp.patient"></span> · <span x-text="mp.completed.length"></span>/<span x-text="mp.steps.length"></span> steps done</p>
            <div class="space-y-2 border border-slate-200 rounded-lg p-3">
                <template x-for="(step, idx) in mp.steps" :key="idx">
                    <label class="flex items-center gap-2.5 py-1">
                        <input type="checkbox" name="completed[]" :value="idx" x-model.number="mp.completed" class="rounded border-slate-300">
                        <span class="text-sm text-slate-700" :class="mp.completed.includes(idx) ? 'line-through text-slate-400' : ''" x-text="step"></span>
                    </label>
                </template>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="manageModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Save progress</button>
            </div>
        </form>
    </x-modal>

    {{-- Template modal --}}
    <x-modal show="templateModal" title-expr="tpl.id ? 'Edit Pathway' : 'Add Pathway'" max="lg">
        <form method="POST" :action="tpl.id ? '/pathways/templates/' + tpl.id : '{{ route('web.pathways.templates.store') }}'" class="space-y-4">
            @csrf
            <template x-if="tpl.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="tpl.name" required class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="tpl.category" class="input-field">@foreach(PathwayTemplate::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Steps <span class="text-slate-400 font-normal">(one per line)</span></label>
                <textarea name="steps_text" x-model="tpl.steps_text" rows="6" required class="input-field" placeholder="Assessment&#10;Investigations&#10;Treatment&#10;Review&#10;Discharge"></textarea>
            </div>
            <template x-if="tpl.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="tpl.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="templateModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
function pathways() {
    return {
        tab: 'enrollments', enrollModal: false, manageModal: false, templateModal: false,
        patientSearch: '', patientResults: [], selectedPatient: null, templateId: '',
        mp: { id: '', patient: '', template: '', steps: [], completed: [] },
        tpl: { id: '', name: '', category: 'medical', steps_text: '', is_active: true },
        openEnroll() { this.selectedPatient = null; this.patientSearch = ''; this.patientResults = []; this.templateId = ''; this.enrollModal = true; },
        openManage(m) { this.mp = { id: m.id, patient: m.patient, template: m.template, steps: m.steps, completed: [...m.completed] }; this.manageModal = true; },
        openTemplate(t) { this.tpl = t ? { ...t } : { id: '', name: '', category: 'medical', steps_text: '', is_active: true }; this.templateModal = true; },
        async searchPatients() {
            if (this.patientSearch.trim().length < 2) { this.patientResults = []; return; }
            try { const r = await fetch('/ajax/patients?q=' + encodeURIComponent(this.patientSearch.trim()), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }); this.patientResults = r.ok ? await r.json() : []; }
            catch (e) { this.patientResults = []; }
        },
        pickPatient(p) { this.selectedPatient = p; this.patientResults = []; this.patientSearch = ''; },
    };
}
</script>
@endpush
@endsection
