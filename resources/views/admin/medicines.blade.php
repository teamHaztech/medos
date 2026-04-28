@extends('layouts.app')
@section('title', 'Manage Medicines')
@section('page-title', 'Medicines')

@section('content')
<div x-data="{ showAdd: false, search: '' }">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <input type="text" x-model="search" class="input-field w-64" placeholder="Search medicines...">
            <p class="text-sm text-slate-500">{{ $medicines->count() }} medicines</p>
        </div>
        <button @click="showAdd = !showAdd" class="btn-primary">+ Add Medicine</button>
    </div>

    <template x-if="showAdd">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
            <form method="POST" action="{{ route('web.admin.medicines.store') }}" class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                @csrf
                <input type="text" name="name" required class="input-field" placeholder="Medicine name *">
                <input type="text" name="generic_name" class="input-field" placeholder="Generic name">
                <input type="text" name="category" class="input-field" placeholder="Category">
                <input type="text" name="default_dosage" class="input-field" placeholder="Dosage (500mg)">
                <select name="form" class="input-field">
                    <option value="tablet">Tablet</option>
                    <option value="capsule">Capsule</option>
                    <option value="syrup">Syrup</option>
                    <option value="injection">Injection</option>
                    <option value="cream">Cream</option>
                    <option value="drops">Drops</option>
                    <option value="inhaler">Inhaler</option>
                    <option value="ointment">Ointment</option>
                    <option value="gel">Gel</option>
                    <option value="sachet">Sachet</option>
                    <option value="spray">Spray</option>
                </select>
                <button type="submit" class="btn-success">Save</button>
            </form>
        </div>
    </template>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Name</th>
                    <th class="table-header">Generic</th>
                    <th class="table-header">Category</th>
                    <th class="table-header">Dosage</th>
                    <th class="table-header">Form</th>
                    <th class="table-header w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($medicines as $med)
                <tr class="hover:bg-slate-50" x-show="!search || '{{ strtolower($med->name . ' ' . ($med->generic_name ?? '') . ' ' . ($med->category ?? '')) }}'.includes(search.toLowerCase())">
                    <td class="table-cell font-medium">{{ $med->name }}</td>
                    <td class="table-cell text-slate-500">{{ $med->generic_name }}</td>
                    <td class="table-cell"><span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs">{{ $med->category }}</span></td>
                    <td class="table-cell">{{ $med->default_dosage }}</td>
                    <td class="table-cell capitalize">{{ $med->form }}</td>
                    <td class="table-cell">
                        <form method="POST" action="{{ route('web.admin.medicines.delete', $med->id) }}" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button class="text-red-400 hover:text-red-600 text-sm">Remove</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
