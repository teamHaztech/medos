@extends('layouts.app')
@section('title', 'Manage Medicines')
@section('page-title', 'Medicines')

@php $forms = ['tablet','capsule','syrup','injection','cream','drops','inhaler','ointment','gel','sachet','spray']; @endphp

@section('content')
<div x-data="{
        showAdd: false,
        search: '',
        editOpen: false,
        edit: { id:'', name:'', generic_name:'', category:'', default_dosage:'', form:'tablet' },
        openEdit(m) { this.edit = Object.assign({}, m); this.editOpen = true; },
        get editAction() { return '{{ url('admin/medicines') }}/' + this.edit.id; }
    }">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <input type="text" x-model="search" class="input-field w-64" placeholder="Search medicines...">
            <p class="text-sm text-slate-500">{{ $medicines->count() }} medicines</p>
        </div>
        <button @click="showAdd = !showAdd" class="btn-primary">+ Add Medicine</button>
    </div>

    <div x-show="showAdd" style="display:none" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
        <form method="POST" action="{{ route('web.admin.medicines.store') }}" class="grid grid-cols-2 lg:grid-cols-5 gap-3">
            @csrf
            <input type="text" name="name" required class="input-field" placeholder="Medicine name *">
            <input type="text" name="generic_name" class="input-field" placeholder="Generic name">
            <input type="text" name="category" class="input-field" placeholder="Category">
            <input type="text" name="default_dosage" class="input-field" placeholder="Dosage (500mg)">
            <select name="form" class="input-field">
                @foreach($forms as $f)<option value="{{ $f }}">{{ ucfirst($f) }}</option>@endforeach
            </select>
            <button type="submit" class="btn-success">Save</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Name</th>
                    <th class="table-header">Generic</th>
                    <th class="table-header">Category</th>
                    <th class="table-header">Dosage</th>
                    <th class="table-header">Form</th>
                    <th class="table-header w-24"></th>
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
                        <div class="flex items-center gap-2">
                            <button type="button"
                                @click="openEdit({ id:'{{ $med->id }}', name: @js($med->name), generic_name: @js($med->generic_name ?? ''), category: @js($med->category ?? ''), default_dosage: @js($med->default_dosage ?? ''), form: @js($med->form ?? 'tablet') })"
                                class="text-sm font-medium text-blue-600 hover:text-blue-800">Edit</button>
                            <form method="POST" action="{{ route('web.admin.medicines.delete', $med->id) }}" onsubmit="return confirm('Remove?')">
                                @csrf @method('DELETE')
                                <button class="text-red-400 hover:text-red-600 text-sm">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit Medicine" max="lg">
            <form method="POST" :action="editAction" class="grid grid-cols-2 gap-4">
                @csrf @method('PUT')
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Name *</label>
                    <input type="text" name="name" required x-model="edit.name" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Generic name</label>
                    <input type="text" name="generic_name" x-model="edit.generic_name" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                    <input type="text" name="category" x-model="edit.category" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Dosage</label>
                    <input type="text" name="default_dosage" x-model="edit.default_dosage" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Form</label>
                    <select name="form" x-model="edit.form" class="input-field">
                        @foreach($forms as $f)<option value="{{ $f }}">{{ ucfirst($f) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5">Save Changes</button>
                    <button type="button" @click="editOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
