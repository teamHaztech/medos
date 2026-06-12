@extends('layouts.app')
@section('title', $asset->asset_name)
@section('page-title', 'Asset Detail')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
<div x-data="{
        wOpen: false,
        wMode: 'add',
        wBlank: { id:'', warranty_type:'manufacturer', start_date:'', end_date:'', vendor_contact:'', terms:'', reminder_days_before_expiry:30 },
        w: {},
        openWAdd() { this.w = Object.assign({}, this.wBlank); this.wMode = 'add'; this.wOpen = true; },
        openWEdit(x) { this.w = Object.assign({}, x); this.wMode = 'edit'; this.wOpen = true; },
        get wAction() { return this.wMode === 'edit' ? '{{ url('admin/warranties') }}/' + this.w.id : '{{ route('web.admin.assets.warranties.store', $asset->id) }}'; },
        mOpen: false,
        openMAdd() { this.mOpen = true; }
    }">

    <a href="{{ route('web.admin.assets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to register</a>

    @if(session('success'))
        <div class="my-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Header --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-3 mb-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">{{ $asset->asset_name }}</h2>
                <p class="text-sm text-slate-500">{{ $asset->asset_type }}{{ $asset->model ? ' · ' . $asset->model : '' }}{{ $asset->manufacturer ? ' · ' . $asset->manufacturer : '' }}</p>
            </div>
            <span class="px-3 py-1 rounded-full text-sm font-semibold
                @if($asset->status === 'active') bg-green-100 text-green-700
                @elseif($asset->status === 'under_maintenance') bg-amber-100 text-amber-700
                @else bg-slate-200 text-slate-600 @endif">{{ $asset->statusLabel() }}</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-5 text-sm">
            <div><p class="text-xs text-slate-400">Serial</p><p class="font-medium text-slate-700">{{ $asset->serial_number ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Department</p><p class="font-medium text-slate-700">{{ $asset->department ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Location</p><p class="font-medium text-slate-700">{{ $asset->location ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Vendor</p><p class="font-medium text-slate-700">{{ $asset->vendor?->name ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Purchase Date</p><p class="font-medium text-slate-700">{{ optional($asset->purchase_date)->format('M d, Y') ?? '-' }}</p></div>
            <div><p class="text-xs text-slate-400">Purchase Cost</p><p class="font-medium text-slate-700">{{ $asset->purchase_cost ? $cur . number_format($asset->purchase_cost, 2) : '-' }}</p></div>
        </div>
        @if($asset->notes)<p class="text-sm text-slate-600 mt-4 p-3 bg-slate-50 rounded-lg">{{ $asset->notes }}</p>@endif
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
                                <button type="button" @click="openWEdit({ id:'{{ $w->id }}', warranty_type: @js($w->warranty_type), start_date: '{{ optional($w->start_date)->toDateString() }}', end_date: '{{ optional($w->end_date)->toDateString() }}', vendor_contact: @js($w->vendor_contact ?? ''), terms: @js($w->terms ?? ''), reminder_days_before_expiry: {{ $w->reminder_days_before_expiry }} })" class="text-blue-500 hover:text-blue-700 text-xs">Edit</button>
                                <form method="POST" action="{{ route('web.admin.assets.warranties.destroy', $w->id) }}" onsubmit="return confirm('Remove this warranty?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-xs">Remove</button>
                                </form>
                            </div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            {{ optional($w->start_date)->format('M d, Y') ?? '—' }} → {{ optional($w->end_date)->format('M d, Y') }}
                            {{ $w->vendor_contact ? ' · ' . $w->vendor_contact : '' }}
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
                <button @click="openMAdd()" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Add</button>
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

    {{-- Warranty modal (Add / Edit) --}}
    <div x-show="wOpen" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="wOpen = false">
        <div @click.away="wOpen = false" style="max-height:88vh" class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-900" x-text="wMode === 'edit' ? 'Edit Warranty' : 'Add Warranty'"></h3>
                <button type="button" @click="wOpen = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" :action="wAction" enctype="multipart/form-data" class="p-6 grid grid-cols-2 gap-4">
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
        </div>
    </div>

    {{-- Maintenance modal (Add) --}}
    <div x-show="mOpen" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="mOpen = false">
        <div @click.away="mOpen = false" style="max-height:88vh" class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-bold text-slate-900">Add Maintenance Log</h3>
                <button type="button" @click="mOpen = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('web.admin.assets.maintenance.store', $asset->id) }}" class="p-6 grid grid-cols-2 gap-4">
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
        </div>
    </div>
</div>
@endsection
