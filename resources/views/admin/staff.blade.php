@extends('layouts.app')

@section('title', 'Staff Management')
@section('page-title', 'Staff')

@section('content')
<div x-data="staffPage()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-slate-500">Manage hospital staff members and their roles</p>
        <div class="flex items-center gap-2">
            <button @click="showImportModal = true" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-6l-4-4m0 0L8 6m4-4v12"/></svg>
                Import
            </button>
            <button @click="showAddModal = true" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Staff
            </button>
        </div>
    </div>

    {{-- Bulk-import result (created logins shown once — share securely) --}}
    @if(session('import_result'))
        @php $ir = session('import_result'); @endphp
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-slate-700">Import result</h3>
                <span class="text-xs text-slate-400">{{ count($ir['created']) }} created · {{ count($ir['skipped']) }} skipped · {{ count($ir['errors']) }} errors</span>
            </div>
            @if(count($ir['created']))
                <p class="text-xs text-slate-500 mb-2">Share these logins securely — passwords are shown only once. Ask staff to change them after first login.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200">
                                <th class="text-left text-xs font-medium text-slate-500 pb-2">Name</th>
                                <th class="text-left text-xs font-medium text-slate-500 pb-2">Email</th>
                                <th class="text-left text-xs font-medium text-slate-500 pb-2">Password</th>
                                <th class="text-left text-xs font-medium text-slate-500 pb-2">Role</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($ir['created'] as $c)
                                <tr>
                                    <td class="py-2 font-medium text-slate-700">{{ $c['name'] }}</td>
                                    <td class="py-2 font-mono text-xs text-slate-600">{{ $c['email'] }}</td>
                                    <td class="py-2 font-mono text-xs text-slate-600">{{ $c['password'] }}</td>
                                    <td class="py-2 capitalize text-slate-600">{{ str_replace('_', ' ', $c['role']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if(count($ir['skipped']))
                <p class="text-xs text-amber-600 mt-3"><b>Skipped</b> (already exist): {{ collect($ir['skipped'])->pluck('email')->implode(', ') }}</p>
            @endif
            @if(count($ir['errors']))
                <div class="text-xs text-red-600 mt-2">
                    <b>Errors:</b>
                    <ul class="list-disc list-inside mt-1">
                        @foreach($ir['errors'] as $e)
                            <li>Row {{ $e['row'] ?? '?' }}{{ !empty($e['email']) ? ' ('.$e['email'].')' : '' }} — {{ $e['reason'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    {{-- Staff Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Name</th>
                        <th class="table-header">Role</th>
                        <th class="table-header">Department</th>
                        <th class="table-header">Email</th>
                        <th class="table-header">Phone</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($staff ?? [] as $member)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-slate-200 text-slate-600 rounded-full flex items-center justify-center text-sm font-medium">
                                    {{ strtoupper(substr($member->name ?? 'U', 0, 1)) }}
                                </div>
                                <span class="font-medium text-slate-900">{{ $member->name ?? 'Unknown' }}</span>
                            </div>
                        </td>
                        <td class="table-cell">
                            @php
                                $roleColors = [
                                    'doctor' => 'bg-blue-100 text-blue-800',
                                    'nurse' => 'bg-pink-100 text-pink-800',
                                    'receptionist' => 'bg-green-100 text-green-800',
                                    'hospital_admin' => 'bg-purple-100 text-purple-800',
                                    'pharmacist' => 'bg-orange-100 text-orange-800',
                                    'lab_tech' => 'bg-cyan-100 text-cyan-800',
                                    'billing_staff' => 'bg-yellow-100 text-yellow-800',
                                    'super_admin' => 'bg-red-100 text-red-800',
                                ];
                                $roleColor = $roleColors[$member->role?->value ?? ''] ?? 'bg-slate-100 text-slate-600';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleColor }}">
                                {{ $member->role?->label() ?? 'Staff' }}
                            </span>
                        </td>
                        <td class="table-cell">{{ $member->department ?? '' }}</td>
                        <td class="table-cell text-slate-500">{{ $member->email ?? '' }}</td>
                        <td class="table-cell text-slate-500">{{ $member->phone ?? '' }}</td>
                        <td class="table-cell">
                            @if($member->is_active)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="table-cell whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <button @click="editStaff({
                                    id: '{{ $member->id }}',
                                    name: @js($member->name ?? ''),
                                    email: @js($member->email ?? ''),
                                    phone: @js($member->phone ?? ''),
                                    role: @js(is_object($member->role) ? $member->role->value : ($member->role ?? 'doctor')),
                                    department: @js($member->department ?? ''),
                                    specialization: @js($member->specialization ?? ''),
                                    qualification: @js($member->qualification ?? ''),
                                    consultation_duration_default: {{ $member->consultation_duration_default ?? 15 }}
                                })" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100">Edit</button>
                                <form method="POST" action="{{ route('web.admin.staff.reset-password', $member->id) }}" onsubmit="return confirm('Generate a new password for {{ $member->name }}? You will see the new password to share.')">
                                    @csrf
                                    <button type="submit" class="text-sm text-amber-600 hover:text-amber-800 font-medium">Reset</button>
                                </form>
                                @if($member->is_active)
                                <form method="POST" action="{{ route('web.admin.staff.delete', $member->id) }}" onsubmit="return confirm('Deactivate this staff member?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium">Deactivate</button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('web.admin.staff.activate', $member->id) }}" onsubmit="return confirm('Activate this staff member?')">
                                    @csrf
                                    <button type="submit" class="text-sm text-green-600 hover:text-green-800 font-medium">Activate</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197"/></svg>
                            <p class="text-sm">No staff members found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Edit Staff Modal --}}
    <x-modal show="showEditModal" title="Edit Staff Member" max="lg">
            <form :action="'/admin/staff/' + editingStaff.id" method="POST" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                    <input type="text" name="name" x-model="editingStaff.name" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" x-model="editingStaff.email" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="tel" name="phone" x-model="editingStaff.phone" class="input-field">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select name="role" x-model="editingStaff.role" required class="input-field">
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="lab_tech">Lab Technician</option>
                            <option value="billing_staff">Billing Staff</option>
                            <option value="dentist">Dentist</option>
                            <option value="dietitian">Dietitian</option>
                            <option value="hospital_admin">Hospital Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                        <input type="text" name="department" x-model="editingStaff.department" class="input-field">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Specialization</label>
                        <input type="text" name="specialization" x-model="editingStaff.specialization" class="input-field">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Qualification</label>
                        <input type="text" name="qualification" x-model="editingStaff.qualification" class="input-field">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Consultation Duration (minutes)</label>
                    <input type="number" name="consultation_duration_default" x-model="editingStaff.consultation_duration_default" class="input-field" min="5" max="120">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showEditModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Save Changes</button>
                </div>
            </form>
    </x-modal>

    {{-- Add Staff Modal --}}
    <x-modal show="showAddModal" title="Add Staff Member" max="lg">
            <form method="POST" action="{{ route('web.admin.staff.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                    <input type="text" name="name" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input type="email" name="email" required class="input-field">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="tel" name="phone" class="input-field">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select name="role" required class="input-field">
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="receptionist">Receptionist</option>
                            <option value="pharmacist">Pharmacist</option>
                            <option value="lab_tech">Lab Technician</option>
                            <option value="billing_staff">Billing Staff</option>
                            <option value="dentist">Dentist</option>
                            <option value="dietitian">Dietitian</option>
                            <option value="hospital_admin">Hospital Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                        <input type="text" name="department" class="input-field" placeholder="e.g. Cardiology">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Specialization</label>
                        <input type="text" name="specialization" class="input-field" placeholder="e.g. Cardiology">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Qualification</label>
                        <input type="text" name="qualification" class="input-field" placeholder="e.g. MBBS, MD">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Consultation Duration (minutes)</label>
                        <input type="number" name="consultation_duration_default" class="input-field" value="15" min="5" max="120">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Temporary password</label>
                        <input type="text" name="password" class="input-field" placeholder="Blank = auto-generate">
                        <p class="text-xs text-slate-400 mt-1">You'll see the login + password to share after saving.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showAddModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Add Staff</button>
                </div>
            </form>
    </x-modal>

    {{-- Import Staff Modal --}}
    <x-modal show="showImportModal" title="Import Staff from CSV" max="2xl">
            <form method="POST" action="{{ route('web.admin.staff.import') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                    <p class="text-sm font-medium text-slate-700 mb-1">Columns: <span class="font-mono text-xs">name, email, password, role, department, phone</span></p>
                    <ul class="text-xs text-slate-500 space-y-0.5 list-disc list-inside">
                        <li><b>name</b> and <b>email</b> are required. Blank <b>password</b> → auto-generated (shown after import).</li>
                        <li><b>role</b>: doctor, nurse, receptionist, pharmacist, lab_tech, billing_staff, dentist, dietitian, hospital_admin (blank → receptionist).</li>
                        <li>Duplicate emails are skipped — safe to re-run the same file when setting up a new hospital.</li>
                    </ul>
                    <a href="{{ route('web.admin.staff.import.template') }}" class="btn-primary mt-2 text-sm">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download CSV template
                    </a>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Upload CSV file</label>
                    <input type="file" name="file" accept=".csv,.txt" class="block w-full text-sm text-slate-600 border border-slate-300 rounded-lg p-2">
                </div>

                <div class="text-center text-xs text-slate-400">— or paste rows below —</div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Paste CSV rows</label>
                    <textarea name="rows" rows="5" class="input-field font-mono text-xs" placeholder="name,email,password,role,department,phone&#10;Dr. Asha Rao,asha@example.com,,doctor,Cardiology,9876500001&#10;Nurse John,john@example.com,,nurse,,9876500002"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showImportModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Import Staff</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection

@push('scripts')
<script>
function staffPage() {
    return {
        showAddModal: false,
        showImportModal: false,
        showEditModal: false,
        editingStaff: {},

        init() {},

        editStaff(member) {
            this.editingStaff = {
                id: member.id,
                name: member.name || '',
                email: member.email || '',
                phone: member.phone || '',
                role: (typeof member.role === 'object' ? member.role.value : member.role) || 'doctor',
                department: member.department || '',
                specialization: member.specialization || '',
                qualification: member.qualification || '',
                consultation_duration_default: member.consultation_duration_default || 15,
            };
            this.showEditModal = true;
        },
    };
}
</script>
@endpush
