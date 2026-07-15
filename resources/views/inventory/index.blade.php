@extends('layouts.app')
@section('title', 'Inventory')
@section('page-title', 'Inventory Management')

@php use App\Modules\Inventory\Models\InventoryItem; use App\Modules\Inventory\Models\StockMovement; @endphp

@section('content')
<div x-data="inv()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Items</p><p class="text-2xl font-bold text-slate-800">{{ $counts['items'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Low stock</p><p class="text-2xl font-bold text-amber-600">{{ $counts['low'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Out of stock</p><p class="text-2xl font-bold text-red-600">{{ $counts['out'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Expiring ≤ 90d</p><p class="text-2xl font-bold text-orange-600">{{ $counts['expiring'] }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <button type="button" @click="tab='items'" :class="tab==='items' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Items</button>
        <button type="button" @click="tab='movements'" :class="tab==='movements' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Movements</button>
        <button type="button" @click="tab='expiring'" :class="tab==='expiring' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-semibold">Expiring ({{ $counts['expiring'] }})</button>
        <div class="ml-auto flex flex-wrap gap-2">
            <a href="{{ route('web.inventory.export') }}" class="btn-secondary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </a>
            <button type="button" @click="showImport = true" class="btn-secondary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-6l-4-4m0 0L8 6m4-4v12"/></svg>
                Import
            </button>
            <button type="button" @click="openItem()" class="btn-primary">+ Add item</button>
            <button type="button" @click="openMove('')" class="btn-primary">Record stock</button>
        </div>
    </div>

    {{-- Bulk-import result --}}
    @if(session('import_result'))
        @php $ir = session('import_result'); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-semibold text-slate-700">Import result</h3>
                <span class="text-xs text-slate-400">{{ count($ir['created']) }} added · {{ count($ir['skipped']) }} skipped · {{ count($ir['errors']) }} errors</span>
            </div>
            @if(count($ir['created']))
                <p class="text-xs text-slate-500">Added: {{ collect($ir['created'])->pluck('name')->take(30)->implode(', ') }}{{ count($ir['created']) > 30 ? '…' : '' }}</p>
            @endif
            @if(count($ir['skipped']))
                <p class="text-xs text-amber-600 mt-1"><b>Skipped</b> (already exist): {{ collect($ir['skipped'])->pluck('name')->take(30)->implode(', ') }}</p>
            @endif
            @if(count($ir['errors']))
                <div class="text-xs text-red-600 mt-1"><b>Errors:</b>
                    <ul class="list-disc list-inside">
                        @foreach($ir['errors'] as $e)<li>Row {{ $e['row'] ?? '?' }}{{ !empty($e['name']) ? ' ('.$e['name'].')' : '' }} — {{ $e['reason'] }}</li>@endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- ITEMS --}}
    <div x-show="tab==='items'" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Item</th><th class="table-header">Category</th><th class="table-header text-center">In stock</th><th class="table-header text-center">Min / Max</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($items as $it)
                    <tr class="{{ $it->is_active ? '' : 'opacity-50' }}">
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $it->name }}<span class="text-xs text-slate-400"> {{ $it->code }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ InventoryItem::CATEGORIES[$it->category] ?? $it->category }}</td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="font-semibold {{ $it->current_stock <= 0 ? 'text-red-600' : ($it->current_stock <= $it->reorder_min ? 'text-amber-600' : 'text-slate-800') }}">{{ $it->current_stock }}</span> <span class="text-xs text-slate-400">{{ $it->unit }}</span>
                            @if($it->current_stock <= 0)<span class="block text-xs text-red-600">Out</span>@elseif($it->current_stock <= $it->reorder_min)<span class="block text-xs text-amber-600">Reorder</span>@endif
                        </td>
                        <td class="px-4 py-2.5 text-center text-xs text-slate-500">{{ $it->reorder_min }} / {{ $it->reorder_max }}</td>
                        <td class="px-4 py-2.5 text-right space-x-2">
                            <button type="button" @click="openMove(@js($it->id))" class="text-xs font-medium text-blue-600 hover:text-blue-800">Stock</button>
                            <button type="button" @click="openItem({ id: @js($it->id), name: @js($it->name), code: @js($it->code ?? ''), category: @js($it->category), unit: @js($it->unit), reorder_min: {{ (int) $it->reorder_min }}, reorder_max: {{ (int) $it->reorder_max }}, current_stock: {{ (int) $it->current_stock }}, is_active: {{ $it->is_active ? 'true' : 'false' }} })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- MOVEMENTS --}}
    <div x-show="tab==='movements'" style="display:none" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Item</th><th class="table-header">Type</th><th class="table-header text-center">Qty</th><th class="table-header">Batch / Dept</th><th class="table-header">By</th><th class="table-header">When</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $m)
                    <tr>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $m->item?->name ?? '' }}</td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ StockMovement::TYPES[$m->type] ?? $m->type }}</td>
                        <td class="px-4 py-2.5 text-center text-sm font-semibold {{ $m->quantity < 0 ? 'text-red-600' : 'text-green-600' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $m->batch_number ? 'B:'.$m->batch_number : '' }}{{ $m->expiry_date ? ' exp '.$m->expiry_date->format('M Y') : '' }}{{ $m->department ? ' → '.$m->department : '' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $m->performed_by_name ?? '' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($m->created_at)->format('M d, H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No stock movements yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- EXPIRING --}}
    <div x-show="tab==='expiring'" style="display:none" class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">Item</th><th class="table-header">Batch</th><th class="table-header">Expiry</th><th class="table-header text-right">Days left</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($expiring as $e)
                @php $days = today()->diffInDays($e->expiry_date, false); @endphp
                <tr>
                    <td class="px-4 py-2.5 text-sm text-slate-800">{{ $e->item?->name ?? '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-600">{{ $e->batch_number ?? '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-600">{{ $e->expiry_date->format('M d, Y') }}</td>
                    <td class="px-4 py-2.5 text-right text-sm font-semibold {{ $days <= 30 ? 'text-red-600' : 'text-orange-600' }}">{{ $days }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-400">No batches expiring in 90 days.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Stock move modal --}}
    <x-modal show="moveModal" title="Record Stock Movement" max="lg">
        <form method="POST" action="{{ route('web.inventory.move') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Item</label>
                <select name="item_id" x-model="mv.item_id" required class="input-field">
                    <option value="">Select…</option>
                    @foreach($items->where('is_active', true) as $it)<option value="{{ $it->id }}">{{ $it->name }} ({{ $it->current_stock }} {{ $it->unit }})</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="type" x-model="mv.type" class="input-field"><option value="receipt">Receipt (in)</option><option value="issue">Issue (out)</option><option value="adjustment">Adjustment</option></select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity <span class="text-slate-400 font-normal" x-show="mv.type==='adjustment'">(±)</span></label>
                    <input type="number" name="quantity" x-model="mv.quantity" required class="input-field">
                </div>
            </div>
            <div x-show="mv.type==='receipt'" class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Batch #</label><input type="text" name="batch_number" class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Expiry</label><input type="date" name="expiry_date" class="input-field"></div>
            </div>
            <div x-show="mv.type==='issue'">
                <label class="block text-sm font-medium text-slate-700 mb-1">Issue to department</label>
                <input type="text" name="department" class="input-field" placeholder="e.g. Ward 3, OT">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Reference / notes <span class="text-slate-400 font-normal">(optional)</span></label>
                <input type="text" name="reference" class="input-field" placeholder="PO no. / requisition / note">
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="moveModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="!mv.item_id || !mv.quantity" :class="(!mv.item_id || !mv.quantity) ? 'opacity-40' : ''">Record</button>
            </div>
        </form>
    </x-modal>

    {{-- Item master modal --}}
    <x-modal show="itemModal" title-expr="item.id ? 'Edit Item' : 'Add Item'" max="lg">
        <form method="POST" :action="item.id ? '/inventory/items/' + item.id : '{{ route('web.inventory.items.store') }}'" class="space-y-4">
            @csrf
            <template x-if="item.id"><input type="hidden" name="_method" value="PUT"></template>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Name</label><input type="text" name="name" x-model="item.name" required class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Code</label><input type="text" name="code" x-model="item.code" class="input-field"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Category</label><select name="category" x-model="item.category" class="input-field">@foreach(InventoryItem::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Unit</label><select name="unit" x-model="item.unit" class="input-field">@foreach(InventoryItem::UNITS as $u)<option value="{{ $u }}">{{ $u }}</option>@endforeach</select></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Reorder min</label><input type="number" name="reorder_min" x-model="item.reorder_min" min="0" required class="input-field"></div>
                <div><label class="block text-sm font-medium text-slate-700 mb-1">Reorder max</label><input type="number" name="reorder_max" x-model="item.reorder_max" min="0" required class="input-field"></div>
                <div x-show="!item.id"><label class="block text-sm font-medium text-slate-700 mb-1">Opening stock</label><input type="number" name="current_stock" x-model="item.current_stock" min="0" class="input-field"></div>
            </div>
            <template x-if="item.id"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" x-model="item.is_active" class="rounded border-slate-300"><span class="text-slate-600">Active</span></label></template>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="itemModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </x-modal>

    {{-- Import Items Modal --}}
    <x-modal show="showImport" title="Import Inventory Items (CSV)" max="2xl">
        <form method="POST" action="{{ route('web.inventory.import.run') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                <p class="text-sm font-medium text-slate-700 mb-1">Columns: <span class="font-mono text-xs">name, code, category, unit, reorder_min, reorder_max, current_stock</span></p>
                <ul class="text-xs text-slate-500 space-y-0.5 list-disc list-inside">
                    <li><b>name</b> is required. <b>category</b>: {{ implode(', ', array_keys(\App\Modules\Inventory\Models\InventoryItem::CATEGORIES)) }} (unknown → other).</li>
                    <li><b>unit</b>: {{ implode(', ', \App\Modules\Inventory\Models\InventoryItem::UNITS) }} (unknown → piece).</li>
                    <li>Items with a duplicate name are skipped — safe to re-run the same file.</li>
                </ul>
                <a href="{{ route('web.inventory.import.template') }}" class="btn-primary mt-2 text-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Download CSV template
                </a>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Upload CSV file</label>
                <input type="file" name="file" accept=".csv,.txt,.xlsx" class="block w-full text-sm text-slate-600 border border-slate-300 rounded-lg p-2">
            </div>
            <div class="text-center text-xs text-slate-400">— or paste rows below —</div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Paste CSV rows</label>
                <textarea name="rows" rows="5" class="input-field font-mono text-xs" placeholder="name,code,category,unit,reorder_min,reorder_max,current_stock&#10;Surgical Gloves (M),GLV-M,surgical,box,10,100,50"></textarea>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="showImport = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Import Items</button>
            </div>
        </form>
    </x-modal>
</div>

@push('scripts')
<script>
function inv() {
    return {
        tab: 'items', moveModal: false, itemModal: false, showImport: false,
        mv: { item_id: '', type: 'receipt', quantity: '' },
        item: { id: '', name: '', code: '', category: 'consumable', unit: 'piece', reorder_min: 0, reorder_max: 0, current_stock: 0, is_active: true },
        openMove(itemId) { this.mv = { item_id: itemId || '', type: 'receipt', quantity: '' }; this.moveModal = true; },
        openItem(i) { this.item = i ? { ...i } : { id: '', name: '', code: '', category: 'consumable', unit: 'piece', reorder_min: 0, reorder_max: 0, current_stock: 0, is_active: true }; this.itemModal = true; },
    };
}
</script>
@endpush
@endsection
