@extends('layouts.app')
@section('title', $hospital->name . ' — Super Admin')
@section('page-title', $hospital->name)

@section('content')
<div x-data="hospitalDetail()">

    {{-- Back link --}}
    <a href="{{ route('web.superadmin.index') }}" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to All Hospitals
    </a>

    {{-- Backup & Restore --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <h3 class="text-base font-bold text-slate-900">Backup &amp; Restore</h3>
                <p class="text-sm text-slate-500 mt-0.5">Download a full <strong>.json</strong> backup of {{ $hospital->name }}, or restore one. Restore re-adds missing rows into this hospital and never overwrites existing data.</p>
            </div>
            <a href="{{ route('web.superadmin.hospitals.backup', $hospital->id) }}" class="btn-primary text-xs px-3 py-1.5 whitespace-nowrap shrink-0">⤓ Download Backup</a>
        </div>
        <form method="POST" action="{{ route('web.superadmin.hospitals.restore', $hospital->id) }}" enctype="multipart/form-data"
              class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100"
              onsubmit="return confirm('Restore this backup INTO {{ $hospital->name }}? Missing rows are re-added; existing rows are left as-is.')">
            @csrf
            <input type="file" name="file" accept=".json,application/json" required
                   class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium whitespace-nowrap">Restore from backup</button>
        </form>
    </div>

    {{-- Hospital Info Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <span class="text-4xl">{{ $hospital->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">{{ $hospital->name }}</h2>
                    <p class="text-slate-500">{{ $hospital->city }}{{ $hospital->state ? ', ' . $hospital->state : '' }} &middot; {{ $hospital->country }}</p>
                    @if($hospital->address)
                        <p class="text-sm text-slate-400 mt-1">{{ $hospital->address }}</p>
                    @endif
                    <div class="flex flex-wrap gap-3 mt-2 text-sm text-slate-500">
                        @if($hospital->phone)<span>{{ $hospital->phone }}</span>@endif
                        @if($hospital->email)<span>{{ $hospital->email }}</span>@endif
                    </div>
                    <div class="flex gap-2 mt-3">
                        @if($hospital->is_active)
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded">ACTIVE</span>
                        @else
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs font-bold rounded">INACTIVE</span>
                        @endif
                        <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs font-medium rounded">{{ $region['currency'] }} {{ $region['currency_code'] }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('web.superadmin.hospitals.edit', $hospital->id) }}" class="btn-secondary text-sm px-4 py-2">Edit Hospital</a>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500 uppercase">Staff</p>
            <p class="text-2xl font-bold text-slate-800">{{ $staff->count() + $pivotStaff->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500 uppercase">Patients</p>
            <p class="text-2xl font-bold text-slate-800">{{ number_format($patientCount) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500 uppercase">Appointments Today</p>
            <p class="text-2xl font-bold text-slate-800">{{ $appointmentCount }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
            <p class="text-xs font-medium text-slate-500 uppercase">Revenue Today</p>
            <p class="text-2xl font-bold text-slate-800">{{ $region['currency'] }}{{ number_format($revenueToday, 0) }}</p>
            <p class="text-xs text-slate-400">Total: {{ $region['currency'] }}{{ number_format($totalRevenue, 0) }}</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 mb-4 bg-slate-100 rounded-lg p-1 w-fit">
        <button @click="tab = 'staff'" :class="tab === 'staff' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2 rounded-md text-sm font-medium transition-colors">Staff ({{ $staff->count() + $pivotStaff->count() }})</button>
        <button @click="tab = 'admins'" :class="tab === 'admins' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2 rounded-md text-sm font-medium transition-colors">Admins ({{ $admins->count() + $pivotAdmins->count() }})</button>
        <button @click="tab = 'modules'" :class="tab === 'modules' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500 hover:text-slate-700'" class="px-4 py-2 rounded-md text-sm font-medium transition-colors">Modules</button>
    </div>

    {{-- Modules Tab --}}
    <div x-show="tab === 'modules'" style="display:none" class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-800">Enabled Modules</h3>
            <p class="text-xs text-slate-500">Control which features this hospital has access to.</p>
        </div>
        <form method="POST" action="{{ route('web.superadmin.hospitals.modules', $hospital->id) }}">
            @csrf
            <div class="space-y-5">
                @foreach(\App\Modules\Core\Support\ModuleCatalog::byCategory() as $cat => $mods)
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ $cat }}</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($mods as $key => $meta)
                        <label class="flex items-start gap-2 p-2.5 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" {{ $hospital->isModuleEnabled($key) ? 'checked' : '' }} class="mt-0.5 rounded border-slate-300">
                            <span>
                                <span class="block text-sm font-medium text-slate-800">{{ $meta['name'] }}</span>
                                <span class="block text-xs text-slate-500">{{ $meta['description'] }}</span>
                            </span>
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="flex justify-end mt-5">
                <button type="submit" class="btn-primary">Save modules</button>
            </div>
        </form>
    </div>

    {{-- Staff Tab --}}
    <div x-show="tab === 'staff'" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Staff Members</h3>
            <button @click="showAddStaff = true" class="btn-primary text-sm px-3 py-1.5">+ Add Staff</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Role</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Department</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($staff as $s)
                    @php $role = is_object($s->role) ? $s->role->value : ($s->role ?? ''); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $s->name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">{{ ucwords(str_replace('_', ' ', $role)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $s->department ?? '' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $s->email ?? '' }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-slate-400">Primary</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.staff.remove', [$hospital->id, $s->id]) }}" class="inline" onsubmit="return confirm('Remove this staff from {{ $hospital->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                    @foreach($pivotStaff as $s)
                    @php
                        $pData = $pivotData[$s->id] ?? null;
                        $pRole = $pData?->role ?? (is_object($s->role) ? $s->role->value : ($s->role ?? ''));
                    @endphp
                    <tr class="hover:bg-slate-50 bg-amber-50/30">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $s->name }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded">{{ ucwords(str_replace('_', ' ', $pRole)) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $pData?->department ?? $s->department ?? '' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $s->email ?? '' }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-amber-600">Multi-hospital</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.staff.remove', [$hospital->id, $s->id]) }}" class="inline" onsubmit="return confirm('Remove this staff from {{ $hospital->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Remove</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($staff->isEmpty() && $pivotStaff->isEmpty())
        <div class="p-8 text-center text-slate-400">
            <p>No staff assigned to this hospital yet.</p>
        </div>
        @endif
    </div>

    {{-- Admins Tab --}}
    <div x-show="tab === 'admins'" style="display:none" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Hospital Admins</h3>
            <button @click="showAddAdmin = true" class="btn-primary text-sm px-3 py-1.5">+ Add Admin</button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($admins as $a)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $a->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $a->email }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-slate-400">Primary</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.users.reset', [$hospital->id, $a->id]) }}" class="inline" onsubmit="return confirm('Reset password for {{ $a->name }}? You will see the new password to share.')">
                                @csrf
                                <button type="submit" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Reset password</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                    @foreach($pivotAdmins as $a)
                    <tr class="hover:bg-slate-50 bg-amber-50/30">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $a->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $a->email }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-amber-600">Multi-hospital</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.users.reset', [$hospital->id, $a->id]) }}" class="inline" onsubmit="return confirm('Reset password for {{ $a->name }}?')">
                                @csrf
                                <button type="submit" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Reset password</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($admins->isEmpty() && $pivotAdmins->isEmpty())
        <div class="p-8 text-center text-slate-400">
            <p>No admins assigned to this hospital yet.</p>
        </div>
        @endif
    </div>

    {{-- Add Staff Modal --}}
    <x-modal show="showAddStaff" title="Add Staff to {{ $hospital->name }}" max="lg" body-class="">
            {{-- Mode switcher --}}
            <div class="px-6 pt-4">
                <div class="flex gap-1 bg-slate-100 rounded-lg p-1">
                    <button @click="staffMode = 'existing'" :class="staffMode === 'existing' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors">Add Existing Staff</button>
                    <button @click="staffMode = 'new'" :class="staffMode === 'new' ? 'bg-white shadow-sm text-slate-900' : 'text-slate-500'" class="flex-1 px-3 py-2 rounded-md text-sm font-medium transition-colors">Create New Staff</button>
                </div>
            </div>

            {{-- Add existing staff --}}
            <form x-show="staffMode === 'existing'" method="POST" action="{{ route('web.superadmin.hospitals.staff.add', $hospital->id) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Select Staff</label>
                    <select name="staff_id" required class="input-field">
                        <option value="">-- Choose staff member --</option>
                        @foreach($availableStaff as $as)
                        @php $asRole = is_object($as->role) ? $as->role->value : ($as->role ?? ''); @endphp
                        <option value="{{ $as->id }}">{{ $as->name }} ({{ ucwords(str_replace('_', ' ', $asRole)) }}{{ $as->department ? ' - ' . $as->department : '' }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Role at this hospital</label>
                        <select name="role" class="input-field">
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="lab_tech">Lab Technician</option>
                            <option value="billing_staff">Billing Staff</option>
                            <option value="hospital_admin">Hospital Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Department</label>
                        <input type="text" name="department" class="input-field" placeholder="e.g. Cardiology">
                    </div>
                </div>
                <button type="submit" class="btn-primary w-full py-2.5">Assign Staff</button>
            </form>

            {{-- Create new staff --}}
            <form x-show="staffMode === 'new'" style="display:none" method="POST" action="{{ route('web.superadmin.hospitals.staff.add', $hospital->id) }}" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="input-field" placeholder="Dr. John Doe">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="input-field" placeholder="john@hospital.com">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Role *</label>
                        <select name="role" required class="input-field">
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="lab_tech">Lab Technician</option>
                            <option value="billing_staff">Billing Staff</option>
                            <option value="hospital_admin">Hospital Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Department</label>
                        <input type="text" name="department" class="input-field" placeholder="e.g. Pediatrics">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" class="input-field" placeholder="Leave blank for default (password123)">
                </div>
                <button type="submit" class="btn-primary w-full py-2.5">Create & Assign Staff</button>
            </form>
    </x-modal>

    {{-- Add Admin Modal --}}
    <x-modal show="showAddAdmin" title="Add Admin to {{ $hospital->name }}" max="lg">
            <form method="POST" action="{{ route('web.superadmin.hospitals.admin.add', $hospital->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="input-field" placeholder="Admin Name">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="input-field" placeholder="admin@hospital.com">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" class="input-field" placeholder="Leave blank for default (password123)">
                </div>
                <button type="submit" class="btn-primary w-full py-2.5">Create Admin</button>
            </form>
    </x-modal>
</div>
@endsection

@push('scripts')
<script>
function hospitalDetail() {
    return {
        tab: 'staff',
        showAddStaff: false,
        showAddAdmin: false,
        staffMode: 'existing',
    };
}
</script>
@endpush
