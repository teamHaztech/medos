@extends('layouts.app')
@section('title', 'Incident Reporting')
@section('page-title', 'Incident Reporting')

@php use App\Modules\Quality\Models\Incident;
$sevCls = ['minor'=>'bg-slate-100 text-slate-600','moderate'=>'bg-amber-100 text-amber-700','major'=>'bg-orange-100 text-orange-700','sentinel'=>'bg-red-100 text-red-700'];
$stCls = ['reported'=>'bg-blue-100 text-blue-700','under_review'=>'bg-amber-100 text-amber-700','action_taken'=>'bg-indigo-100 text-indigo-700','closed'=>'bg-green-100 text-green-700'];
@endphp

@section('content')
<div x-data="incidents()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Open</p><p class="text-2xl font-bold text-blue-700">{{ $counts['open'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Major / Sentinel open</p><p class="text-2xl font-bold text-red-600">{{ $counts['serious'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Closed</p><p class="text-2xl font-bold text-green-600">{{ $counts['closed'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Total</p><p class="text-2xl font-bold text-slate-800">{{ $counts['total'] }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All statuses</option>
                @foreach(Incident::STATUSES as $k => $label)<option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
            <select name="severity" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All severity</option>
                @foreach(Incident::SEVERITIES as $k => $label)<option value="{{ $k }}" {{ request('severity') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
            <select name="category" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All types</option>
                @foreach(Incident::CATEGORIES as $k => $label)<option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
        </form>
        <button type="button" @click="openReport()" class="ml-auto btn-primary">+ Report incident</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">No.</th><th class="table-header">Type</th><th class="table-header">Severity</th><th class="table-header">Dept</th><th class="table-header">When</th><th class="table-header">Status</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($incidents as $i)
                    <tr>
                        <td class="px-4 py-2.5 text-xs font-mono text-slate-500">{{ $i->incident_no }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ Incident::CATEGORIES[$i->category] ?? $i->category }}<span class="block text-xs text-slate-400 truncate max-w-xs">{{ $i->description }}</span></td>
                        <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $sevCls[$i->severity] ?? 'bg-slate-100' }}">{{ Incident::SEVERITIES[$i->severity] ?? $i->severity }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ $i->department ?? '' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($i->occurred_at)->format('M d, H:i') }}</td>
                        <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $stCls[$i->status] ?? 'bg-slate-100' }}">{{ Incident::STATUSES[$i->status] ?? $i->status }}</span></td>
                        <td class="px-4 py-2.5 text-right"><button type="button" @click="openManage({ id: @js($i->id), no: @js($i->incident_no), category: @js(Incident::CATEGORIES[$i->category] ?? $i->category), severity: @js($i->severity), status: @js($i->status), department: @js($i->department ?? ''), reportedBy: @js($i->reported_by_name ?? ''), occurredAt: @js(optional($i->occurred_at)->format('M d, Y H:i')), patient: @js($i->patient?->name ?? ''), description: @js($i->description), immediateAction: @js($i->immediate_action ?? ''), assigned: @js($i->assigned_to_name ?? ''), capa: @js($i->capa ?? '') })" class="text-xs font-medium text-blue-600 hover:text-blue-800">Manage</button></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No incidents recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Report modal --}}
    <x-modal show="reportModal" title="Report Incident" max="2xl">
        <form method="POST" action="{{ route('web.incidents.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" :value="selectedPatient?.id || ''">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">When it occurred</label>
                    <input type="datetime-local" name="occurred_at" x-model="occurredAt" class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Department / Location</label>
                    <input type="text" name="department" class="input-field" placeholder="e.g. Ward 3, ICU">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="category" class="input-field">@foreach(Incident::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Severity</label>
                    <select name="severity" class="input-field">@foreach(Incident::SEVERITIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                </div>
            </div>
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-1">Patient involved <span class="text-slate-400 font-normal">(optional)</span></label>
                <div x-show="selectedPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <span class="text-sm text-slate-800" x-text="selectedPatient?.name"></span>
                    <button type="button" @click="selectedPatient=null" class="text-slate-400 text-lg leading-none">&times;</button>
                </div>
                <div x-show="!selectedPatient">
                    <input type="text" x-model="patientSearch" @input.debounce.300ms="searchPatients()" class="input-field" placeholder="Search patient (optional)…" autocomplete="off">
                    <div x-show="patientResults.length" class="absolute z-30 left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-xl" style="display:none">
                        <template x-for="p in patientResults" :key="p.id"><button type="button" @click="pickPatient(p)" class="w-full flex justify-between p-2.5 hover:bg-blue-50 text-left border-b border-slate-100 last:border-0"><span class="text-sm text-slate-800" x-text="p.name"></span><span class="text-xs text-slate-400" x-text="p.phone"></span></button></template>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">What happened</label>
                <textarea name="description" rows="3" required class="input-field" placeholder="Describe the incident…"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Immediate action taken <span class="text-slate-400 font-normal">(optional)</span></label>
                <textarea name="immediate_action" rows="2" class="input-field"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="reportModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Submit report</button>
            </div>
        </form>
    </x-modal>

    {{-- Manage modal --}}
    <x-modal show="manageModal" title-expr="'Incident ' + inc.no" max="2xl">
        <div class="space-y-3">
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm space-y-1">
                <p><span class="text-slate-500">Type:</span> <span class="font-medium" x-text="inc.category"></span> · <span class="text-slate-500">Dept:</span> <span x-text="inc.department || ''"></span></p>
                <p><span class="text-slate-500">When:</span> <span x-text="inc.occurredAt"></span> · <span class="text-slate-500">Reported by:</span> <span x-text="inc.reportedBy || ''"></span></p>
                <p x-show="inc.patient"><span class="text-slate-500">Patient:</span> <span x-text="inc.patient"></span></p>
                <p class="text-slate-700 pt-1" x-text="inc.description"></p>
                <p x-show="inc.immediateAction" class="text-slate-500 text-xs">Immediate action: <span x-text="inc.immediateAction"></span></p>
            </div>
            <form method="POST" :action="'/incidents/' + inc.id" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" x-model="inc.status" class="input-field">@foreach(Incident::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Severity</label>
                        <select name="severity" x-model="inc.severity" class="input-field">@foreach(Incident::SEVERITIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Assigned to</label>
                    <input type="text" name="assigned_to_name" x-model="inc.assigned" class="input-field" placeholder="Owner / investigator">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">CAPA <span class="text-slate-400 font-normal">(corrective &amp; preventive action)</span></label>
                    <textarea name="capa" x-model="inc.capa" rows="3" class="input-field"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="manageModal=false" class="btn-secondary text-sm">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </x-modal>
</div>

@push('scripts')
<script>
function incidents() {
    return {
        reportModal: false, manageModal: false,
        patientSearch: '', patientResults: [], selectedPatient: null,
        occurredAt: '{{ now()->format('Y-m-d\TH:i') }}',
        inc: { id: '', no: '', category: '', severity: 'minor', status: 'reported', department: '', reportedBy: '', occurredAt: '', patient: '', description: '', immediateAction: '', assigned: '', capa: '' },
        openReport() { this.selectedPatient = null; this.patientSearch = ''; this.patientResults = []; this.reportModal = true; },
        openManage(i) { this.inc = { ...i }; this.manageModal = true; },
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
