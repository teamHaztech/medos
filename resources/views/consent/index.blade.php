@extends('layouts.app')
@section('title', 'Consent')
@section('page-title', 'Consent Management')

@php use App\Modules\Consent\Models\ConsentForm; use App\Modules\Consent\Models\PatientConsent; @endphp

@section('content')
<div x-data="consent()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Pending</p><p class="text-2xl font-bold text-amber-600">{{ $counts['pending'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Signed</p><p class="text-2xl font-bold text-green-600">{{ $counts['signed'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Declined</p><p class="text-2xl font-bold text-red-600">{{ $counts['declined'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Completion</p><p class="text-2xl font-bold text-blue-700">{{ $counts['completion'] }}%</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button type="button" @click="tab='records'" :class="tab==='records' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Consents</button>
        <button type="button" @click="tab='forms'" :class="tab==='forms' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Form Repository</button>
        <button type="button" @click="openRequest()" class="ml-auto btn-primary">+ Request consent</button>
    </div>

    {{-- RECORDS --}}
    <div x-show="tab==='records'" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Patient</th><th class="table-header">Consent form</th><th class="table-header">Status</th><th class="table-header">Signed by</th><th class="table-header text-right">Action</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($records as $r)
                    <tr>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $r->patient?->name ?? '' }}<span class="block text-xs text-slate-400">{{ $r->patient?->phone }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-700">{{ $r->form?->name ?? '' }}</td>
                        <td class="px-4 py-2.5">
                            @php $s = $r->status; $cls = ['pending'=>'bg-amber-100 text-amber-700','signed'=>'bg-green-100 text-green-700','declined'=>'bg-red-100 text-red-700','withdrawn'=>'bg-slate-100 text-slate-500'][$s] ?? 'bg-slate-100 text-slate-600'; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $cls }}">{{ PatientConsent::STATUSES[$s] ?? $s }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $r->signed_by_name ? $r->signed_by_name.' ('.(PatientConsent::RELATIONSHIPS[$r->relationship] ?? $r->relationship).')' : '' }}{{ $r->witness_name ? ' · witness: '.$r->witness_name : '' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @if($r->status === 'pending')
                            <button type="button" @click="openSign(@js($r->id), @js($r->patient?->name), {{ $r->form?->requires_witness ? 'true' : 'false' }})" class="text-xs font-medium text-blue-600 hover:text-blue-800">Sign</button>
                            <form method="POST" action="{{ route('web.consent.status', $r->id) }}" class="inline ml-2">@csrf<input type="hidden" name="status" value="declined"><button type="submit" class="text-xs text-red-500 hover:text-red-700">Decline</button></form>
                            @elseif($r->status === 'signed')
                            <form method="POST" action="{{ route('web.consent.status', $r->id) }}" class="inline">@csrf<input type="hidden" name="status" value="withdrawn"><button type="submit" class="text-xs text-slate-400 hover:text-slate-600">Withdraw</button></form>
                            @else <span class="text-xs text-slate-300"></span> @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No consents recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- FORMS --}}
    <div x-show="tab==='forms'" style="display:none">
        <div class="flex justify-end mb-3"><button type="button" @click="openForm()" class="btn-primary">+ Add form</button></div>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Form</th><th class="table-header">Category</th><th class="table-header text-center">Witness</th><th class="table-header text-center">Active</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($forms as $f)
                    <tr class="{{ $f->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $f->name }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ ConsentForm::CATEGORIES[$f->category] ?? $f->category }}</td>
                        <td class="px-4 py-2.5 text-center text-xs">{!! $f->requires_witness ? '<span class="text-amber-600">Required</span>' : '<span class="text-slate-300"></span>' !!}</td>
                        <td class="px-4 py-2.5 text-center">@if($f->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Yes</span>@else<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">No</span>@endif</td>
                        <td class="px-4 py-2.5 text-right"><button type="button" @click="openForm({ id: @js($f->id), name: @js($f->name), category: @js($f->category), content: @js($f->content ?? ''), requires_witness: {{ $f->requires_witness ? 'true' : 'false' }}, is_active: {{ $f->is_active ? 'true' : 'false' }} })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Request modal --}}
    <x-modal show="requestModal" title="Request Consent" max="lg">
        <form method="POST" action="{{ route('web.consent.request') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="patient_id" :value="selectedPatient?.id || ''">
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-1">Patient</label>
                <div x-show="selectedPatient" class="flex items-center justify-between px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <span class="text-sm font-medium text-slate-800"><span x-text="selectedPatient?.name"></span> <span class="text-slate-400" x-text="selectedPatient?.phone"></span></span>
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Consent form</label>
                <select name="consent_form_id" x-model="formId" required class="input-field">
                    <option value="">Select…</option>
                    @foreach($forms->where('is_active', true) as $f)<option value="{{ $f->id }}">{{ $f->name }} · {{ ConsentForm::CATEGORIES[$f->category] ?? '' }}</option>@endforeach
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="requestModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!selectedPatient || !formId" :class="(!selectedPatient || !formId) ? 'opacity-40' : ''">Request</button>
            </div>
        </form>
    </x-modal>

    {{-- Sign modal --}}
    <x-modal show="signModal" title="Record Consent Signature" max="lg">
        <form method="POST" :action="'/consent/' + signId + '/sign'" class="space-y-4">
            @csrf
            <p class="text-sm text-slate-500">Recording consent for <span class="font-semibold text-slate-800" x-text="signPatient"></span>.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Signed by</label>
                    <input type="text" name="signed_by_name" x-model="signBy" required class="input-field" placeholder="Name of signatory">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                    <select name="relationship" x-model="signRel" class="input-field">@foreach(PatientConsent::RELATIONSHIPS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                </div>
            </div>
            <div x-show="signWitness">
                <label class="block text-sm font-medium text-slate-700 mb-1">Witness name <span class="text-amber-600 font-normal">(required for this form)</span></label>
                <input type="text" name="witness_name" x-model="signWit" class="input-field" placeholder="Witness name">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="signModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!signBy.trim() || (signWitness && !signWit.trim())" :class="(!signBy.trim() || (signWitness && !signWit.trim())) ? 'opacity-40' : ''">Record signature</button>
            </div>
        </form>
    </x-modal>

    {{-- Form master modal --}}
    <x-modal show="formModal" title-expr="form.id ? 'Edit Consent Form' : 'Add Consent Form'" max="lg">
        <form method="POST" :action="form.id ? '/consent/forms/' + form.id : '{{ route('web.consent.forms.store') }}'" class="space-y-4">
            @csrf
            <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                <input type="text" name="name" x-model="form.name" required class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                <select name="category" x-model="form.category" class="input-field">@foreach(ConsentForm::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Content</label>
                <textarea name="content" x-model="form.content" rows="3" class="input-field" placeholder="Consent statement / body"></textarea>
            </div>
            <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="requires_witness" value="1" x-model="form.requires_witness" class="rounded border-slate-300"><span class="text-slate-600">Requires witness</span></label>
            <template x-if="form.id"><label class="inline-flex items-center gap-2 text-sm ml-4"><input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="formModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
function consent() {
    return {
        tab: 'records', requestModal: false, signModal: false, formModal: false,
        patientSearch: '', patientResults: [], selectedPatient: null, formId: '',
        signId: '', signPatient: '', signWitness: false, signBy: '', signRel: 'self', signWit: '',
        form: { id: '', name: '', category: 'general', content: '', requires_witness: false, is_active: true },
        openRequest() { this.selectedPatient = null; this.patientSearch = ''; this.patientResults = []; this.formId = ''; this.requestModal = true; },
        openSign(id, patient, witness) { this.signId = id; this.signPatient = patient || 'patient'; this.signWitness = !!witness; this.signBy = ''; this.signRel = 'self'; this.signWit = ''; this.signModal = true; },
        openForm(f) { this.form = f ? { ...f } : { id: '', name: '', category: 'general', content: '', requires_witness: false, is_active: true }; this.formModal = true; },
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
