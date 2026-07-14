@extends('layouts.app')
@section('title', 'Asset Register')
@section('page-title', 'Asset Management')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<div x-data="{
        editOpen: false,
        mode: 'add',
        blank: { id:'', asset_name:'', asset_type:'', serial_number:'', model:'', manufacturer:'', department:'', location:'', purchase_date:'', purchase_cost:'', useful_life_years:'', salvage_value:'', vendor_id:'', status:'active', notes:'' },
        edit: {},
        openAdd() { this.edit = Object.assign({}, this.blank); this.mode = 'add'; this.editOpen = true; },
        openEdit(a) { this.edit = Object.assign({}, a); this.mode = 'edit'; this.editOpen = true; },
        get formAction() { return this.mode === 'edit' ? '{{ url('admin/assets') }}/' + this.edit.id : '{{ url('admin/assets') }}'; }
    }">

    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.admin.assets.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Dashboard</a>
        <a href="{{ route('web.admin.assets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Asset Register</a>
        <a href="{{ route('web.admin.vendors.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Vendors</a>
        <a href="{{ route('web.admin.tickets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Service Requests</a>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('web.admin.assets.import.template') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">⤓ Template</a>
            <form method="POST" action="{{ route('web.admin.assets.import') }}" enctype="multipart/form-data" class="inline-flex">
                @csrf
                <label class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 cursor-pointer">
                    Import CSV
                    <input type="file" name="file" accept=".csv,text/csv" class="hidden" onchange="if(this.files.length){this.form.submit()}">
                </label>
            </form>
            <a href="{{ route('web.admin.assets.export') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">⤒ Export CSV</a>
            <a href="{{ route('web.admin.assets.report') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Print Report</a>
            <button @click="openAdd()" class="btn-primary">+ Add Asset</button>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>
    @endif
    @if(session('import_errors') && count(session('import_errors')))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm font-semibold text-amber-800 mb-2">Some rows need attention ({{ count(session('import_errors')) }} shown):</p>
            <ul class="text-xs text-amber-700 space-y-0.5 max-h-48 overflow-y-auto">
                @foreach(session('import_errors') as $err)<li>• {{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4 grid grid-cols-2 md:grid-cols-6 gap-3">
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="input-field md:col-span-2" placeholder="Search name, serial, model...">
        <select name="department" class="input-field">
            <option value="">All departments</option>
            @foreach($departments as $d)<option value="{{ $d }}" @selected(($filters['department'] ?? '') === $d)>{{ $d }}</option>@endforeach
        </select>
        <select name="type" class="input-field">
            <option value="">All types</option>
            @foreach($types as $t)<option value="{{ $t }}" @selected(($filters['type'] ?? '') === $t)>{{ $t }}</option>@endforeach
        </select>
        <select name="status" class="input-field">
            <option value="">Any status</option>
            @foreach($statuses as $k => $label)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $label }}</option>@endforeach
        </select>
        <select name="warranty" class="input-field">
            <option value="">Any warranty</option>
            <option value="active" @selected(($filters['warranty'] ?? '') === 'active')>Has active</option>
            <option value="expiring" @selected(($filters['warranty'] ?? '') === 'expiring')>Expiring ≤30d</option>
            <option value="expired" @selected(($filters['warranty'] ?? '') === 'expired')>Expired</option>
            <option value="none" @selected(($filters['warranty'] ?? '') === 'none')>None</option>
        </select>
        <div class="md:col-span-6 flex gap-2">
            <button type="submit" class="btn-primary px-5">Filter</button>
            <a href="{{ route('web.admin.assets.index') }}" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
            <span class="ml-auto self-center text-sm text-slate-500">{{ $assets->count() }} assets</span>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Asset</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Department</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Warranty</th>
                    <th class="table-header w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($assets as $a)
                    @php $w = $a->activeWarranty(); $days = $w?->daysToExpiry(); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell">
                            <a href="{{ route('web.admin.assets.show', $a->id) }}" class="font-medium text-blue-600 hover:text-blue-800">{{ $a->asset_name }}</a>
                            <p class="text-xs text-slate-400">{{ $a->serial_number ? 'SN: ' . $a->serial_number : '' }}{{ $a->location ? ' · ' . $a->location : '' }}</p>
                        </td>
                        <td class="table-cell text-slate-600">{{ $a->asset_type ?? '' }}</td>
                        <td class="table-cell">{{ $a->department ?? '' }}</td>
                        <td class="table-cell">
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                @if($a->status === 'active') bg-green-100 text-green-700
                                @elseif($a->status === 'under_maintenance') bg-amber-100 text-amber-700
                                @else bg-slate-200 text-slate-600 @endif">{{ $a->statusLabel() }}</span>
                        </td>
                        <td class="table-cell">
                            @if($w)
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $days <= 30 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $w->typeLabel() }} · {{ $days }}d
                                </span>
                            @elseif($a->warranties->count())
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-red-100 text-red-700">Expired</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-slate-100 text-slate-500">None</span>
                            @endif
                        </td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                    @click="openEdit({ id:'{{ $a->id }}', asset_name: @js($a->asset_name), asset_type: @js($a->asset_type ?? ''), serial_number: @js($a->serial_number ?? ''), model: @js($a->model ?? ''), manufacturer: @js($a->manufacturer ?? ''), department: @js($a->department ?? ''), location: @js($a->location ?? ''), purchase_date: '{{ optional($a->purchase_date)->toDateString() }}', purchase_cost: '{{ $a->purchase_cost }}', useful_life_years: '{{ $a->useful_life_years }}', salvage_value: '{{ $a->salvage_value }}', vendor_id: '{{ $a->vendor_id }}', status: @js($a->status), notes: @js($a->notes ?? '') })"
                                    class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                                <form method="POST" action="{{ route('web.admin.assets.destroy', $a->id) }}" onsubmit="return confirm('Remove this asset from the register?')">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-10">No assets match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title-expr="mode === 'edit' ? 'Edit Asset' : 'Add Asset'" max="2xl">
            <form method="POST" :action="formAction" class="grid grid-cols-2 gap-4">
                @csrf
                <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Asset name *</label>
                    <input type="text" name="asset_name" required x-model="edit.asset_name" class="input-field">
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Type</label><input type="text" name="asset_type" x-model="edit.asset_type" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Serial number</label><input type="text" name="serial_number" x-model="edit.serial_number" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Model</label><input type="text" name="model" x-model="edit.model" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Manufacturer</label><input type="text" name="manufacturer" x-model="edit.manufacturer" class="input-field"></div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Department</label>
                    <select name="department" x-model="edit.department" class="input-field">
                        <option value=""></option>
                        @foreach($departments as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                    </select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Location</label><input type="text" name="location" x-model="edit.location" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Purchase date</label><input type="date" name="purchase_date" x-model="edit.purchase_date" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Purchase cost ({{ $cur }})</label><input type="number" step="0.01" name="purchase_cost" x-model="edit.purchase_cost" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Useful life (years)</label><input type="number" min="1" max="100" name="useful_life_years" x-model="edit.useful_life_years" class="input-field" placeholder="e.g. 5"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Salvage value ({{ $cur }})</label><input type="number" step="0.01" name="salvage_value" x-model="edit.salvage_value" class="input-field" placeholder="0"></div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Vendor</label>
                    <select name="vendor_id" x-model="edit.vendor_id" class="input-field">
                        <option value=""></option>
                        @foreach($vendors as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" x-model="edit.status" class="input-field">
                        @foreach($statuses as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><textarea name="notes" x-model="edit.notes" rows="2" class="input-field"></textarea></div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5" x-text="mode === 'edit' ? 'Save Changes' : 'Add Asset'"></button>
                    <button type="button" @click="editOpen = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
