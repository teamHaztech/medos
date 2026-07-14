@extends('layouts.app')
@section('title', $asset->asset_name)
@section('page-title', 'Asset Detail')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<div x-data="{
        wOpen: false, wMode: 'add',
        wBlank: { id:'', warranty_type:'manufacturer', start_date:'', end_date:'', vendor_contact:'', terms:'', reminder_days_before_expiry:30 },
        w: {},
        openWAdd() { this.w = Object.assign({}, this.wBlank); this.wMode = 'add'; this.wOpen = true; },
        openWEdit(x) { this.w = Object.assign({}, x); this.wMode = 'edit'; this.wOpen = true; },
        get wAction() { return this.wMode === 'edit' ? '{{ url('admin/warranties') }}/' + this.w.id : '{{ route('web.admin.assets.warranties.store', $asset->id) }}'; },
        rOpen: false, r: { id:'', end_date:'' },
        openRenew(x) { this.r = { id: x.id, end_date:'' }; this.rOpen = true; },
        get rAction() { return '{{ url('admin/warranties') }}/' + this.r.id + '/renew'; },
        mOpen: false, cOpen: false, dOpen: false, srOpen: false,
        stOpen: false, st: { id:'', status:'open', assigned_to:'', priority:'normal', resolution_notes:'' },
        openSt(t) { this.st = Object.assign({}, t); this.stOpen = true; },
        get stAction() { return '{{ url('admin/service-requests') }}/' + this.st.id; }
    }">

    <a href="{{ route('web.admin.assets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to register</a>

    @if(session('success'))
        <div class="my-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-3 mb-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $asset->asset_name }}</h2>
                <p class="text-sm text-slate-500">{{ $asset->asset_type }}{{ $asset->model ? ' · ' . $asset->model : '' }}{{ $asset->manufacturer ? ' · ' . $asset->manufacturer : '' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-sm font-semibold
                    @if($asset->status === 'active') bg-green-100 text-green-700
                    @elseif($asset->status === 'under_maintenance') bg-amber-100 text-amber-700
                    @else bg-slate-200 text-slate-600 @endif">{{ $asset->statusLabel() }}</span>
                <button @click="srOpen = true" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100">Report Issue</button>
                @if($asset->status !== 'decommissioned')
                    <button @click="dOpen = true" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200">Decommission</button>
                @endif
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 text-sm">
            <div><p class="text-xs text-slate-400">Serial</p><p class="font-medium text-slate-700">{{ $asset->serial_number ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Department</p><p class="font-medium text-slate-700">{{ $asset->department ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Location</p><p class="font-medium text-slate-700">{{ $asset->location ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Vendor</p><p class="font-medium text-slate-700">{{ $asset->vendor?->name ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Purchase Date</p><p class="font-medium text-slate-700">{{ optional($asset->purchase_date)->format('M d, Y') ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Purchase Cost</p><p class="font-medium text-slate-700">{{ $asset->purchase_cost ? $cur . number_format($asset->purchase_cost, 2) : '-' }}</p></div>
            @if($asset->purchase_cost && $asset->useful_life_years)
            <div><p class="text-xs text-slate-400">Useful Life</p><p class="font-medium text-slate-700">{{ $asset->useful_life_years }} yrs · salvage {{ $cur }}{{ number_format($asset->salvage_value ?? 0, 0) }}</p></div>
            <div><p class="text-xs text-slate-400">Annual Depreciation</p><p class="font-medium text-slate-700">{{ $cur }}{{ number_format($asset->annualDepreciation(), 2) }}</p></div>
            <div><p class="text-xs text-slate-400">Current Book Value</p><p class="font-semibold text-blue-700">{{ $cur }}{{ number_format($asset->bookValue(), 2) }} <span class="text-xs text-slate-400 font-normal">(accum. {{ $cur }}{{ number_format($asset->accumulatedDepreciation(), 0) }})</span></p></div>
            @endif
            <div><p class="text-xs text-slate-400">Total Downtime</p><p class="font-medium text-slate-700">{{ $asset->downtimeHours() }} h</p></div>
        </div>
        @if($asset->notes)<p class="text-sm text-slate-600 mt-4 p-3 bg-slate-50 rounded-lg">{{ $asset->notes }}</p>@endif
        @if($asset->status === 'decommissioned')
            <div class="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-lg text-sm">
                <p class="font-semibold text-slate-700">Decommissioned{{ $asset->decommissioned_on ? ' on ' . $asset->decommissioned_on->format('M d, Y') : '' }}</p>
                @if($asset->decommission_reason)<p class="text-slate-600 mt-0.5">Reason: {{ $asset->decommission_reason }}</p>@endif
                @if($asset->disposal_method)<p class="text-slate-600 mt-0.5">Disposal: {{ $asset->disposal_method }}</p>@endif
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Warranties --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Warranty / AMC / CMC History</h3>
                <button @click="openWAdd()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
            </div>
            <div class="space-y-3">
                @forelse($asset->warranties as $w)
                    @php $days = $w->daysToExpiry(); $active = $w->isActiveNow(); @endphp
                    <div class="border border-slate-100 rounded-lg p-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-sm font-semibold text-slate-800">{{ $w->typeLabel() }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full ml-1 font-medium {{ $active ? ($days <= 30 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') : 'bg-red-100 text-red-700' }}">
                                    {{ $active ? $days . ' days left' : 'Expired' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openRenew({ id:'{{ $w->id }}' })" class="text-green-600 hover:text-green-800 text-xs">Renew</button>
                                <button type="button" @click="openWEdit({ id:'{{ $w->id }}', warranty_type: @js($w->warranty_type), start_date: '{{ optional($w->start_date)->toDateString() }}', end_date: '{{ optional($w->end_date)->toDateString() }}', vendor_contact: @js($w->vendor_contact ?? ''), terms: @js($w->terms ?? ''), reminder_days_before_expiry: {{ $w->reminder_days_before_expiry }} })" class="text-sm font-medium text-blue-600 hover:text-blue-800">Edit</button>
                                <form method="POST" action="{{ route('web.admin.assets.warranties.destroy', $w->id) }}" onsubmit="return confirm('Remove this warranty?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                                </form>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ optional($w->start_date)->format('M d, Y') ?? '—' }} → {{ optional($w->end_date)->format('M d, Y') }}
                            {{ $w->vendor_contact ? ' · ' . $w->vendor_contact : '' }}{{ $w->is_active ? '' : ' · (superseded)' }}
                        </p>
                        @if($w->terms)<p class="text-xs text-slate-600 mt-1">{{ $w->terms }}</p>@endif
                        @if($w->document_path)
                            <a href="{{ route('web.admin.assets.warranties.document', $w->id) }}" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 mt-1">📎 View document</a>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No warranty records yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Maintenance --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Maintenance History</h3>
                <button @click="mOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
            </div>
            <div class="space-y-3">
                @forelse($asset->maintenanceLogs as $m)
                    <div class="border border-slate-100 rounded-lg p-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-sm font-semibold text-slate-800">{{ $m->typeLabel() }}</span>
                                <span class="text-xs text-slate-400 ml-1">{{ optional($m->date)->format('M d, Y') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($m->cost)<span class="text-xs text-slate-600">{{ $cur }}{{ number_format($m->cost, 2) }}</span>@endif
                                <form method="POST" action="{{ route('web.admin.assets.maintenance.destroy', $m->id) }}" onsubmit="return confirm('Remove this log?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                                </form>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $m->performed_by ? 'By ' . $m->performed_by : '' }}
                            {{ $m->next_due_date ? ' · next due ' . $m->next_due_date->format('M d, Y') : '' }}
                        </p>
                        @if($m->notes)<p class="text-xs text-slate-600 mt-1">{{ $m->notes }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No maintenance logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        {{-- Calibrations --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Calibration Records</h3>
                <button @click="cOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
            </div>
            <div class="space-y-3">
                @forelse($asset->calibrations as $cal)
                    @php $days = $cal->daysToDue(); @endphp
                    <div class="border border-slate-100 rounded-lg p-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $cal->result === 'pass' ? 'bg-green-100 text-green-700' : ($cal->result === 'fail' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $cal->resultLabel() }}</span>
                                @if($days !== null)
                                    <span class="text-xs px-2 py-0.5 rounded-full ml-1 font-medium {{ $days < 0 ? 'bg-red-100 text-red-700' : ($days <= 30 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $days < 0 ? 'Overdue' : 'due in ' . $days . 'd' }}</span>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('web.admin.assets.calibrations.destroy', $cal->id) }}" onsubmit="return confirm('Remove this calibration record?')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                            </form>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $cal->calibrated_on ? 'Calibrated ' . $cal->calibrated_on->format('M d, Y') : '' }}
                            {{ $cal->next_due_date ? ' · next due ' . $cal->next_due_date->format('M d, Y') : '' }}
                            {{ $cal->performed_by ? ' · ' . $cal->performed_by : '' }}
                        </p>
                        @if($cal->notes)<p class="text-xs text-slate-600 mt-1">{{ $cal->notes }}</p>@endif
                        @if($cal->certificate_path)
                            <a href="{{ route('web.admin.assets.calibrations.certificate', $cal->id) }}" class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 mt-1">📎 View certificate</a>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No calibration records yet.</p>
                @endforelse
            </div>
        </div>

        {{-- Service requests --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700">Service Requests / Breakdowns</h3>
                <button @click="srOpen = true" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Report</button>
            </div>
            <div class="space-y-3">
                @forelse($asset->serviceRequests as $t)
                    <div class="border border-slate-100 rounded-lg p-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ in_array($t->priority, ['critical','high']) ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ $t->priorityLabel() }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $t->status === 'open' ? 'bg-amber-100 text-amber-700' : ($t->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') }}">{{ $t->statusLabel() }}</span>
                            </div>
                            <button type="button" @click="openSt({ id:'{{ $t->id }}', status: @js($t->status), assigned_to: @js($t->assigned_to ?? ''), priority: @js($t->priority), resolution_notes: @js($t->resolution_notes ?? '') })" class="text-blue-500 hover:text-blue-700 text-xs">Update</button>
                        </div>
                        <p class="text-sm text-slate-700 mt-1">{{ $t->issue }}</p>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ $t->reported_by ? 'By ' . $t->reported_by : '' }}{{ $t->reported_at ? ' · ' . $t->reported_at->format('M d, Y') : '' }}
                            {{ $t->assigned_to ? ' · assigned ' . $t->assigned_to : '' }}
                            @if($t->downtimeHours() !== null) · downtime {{ $t->downtimeHours() }}h @endif
                        </p>
                        @if($t->resolution_notes)<p class="text-xs text-slate-600 mt-1">Resolution: {{ $t->resolution_notes }}</p>@endif
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No service requests logged.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===================== MODALS ===================== --}}

    {{-- Warranty add / edit --}}
    <x-modal show="wOpen" title-expr="wMode === 'edit' ? 'Edit Warranty' : 'Add Warranty'" max="lg">
            <form method="POST" :action="wAction" enctype="multipart/form-data" class="grid grid-cols-2 gap-4">
                @csrf
                <input type="hidden" name="_method" :value="wMode === 'edit' ? 'PUT' : 'POST'">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="warranty_type" x-model="w.warranty_type" class="input-field">@foreach(\App\Modules\Asset\Models\AssetWarranty::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Vendor contact</label><input type="text" name="vendor_contact" x-model="w.vendor_contact" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Start date</label><input type="date" name="start_date" x-model="w.start_date" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">End date *</label><input type="date" name="end_date" required x-model="w.end_date" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Remind days before</label><input type="number" name="reminder_days_before_expiry" min="1" max="365" x-model="w.reminder_days_before_expiry" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Document (PDF/image)</label><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" class="input-field text-xs"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Terms / coverage</label><textarea name="terms" x-model="w.terms" rows="2" class="input-field"></textarea></div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5" x-text="wMode === 'edit' ? 'Save Changes' : 'Add Warranty'"></button>
                    <button type="button" @click="wOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Warranty renew --}}
    <x-modal show="rOpen" title="Renew Warranty" max="md">
            <form method="POST" :action="rAction" class="space-y-4">
                @csrf
                <p class="text-xs text-slate-500">Creates a new term continuing from the current one; the old record is kept as history.</p>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">New end date *</label><input type="date" name="end_date" required class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Vendor contact</label><input type="text" name="vendor_contact" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Terms</label><textarea name="terms" rows="2" class="input-field"></textarea></div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary px-5 py-2.5">Renew</button>
                    <button type="button" @click="rOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Maintenance add --}}
    <x-modal show="mOpen" title="Add Maintenance Log" max="lg">
            <form method="POST" action="{{ route('web.admin.assets.maintenance.store', $asset->id) }}" class="grid grid-cols-2 gap-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="maintenance_type" class="input-field">@foreach(\App\Modules\Asset\Models\AssetMaintenanceLog::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Performed by</label><input type="text" name="performed_by" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Date *</label><input type="date" name="date" required class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Next due date</label><input type="date" name="next_due_date" class="input-field"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Cost ({{ $cur }})</label><input type="number" step="0.01" name="cost" class="input-field"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><textarea name="notes" rows="2" class="input-field"></textarea></div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5">Add Log</button>
                    <button type="button" @click="mOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Calibration add --}}
    <x-modal show="cOpen" title="Add Calibration Record" max="lg">
            <form method="POST" action="{{ route('web.admin.assets.calibrations.store', $asset->id) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Calibrated on</label><input type="date" name="calibrated_on" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Next due date</label><input type="date" name="next_due_date" class="input-field"></div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Result</label>
                    <select name="result" class="input-field">@foreach(\App\Modules\Asset\Models\AssetCalibration::RESULTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Performed by</label><input type="text" name="performed_by" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Remind days before</label><input type="number" name="reminder_days_before_due" value="30" min="1" max="365" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Certificate (PDF/image)</label><input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png" class="input-field text-xs"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label><textarea name="notes" rows="2" class="input-field"></textarea></div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5">Add Calibration</button>
                    <button type="button" @click="cOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Decommission --}}
    <x-modal show="dOpen" title="Decommission Asset" max="md">
            <form method="POST" action="{{ route('web.admin.assets.decommission', $asset->id) }}" class="space-y-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Date *</label><input type="date" name="decommissioned_on" required value="{{ now()->toDateString() }}" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Reason *</label><textarea name="decommission_reason" required rows="2" class="input-field" placeholder="End of life, beyond economic repair..."></textarea></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Disposal method</label><input type="text" name="disposal_method" class="input-field" placeholder="Returned to vendor, scrapped, e-waste..."></div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary px-5 py-2.5">Decommission</button>
                    <button type="button" @click="dOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Report issue --}}
    <x-modal show="srOpen" title="Report Issue" max="md">
            <form method="POST" action="{{ route('web.admin.tickets.store', $asset->id) }}" class="space-y-4">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Issue *</label><textarea name="issue" required rows="3" class="input-field" placeholder="Describe the fault / breakdown"></textarea></div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Priority</label>
                    <select name="priority" class="input-field">@foreach(\App\Modules\Asset\Models\AssetServiceRequest::PRIORITIES as $k => $l)<option value="{{ $k }}" @selected($k==='normal')>{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Reported by</label><input type="text" name="reported_by" class="input-field" placeholder="{{ auth()->user()->name ?? '' }}"></div>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary px-5 py-2.5">Submit</button>
                    <button type="button" @click="srOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>

    {{-- Ticket status update --}}
    <x-modal show="stOpen" title="Update Service Request" max="md">
            <form method="POST" :action="stAction" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" x-model="st.status" class="input-field">@foreach(\App\Modules\Asset\Models\AssetServiceRequest::STATUSES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Priority</label>
                    <select name="priority" x-model="st.priority" class="input-field">@foreach(\App\Modules\Asset\Models\AssetServiceRequest::PRIORITIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Assigned to</label><input type="text" name="assigned_to" x-model="st.assigned_to" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Resolution notes</label><textarea name="resolution_notes" x-model="st.resolution_notes" rows="2" class="input-field"></textarea></div>
                <p class="text-xs text-slate-400">Marking Resolved/Closed logs a corrective maintenance entry automatically.</p>
                <div class="flex items-center gap-3 pt-1">
                    <button type="submit" class="btn-primary px-5 py-2.5">Save</button>
                    <button type="button" @click="stOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
