@extends('layouts.app')
@section('title', 'Vendors')
@section('page-title', 'Asset Management')

@section('content')
<div x-data="{
        editOpen: false,
        mode: 'add',
        blank: { id:'', name:'', contact_person:'', phone:'', email:'', address:'', service_type:'' },
        edit: {},
        openAdd() { this.edit = Object.assign({}, this.blank); this.mode = 'add'; this.editOpen = true; },
        openEdit(v) { this.edit = Object.assign({}, v); this.mode = 'edit'; this.editOpen = true; },
        get formAction() { return this.mode === 'edit' ? '{{ url('admin/vendors') }}/' + this.edit.id : '{{ url('admin/vendors') }}'; }
    }">

    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.admin.assets.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Dashboard</a>
        <a href="{{ route('web.admin.assets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Asset Register</a>
        <a href="{{ route('web.admin.vendors.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Vendors</a>
        <a href="{{ route('web.admin.tickets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Service Requests</a>
        <button @click="openAdd()" class="ml-auto btn-primary">+ Add Vendor</button>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Vendor</th>
                    <th class="table-header">Contact</th>
                    <th class="table-header">Service</th>
                    <th class="table-header">Assets</th>
                    <th class="table-header w-24"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($vendors as $v)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $v->name }}</td>
                        <td class="table-cell text-slate-600">
                            {{ $v->contact_person ?? '-' }}
                            <span class="block text-xs text-slate-400">{{ $v->phone }}{{ $v->email ? ' · ' . $v->email : '' }}</span>
                        </td>
                        <td class="table-cell">{{ $v->service_type ?? '-' }}</td>
                        <td class="table-cell">{{ $v->assets_count }}</td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openEdit({ id:'{{ $v->id }}', name: @js($v->name), contact_person: @js($v->contact_person ?? ''), phone: @js($v->phone ?? ''), email: @js($v->email ?? ''), address: @js($v->address ?? ''), service_type: @js($v->service_type ?? '') })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                                <form method="POST" action="{{ route('web.admin.vendors.destroy', $v->id) }}" onsubmit="return confirm('Remove this vendor?')">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-400 py-10">No vendors yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Edit modal --}}
    <x-modal show="editOpen" title-expr="mode === 'edit' ? 'Edit Vendor' : 'Add Vendor'" max="lg">
            <form method="POST" :action="formAction" class="grid grid-cols-2 gap-4">
                @csrf
                <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Name *</label><input type="text" name="name" required x-model="edit.name" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Contact person</label><input type="text" name="contact_person" x-model="edit.contact_person" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Service type</label><input type="text" name="service_type" x-model="edit.service_type" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label><input type="text" name="phone" x-model="edit.phone" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Email</label><input type="email" name="email" x-model="edit.email" class="input-field"></div>
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Address</label><input type="text" name="address" x-model="edit.address" class="input-field"></div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5" x-text="mode === 'edit' ? 'Save Changes' : 'Add Vendor'"></button>
                    <button type="button" @click="editOpen = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
