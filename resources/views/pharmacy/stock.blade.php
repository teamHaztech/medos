@extends('layouts.app')
@section('title', 'Pharmacy Stock')
@section('page-title', 'Pharmacy Stock')

@section('content')
<div x-data="stockManager()">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ $stocks->count() }} stock entries</p>
        <button @click="showAdd = true" class="btn-primary">+ Add Stock</button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stock Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Medicine</th>
                    <th class="table-header">Batch</th>
                    <th class="table-header">Expiry Date</th>
                    <th class="table-header">Available / Total</th>
                    <th class="table-header">Purchase Price</th>
                    <th class="table-header">Selling Price</th>
                    <th class="table-header">Supplier</th>
                    <th class="table-header w-20">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($stocks as $stock)
                    @php
                        $expiryDays = now()->diffInDays($stock->expiry_date, false);
                        $isExpiringSoon = $expiryDays >= 0 && $expiryDays <= 30;
                        $isLowStock = $stock->quantity_available < 10;
                        $rowClass = '';
                        if ($isLowStock) $rowClass = 'bg-red-50';
                        elseif ($isExpiringSoon) $rowClass = 'bg-amber-50';
                    @endphp
                    <tr class="{{ $rowClass }} hover:bg-slate-50">
                        <td class="table-cell font-medium">
                            {{ $stock->medicine_name }}
                            @if($stock->generic_name)
                                <span class="block text-xs text-slate-400">{{ $stock->generic_name }}</span>
                            @endif
                        </td>
                        <td class="table-cell">{{ $stock->batch_number }}</td>
                        <td class="table-cell">
                            {{ $stock->expiry_date->format('d M Y') }}
                            @if($isExpiringSoon)
                                <span class="block text-xs text-amber-600 font-medium">Expiring soon</span>
                            @endif
                        </td>
                        <td class="table-cell">
                            <span class="{{ $isLowStock ? 'text-red-600 font-bold' : '' }}">{{ $stock->quantity_available }}</span>
                            / {{ $stock->quantity_total }}
                            @if($isLowStock)
                                <span class="block text-xs text-red-600 font-medium">Low stock</span>
                            @endif
                        </td>
                        <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($stock->purchase_price, 2) }}</td>
                        <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($stock->selling_price, 2) }}</td>
                        <td class="table-cell">{{ $stock->supplier }}</td>
                        <td class="table-cell">
                            <button type="button"
                                @click="openEdit({ id:'{{ $stock->id }}', medicine_name: @js($stock->medicine_name), quantity_available: '{{ $stock->quantity_available }}', selling_price: '{{ $stock->selling_price }}' })"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="table-cell text-center text-slate-400 py-8">No stock entries. Click "Add Stock" to begin.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Add modal --}}
    <x-modal show="showAdd" title="Add Stock" max="2xl">
        <form method="POST" action="{{ route('web.pharmacy.stock.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Medicine *</label>
                <select name="medicine_id" required class="input-field" x-model="selectedMedicine">
                    <option value="">Select medicine...</option>
                    @foreach($medicines as $med)
                        <option value="{{ $med->id }}">{{ $med->name }} ({{ $med->generic_name }}) - {{ $med->form }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Batch Number *</label>
                <input type="text" name="batch_number" required class="input-field" placeholder="e.g. BT-2026-001">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Expiry Date *</label>
                <input type="date" name="expiry_date" required class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Quantity *</label>
                <input type="number" name="quantity_total" required min="1" class="input-field" placeholder="Total units">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Supplier *</label>
                <input type="text" name="supplier" required class="input-field" placeholder="Supplier name">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Purchase Price *</label>
                <input type="number" name="purchase_price" required step="0.01" min="0" class="input-field" placeholder="0.00">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Selling Price *</label>
                <input type="number" name="selling_price" required step="0.01" min="0" class="input-field" placeholder="0.00">
            </div>
            <div class="md:col-span-2 flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary px-5 py-2.5">Save Stock</button>
                <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </x-modal>

    {{-- Edit modal (same UX as Add) --}}
    <x-modal show="editOpen" title="Edit Stock" max="lg">
        <form method="POST" :action="editAction" class="grid grid-cols-2 gap-4">
            @csrf @method('PUT')
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Medicine</label>
                <input type="text" class="input-field bg-slate-50 text-slate-500" x-model="edit.medicine_name" readonly>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Available Qty *</label>
                <input type="number" name="quantity_available" x-model="edit.quantity_available" required min="0" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Selling Price *</label>
                <input type="number" name="selling_price" x-model="edit.selling_price" required step="0.01" min="0" class="input-field">
            </div>
            <div class="col-span-2 flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary px-5 py-2.5">Save Changes</button>
                <button type="button" @click="editOpen = false" class="btn-secondary">Cancel</button>
            </div>
        </form>
    </x-modal>
</div>

<script>
function stockManager() {
    return {
        showAdd: false,
        selectedMedicine: '',
        editOpen: false,
        edit: { id: '', medicine_name: '', quantity_available: '', selling_price: '' },
        openEdit(s) { this.edit = Object.assign({}, s); this.editOpen = true; },
        get editAction() { return '{{ url('pharmacy/stock') }}/' + this.edit.id; }
    }
}
</script>
@endsection
