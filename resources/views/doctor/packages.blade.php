@extends('layouts.app')
@section('title', 'My Packages')
@section('page-title', 'My Treatment Packages')

@section('content')
<div class="max-w-3xl">
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
            <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Add package --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Add a Package</h3>
        <p class="text-xs text-slate-400 mb-4">Patients see your active packages in the chat bot when they pick you, and can book an appointment with one.</p>
        <form method="POST" action="{{ route('web.doctor.packages.store') }}" class="space-y-3">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Package name *</label>
                    <input type="text" name="name" required maxlength="120" class="input-field" placeholder="e.g. Diabetes Care Plan">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Price ({{ \App\Modules\Core\Services\RegionService::currency() }}) *</label>
                    <input type="number" name="price" required min="0" step="0.01" class="input-field" placeholder="1500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                <input type="text" name="description" maxlength="500" class="input-field" placeholder="e.g. Consultation + HbA1c test + diet plan">
            </div>
            <button type="submit" class="btn-primary px-5 py-2.5">Add Package</button>
        </form>
    </div>

    {{-- List --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Package</th>
                    <th class="table-header">Price</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Actions</th>
                </tr>
            </thead>
            @forelse($packages as $pkg)
            <tbody x-data="{ editing: false }" class="divide-y divide-slate-100 border-t border-slate-100">
                {{-- Display row --}}
                <tr x-show="!editing" class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-slate-800">{{ $pkg->name }}</p>
                        @if($pkg->description)<p class="text-xs text-slate-500">{{ $pkg->description }}</p>@endif
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ \App\Modules\Core\Services\RegionService::formatMoney($pkg->price) }}</td>
                    <td class="px-4 py-3">
                        @if($pkg->is_active)
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Hidden</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="editing = true" class="text-xs text-slate-700 hover:text-slate-900 font-medium">Edit</button>
                            <form method="POST" action="{{ route('web.doctor.packages.toggle', $pkg->id) }}">
                                @csrf
                                <button class="text-xs text-blue-600 hover:text-blue-800 font-medium">{{ $pkg->is_active ? 'Hide' : 'Activate' }}</button>
                            </form>
                            <form method="POST" action="{{ route('web.doctor.packages.delete', $pkg->id) }}" onsubmit="return confirm('Delete this package?')">
                                @csrf
                                <button class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                {{-- Edit row --}}
                <tr x-show="editing" style="display:none">
                    <td colspan="4" class="px-4 py-3 bg-slate-50">
                        <form method="POST" action="{{ route('web.doctor.packages.update', $pkg->id) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <div class="flex-1 min-w-[180px]">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Name</label>
                                <input type="text" name="name" required maxlength="120" value="{{ $pkg->name }}" class="input-field">
                            </div>
                            <div class="w-32">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Price</label>
                                <input type="number" name="price" required min="0" step="0.01" value="{{ $pkg->price }}" class="input-field">
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                                <input type="text" name="description" maxlength="500" value="{{ $pkg->description }}" class="input-field">
                            </div>
                            <button type="submit" class="btn-primary px-4 py-2 text-sm">Save</button>
                            <button type="button" @click="editing = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                        </form>
                    </td>
                </tr>
            </tbody>
            @empty
            <tbody>
                <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">No packages yet. Add one above — it'll appear in the chat bot for patients booking with you.</td></tr>
            </tbody>
            @endforelse
        </table>
    </div>
</div>
@endsection
