@extends('layouts.app')
@section('title', 'Pharmacy Stock')
@section('page-title', 'Pharmacy Stock')

@section('content')
<div x-data="stockManager()">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ $stocks->count() }} stock entries</p>
        <button @click="showAdd = !showAdd" class="btn-primary">+ Add Stock</button>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Add Stock Modal --}}
    <template x-if="showAdd">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-4">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Add New Stock</h3>
            <form method="POST" action="{{ route('web.pharmacy.stock.store') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                @csrf
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Medicine</label>
                    <select name="medicine_id" required class="input-field w-full" x-model="selectedMedicine">
                        <option value="">Select medicine...</option>
                        @foreach($medicines as $med)
                            <option value="{{ $med->id }}">{{ $med->name }} ({{ $med->generic_name }}) - {{ $med->form }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Batch Number</label>
                    <input type="text" name="batch_number" required class="input-field w-full" placeholder="e.g. BT-2026-001">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Expiry Date</label>
                    <input type="date" name="expiry_date" required class="input-field w-full">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Quantity</label>
                    <input type="number" name="quantity_total" required min="1" class="input-field w-full" placeholder="Total units">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Purchase Price</label>
                    <input type="number" name="purchase_price" required step="0.01" min="0" class="input-field w-full" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Selling Price</label>
                    <input type="number" name="selling_price" required step="0.01" min="0" class="input-field w-full" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                    <input type="text" name="supplier" required class="input-field w-full" placeholder="Supplier name">
                </div>
                <div class="lg:col-span-4 flex items-center gap-2 pt-2">
                    <button type="submit" class="btn-success">Save Stock</button>
                    <button type="button" @click="showAdd = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </template>

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
                    <tr class="{{ $rowClass }} hover:bg-slate-50" x-data="{ editing: false }">
                        <template x-if="!editing">
                            <td class="table-cell font-medium">
                                {{ $stock->medicine_name }}
                                @if($stock->generic_name)
                                    <span class="block text-xs text-slate-400">{{ $stock->generic_name }}</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">{{ $stock->batch_number }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">
                                {{ $stock->expiry_date->format('d M Y') }}
                                @if($isExpiringSoon)
                                    <span class="block text-xs text-amber-600 font-medium">Expiring soon</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">
                                <span class="{{ $isLowStock ? 'text-red-600 font-bold' : '' }}">{{ $stock->quantity_available }}</span>
                                / {{ $stock->quantity_total }}
                                @if($isLowStock)
                                    <span class="block text-xs text-red-600 font-medium">Low stock</span>
                                @endif
                            </td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($stock->purchase_price, 2) }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($stock->selling_price, 2) }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">{{ $stock->supplier }}</td>
                        </template>
                        <template x-if="!editing">
                            <td class="table-cell">
                                <button @click="editing = true" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</button>
                            </td>
                        </template>

                        {{-- Inline Edit --}}
                        <template x-if="editing">
                            <td colspan="8" class="table-cell">
                                <form method="POST" action="{{ route('web.pharmacy.stock.update', $stock->id) }}" class="flex items-center gap-3 flex-wrap">
                                    @csrf @method('PUT')
                                    <span class="text-sm font-medium text-slate-700">{{ $stock->medicine_name }}</span>
                                    <div>
                                        <label class="text-xs text-slate-500">Available Qty</label>
                                        <input type="number" name="quantity_available" value="{{ $stock->quantity_available }}" min="0" class="input-field w-24">
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500">Selling Price</label>
                                        <input type="number" name="selling_price" value="{{ $stock->selling_price }}" step="0.01" min="0" class="input-field w-28">
                                    </div>
                                    <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium">Save</button>
                                    <button type="button" @click="editing = false" class="text-slate-400 hover:text-slate-600 text-sm">Cancel</button>
                                </form>
                            </td>
                        </template>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="table-cell text-center text-slate-400 py-8">No stock entries. Click "Add Stock" to begin.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function stockManager() {
    return {
        showAdd: false,
        selectedMedicine: ''
    }
}
</script>
@endsection
