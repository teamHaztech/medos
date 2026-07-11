@extends('layouts.app')
@section('title', 'Wards & Beds')
@section('page-title', 'Inpatients / Wards & Beds')

@section('content')
<div x-data="{ addWardOpen: false, addBedFor: null }">
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.ip.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Ward Board</a>
        <a href="{{ route('web.ip.admissions') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Admissions</a>
        <a href="{{ route('web.ip.adt') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">ADT Tracking</a>
        <a href="{{ route('web.ip.wards') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Wards &amp; Beds</a>
        <button @click="addWardOpen = true" class="ml-auto btn-primary">+ Add Ward</button>
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    @forelse($wards as $ward)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-base font-semibold text-slate-800">{{ $ward->name }}
                    <span class="text-xs font-normal text-slate-400">{{ $ward->ward_type ? '· ' . $ward->ward_type : '' }}{{ $ward->floor ? ' · Floor ' . $ward->floor : '' }} · {{ $ward->beds->count() }} beds</span>
                </h3>
                <div class="flex items-center gap-2">
                    <button @click="addBedFor = (addBedFor === '{{ $ward->id }}' ? null : '{{ $ward->id }}')" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Bed</button>
                    <form method="POST" action="{{ route('web.ip.wards.destroy', $ward->id) }}" onsubmit="return confirm('Remove {{ $ward->name }}?')">@csrf @method('DELETE')<button class="text-sm text-red-400 hover:text-red-600">Remove ward</button></form>
                </div>
            </div>

            <form x-show="addBedFor === '{{ $ward->id }}'" style="display:none" method="POST" action="{{ route('web.ip.beds.store', $ward->id) }}" class="flex items-end gap-2 mb-3 p-3 bg-slate-50 rounded-lg">
                @csrf
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Bed number</label><input type="text" name="bed_number" required class="input-field" placeholder="G-07"></div>
                <button type="submit" class="btn-success">Add bed</button>
            </form>

            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                @foreach($ward->beds as $bed)
                    <div class="rounded-lg border p-3 {{ $bed->status === 'occupied' ? 'bg-blue-50 border-blue-200' : ($bed->status === 'maintenance' ? 'bg-slate-100 border-slate-200' : 'bg-green-50 border-green-200') }}">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold text-slate-800">{{ $bed->bed_number }}</span>
                            <span class="text-[10px] text-slate-500">{{ $bed->statusLabel() }}</span>
                        </div>
                        @if($bed->status !== 'occupied')
                        <div class="flex items-center gap-2 mt-2">
                            @if($bed->status === 'available')
                                <form method="POST" action="{{ route('web.ip.beds.update', $bed->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="maintenance"><button class="text-[11px] text-amber-600 hover:text-amber-800">Maintenance</button></form>
                            @else
                                <form method="POST" action="{{ route('web.ip.beds.update', $bed->id) }}">@csrf @method('PUT')<input type="hidden" name="status" value="available"><button class="text-[11px] text-green-600 hover:text-green-800">Free up</button></form>
                            @endif
                            <form method="POST" action="{{ route('web.ip.beds.destroy', $bed->id) }}" onsubmit="return confirm('Remove bed {{ $bed->bed_number }}?')">@csrf @method('DELETE')<button class="text-[11px] text-red-400 hover:text-red-600">Remove</button></form>
                        </div>
                        @endif
                    </div>
                @endforeach
                @if($ward->beds->isEmpty())<p class="col-span-full text-sm text-slate-400">No beds yet — add some.</p>@endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-10 text-center"><p class="text-slate-500">No wards yet. Add your first ward.</p></div>
    @endforelse

    {{-- Add ward modal --}}
    <x-modal show="addWardOpen" title="Add Ward" max="md">
            <form method="POST" action="{{ route('web.ip.wards.store') }}" class="grid grid-cols-2 gap-4">
                @csrf
                <div class="col-span-2"><label class="block text-xs font-semibold text-slate-600 mb-1">Ward name *</label><input type="text" name="name" required class="input-field" placeholder="General Ward B"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="ward_type" class="input-field"><option value="">—</option>@foreach(\App\Modules\Inpatient\Models\Ward::TYPES as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Floor</label><input type="text" name="floor" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1"># Beds to create</label><input type="number" name="bed_count" min="0" max="200" value="0" class="input-field"></div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Bed prefix</label><input type="text" name="bed_prefix" maxlength="10" class="input-field" placeholder="G"></div>
                <div class="col-span-2 flex items-center gap-3 pt-1"><button type="submit" class="btn-primary px-5 py-2.5">Create Ward</button><button type="button" @click="addWardOpen = false" class="text-sm text-slate-500 px-2 py-2">Cancel</button></div>
            </form>
    </x-modal>
</div>
@endsection
