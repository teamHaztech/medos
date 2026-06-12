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
                        <td class="px-4 py-3 text-slate-600">{{ $s->department ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $s->email ?? '-' }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-slate-400">Primary</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.staff.remove', [$hospital->id, $s->id]) }}" class="inline" onsubmit="return confirm('Remove this staff from {{ $hospital->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Remove</button>
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
                        <td class="px-4 py-3 text-slate-600">{{ $pData?->department ?? $s->department ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $s->email ?? '-' }}</td>
                        <td class="px-4 py-3"><span class="text-xs text-amber-600">Multi-hospital</span></td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('web.superadmin.hospitals.staff.remove', [$hospital->id, $s->id]) }}" class="inline" onsubmit="return confirm('Remove this staff from {{ $hospital->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:text-red-800 font-medium">Remove</button>
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
    <div x-show="showAddStaff" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display:none">
        <div @click.away="showAddStaff = false" class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">Add Staff to {{ $hospital->name }}</h3>
                <button @click="showAddStaff = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

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
        </div>
    </div>

    {{-- Add Admin Modal --}}
    <div x-show="showAddAdmin" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" style="display:none">
        <div @click.away="showAddAdmin = false" class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">Add Admin to {{ $hospital->name }}</h3>
                <button @click="showAddAdmin = false" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('web.superadmin.hospitals.admin.add', $hospital->id) }}" class="p-6 space-y-4">
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
        </div>
    </div>
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
