@extends('layouts.app')
@section('title', 'Super Admin — Hospitals')
@section('page-title', 'Multi-Hospital Dashboard')

@section('content')
<div x-data="superAdminHospitals()">
{{-- Top KPI row --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Hospitals</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ $hospitals->count() }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ $hospitals->where('is_active', true)->count() }} active</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Staff</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalStaff) }}</p>
        <p class="text-xs text-slate-400 mt-1">Active staff across all hospitals</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Patients</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalPatients) }}</p>
        <p class="text-xs text-slate-400 mt-1">All hospitals combined</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Appointments Today</p>
        <p class="text-3xl font-bold text-slate-800 mt-1">{{ number_format($totalAppointmentsToday) }}</p>
        <p class="text-xs text-slate-400 mt-1">Revenue: {{ \App\Modules\Core\Services\RegionService::currency() }} {{ number_format($totalRevenueToday, 2) }}</p>
    </div>
</div>

{{-- Action bar --}}
<div class="flex items-center justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $hospitals->count() }} hospitals registered</p>
    <a href="{{ route('web.superadmin.hospitals.create') }}" class="btn-primary">+ Add Hospital</a>
</div>

{{-- Hospital cards grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    @foreach($hospitals as $h)
    @php
        $region = $regions[$h->country] ?? $regions['IN'];
        $hStaff    = $staffCounts[$h->id] ?? 0;
        $hPatients = $patientCounts[$h->id] ?? 0;
        $hApts     = $aptCounts[$h->id] ?? 0;
        $hRev      = $revCounts[$h->id] ?? 0;
        $isCurrentHospital = auth()->user()?->hospital_id === $h->id;
    @endphp
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden {{ !$h->is_active ? 'opacity-50' : '' }} {{ $isCurrentHospital ? 'ring-2 ring-blue-400' : '' }}">
        {{-- Header --}}
        <div class="p-5 flex items-start justify-between">
            <div class="flex items-start gap-3">
                <span class="text-3xl">{{ $h->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $h->name }}</h3>
                    <p class="text-sm text-slate-500">{{ $h->city }}{{ $h->state ? ', ' . $h->state : '' }}</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        @if($h->is_active)
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded">ACTIVE</span>
                        @else
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded">INACTIVE</span>
                        @endif
                        @if($isCurrentHospital)
                            <span class="px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded">CURRENT</span>
                        @endif
                        <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-medium rounded">{{ $region['currency'] }} {{ $region['currency_code'] }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats row --}}
        <div class="grid grid-cols-4 border-t border-slate-100">
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hStaff }}</p>
                <p class="text-xs text-slate-500">Staff</p>
            </div>
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hPatients }}</p>
                <p class="text-xs text-slate-500">Patients</p>
            </div>
            <div class="p-3 text-center border-r border-slate-100">
                <p class="text-lg font-bold text-slate-800">{{ $hApts }}</p>
                <p class="text-xs text-slate-500">Appts Today</p>
            </div>
            <div class="p-3 text-center">
                <p class="text-lg font-bold text-slate-800">{{ $region['currency'] }}{{ number_format($hRev, 0) }}</p>
                <p class="text-xs text-slate-500">Revenue Today</p>
            </div>
        </div>

        {{-- Contact info --}}
        @if($h->phone || $h->email)
        <div class="px-5 py-2 bg-slate-50 border-t border-slate-100 text-xs text-slate-500">
            @if($h->phone)<span>{{ $h->phone }}</span>@endif
            @if($h->phone && $h->email)<span class="mx-1">&middot;</span>@endif
            @if($h->email)<span>{{ $h->email }}</span>@endif
        </div>
        @endif

        {{-- Actions --}}
        <div class="px-5 py-3 border-t border-slate-100 flex items-center gap-2">
            <a href="{{ route('web.superadmin.hospitals.show', $h->id) }}" class="btn-primary text-xs px-3 py-1.5">Manage</a>
            <button type="button"
                @click="openEdit({ id:'{{ $h->id }}', name: @js($h->name), slug: @js($h->slug), country: @js($h->country ?? 'IN'), city: @js($h->city ?? ''), state: @js($h->state ?? ''), address: @js($h->address ?? ''), phone: @js($h->phone ?? ''), email: @js($h->email ?? ''), is_active: {{ $h->is_active ? 'true' : 'false' }} })"
                class="btn-secondary text-xs px-3 py-1.5">Edit</button>
            @if(!$isCurrentHospital)
            <form method="POST" action="{{ route('switch-hospital') }}" class="inline">
                @csrf
                <input type="hidden" name="hospital_id" value="{{ $h->id }}">
                <button type="submit" class="btn-secondary text-xs px-3 py-1.5">Switch to This</button>
            </form>
            @endif
            @if($h->is_active && !$isCurrentHospital)
            <form method="POST" action="{{ route('web.superadmin.hospitals.delete', $h->id) }}" class="inline" onsubmit="return confirm('Deactivate this hospital?')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs px-3 py-1.5 text-red-600 hover:text-red-800 font-medium">Deactivate</button>
            </form>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- Edit Hospital modal --}}
<div x-show="editOpen" x-transition.opacity style="display:none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="editOpen = false">
    <div @click.away="editOpen = false" class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[88vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-bold text-slate-900">Edit Hospital</h3>
            <button type="button" @click="editOpen = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" :action="editAction" class="p-6 grid grid-cols-2 gap-4">
            @csrf @method('PUT')
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Hospital name *</label>
                <input type="text" name="name" required x-model="edit.name" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Slug *</label>
                <input type="text" name="slug" required x-model="edit.slug" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Country *</label>
                <select name="country" x-model="edit.country" class="input-field">
                    <option value="IN">India</option>
                    <option value="AE">UAE</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">City *</label>
                <input type="text" name="city" required x-model="edit.city" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">State</label>
                <input type="text" name="state" x-model="edit.state" class="input-field">
            </div>
            <div class="col-span-2">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
                <input type="text" name="address" x-model="edit.address" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                <input type="text" name="phone" x-model="edit.phone" class="input-field">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                <input type="email" name="email" x-model="edit.email" class="input-field">
            </div>
            <div class="col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" x-model="edit.is_active" class="rounded border-slate-300">
                    Active
                </label>
            </div>
            <div class="col-span-2 flex items-center gap-3 pt-2">
                <button type="submit" class="btn-primary px-5 py-2.5">Save Changes</button>
                <button type="button" @click="editOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
            </div>
        </form>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
function superAdminHospitals() {
    return {
        editOpen: false,
        edit: { id:'', name:'', slug:'', country:'IN', city:'', state:'', address:'', phone:'', email:'', is_active:true },
        openEdit(h) { this.edit = Object.assign({}, h); this.editOpen = true; },
        get editAction() { return '{{ url('super-admin/hospitals') }}/' + this.edit.id; },
    };
}
</script>
@endpush
