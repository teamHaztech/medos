@extends('layouts.app')
@section('title', 'Service Master')
@section('page-title', 'Billing')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); $cats = \App\Modules\Billing\Models\ServiceCharge::CATEGORIES; @endphp

@section('content')
<div x-data="serviceMaster()">
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.billing.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Dashboard</a>
        <a href="{{ route('web.billing.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Bills</a>
        <a href="{{ route('web.billing.services') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Service Master</a>
        <a href="{{ route('web.billing.new') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">+ New Bill</a>
        <button type="button" @click="openAdd()" class="ml-auto btn-primary">+ Add Service</button>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
        <div><label class="block text-xs font-medium text-slate-500 mb-1">Category</label>
            <select name="category" class="input-field" onchange="this.form.submit()">
                <option value="">All</option>
                @foreach($cats as $k => $l)<option value="{{ $k }}" @selected(request('category')===$k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="flex-1 min-w-0"><label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" class="input-field" placeholder="Name or code…">
        </div>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Service</th><th class="table-header">Code</th><th class="table-header">Category</th>
                    <th class="table-header">Price</th><th class="table-header">Tax</th><th class="table-header">Status</th><th class="table-header">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($services as $s)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium text-slate-800">{{ $s->name }}</td>
                        <td class="table-cell text-slate-500 font-mono text-xs">{{ $s->code ?? '—' }}</td>
                        <td class="table-cell"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ $s->categoryLabel() }}</span></td>
                        <td class="table-cell font-medium">{{ $cur }}{{ number_format($s->price, 2) }}</td>
                        <td class="table-cell">@if($s->is_taxable || $s->gst_rate > 0)<span class="text-xs">GST {{ rtrim(rtrim(number_format($s->gst_rate, 2), '0'), '.') }}%</span>@if($s->hsn_sac)<span class="block text-xs text-slate-400">HSN {{ $s->hsn_sac }}</span>@endif @else<span class="text-xs text-slate-400">Exempt</span>@endif</td>
                        <td class="table-cell">@if($s->is_active)<span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Active</span>@else<span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">Inactive</span>@endif</td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openEdit({ id: '{{ $s->id }}', name: @js($s->name), code: @js($s->code ?? ''), category: '{{ $s->category }}', price: {{ (float) $s->price }}, is_taxable: {{ $s->is_taxable ? 'true' : 'false' }}, gst_rate: {{ (float) $s->gst_rate }}, hsn_sac: @js($s->hsn_sac ?? ''), is_active: {{ $s->is_active ? 'true' : 'false' }} })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                                <form method="POST" action="{{ route('web.billing.services.destroy', $s->id) }}" onsubmit="return confirm('Remove {{ $s->name }}?')">@csrf @method('DELETE')<button class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 font-medium">Remove</button></form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400 text-sm">No services yet — add your rate card.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <x-modal show="editOpen" title-expr="edit.id ? 'Edit Service' : 'Add Service'" max="md">
        <form method="POST" :action="editAction" class="grid grid-cols-2 gap-4">
            @csrf
            <template x-if="edit.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Service name *</label><input type="text" name="name" required x-model="edit.name" class="input-field"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Code</label><input type="text" name="code" x-model="edit.code" class="input-field" placeholder="e.g. ROOM-ICU"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Category *</label>
                <select name="category" x-model="edit.category" class="input-field">@foreach($cats as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Price ({{ $cur }}) *</label><input type="number" step="0.01" min="0" name="price" required x-model="edit.price" class="input-field"></div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">GST rate (%)</label>
                <select name="gst_rate" x-model.number="edit.gst_rate" class="input-field">
                    <option value="0">0% (Exempt)</option><option value="5">5%</option><option value="12">12%</option><option value="18">18%</option><option value="28">28%</option>
                </select>
                <p class="text-xs text-slate-400 mt-1">Clinical services are usually exempt; pharmacy/consumables are taxable.</p>
            </div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">HSN / SAC code</label><input type="text" name="hsn_sac" x-model="edit.hsn_sac" maxlength="20" class="input-field" placeholder="e.g. 3004"></div>
            <div class="col-span-2 flex items-end gap-4">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_taxable" value="1" x-model="edit.is_taxable" class="rounded border-slate-300 text-blue-600"> Taxable</label>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded border-slate-300 text-blue-600"> Active</label>
            </div>
            <div class="col-span-2 flex items-center gap-3 pt-1">
                <button type="submit" class="btn-primary px-5 py-2.5">Save</button>
                <button type="button" @click="editOpen = false" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
function serviceMaster() {
    return {
        editOpen: false,
        edit: { id:'', name:'', code:'', category:'other', price:0, is_taxable:false, gst_rate:0, hsn_sac:'', is_active:true },
        get editAction() { return this.edit.id ? '{{ url('billing/services') }}/' + this.edit.id : '{{ route('web.billing.services.store') }}'; },
        openAdd() { this.edit = { id:'', name:'', code:'', category:'other', price:0, is_taxable:false, gst_rate:0, hsn_sac:'', is_active:true }; this.editOpen = true; },
        openEdit(s) { this.edit = Object.assign({}, s); this.editOpen = true; },
    };
}
</script>
@endpush
@endsection
