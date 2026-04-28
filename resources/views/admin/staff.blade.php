@extends('layouts.app')

@section('title', 'Staff Management')
@section('page-title', 'Staff')

@section('content')
<div x-data="{ showAddModal: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <p class="text-sm text-slate-500">Manage hospital staff members and their roles</p>
        <button @click="showAddModal = true" class="btn-primary">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Staff
        </button>
    </div>

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
                        <td class="table-cell">{{ $member->department ?? '-' }}</td>
                        <td class="table-cell text-slate-500">{{ $member->email ?? '-' }}</td>
                        <td class="table-cell text-slate-500">{{ $member->phone ?? '-' }}</td>
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
                        <td class="table-cell">
                            <button class="text-sm text-blue-600 hover:text-blue-800 font-medium">Edit</button>
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

    {{-- Add Staff Modal --}}
    <div x-show="showAddModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 bg-black/50" @click="showAddModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-slate-800">Add Staff Member</h3>
                <button @click="showAddModal = false" class="text-slate-400 hover:text-slate-600 text-xl">&times;</button>
            </div>
            <form method="POST" action="#" class="space-y-4">
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
                            <option value="hospital_admin">Hospital Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Department</label>
                        <input type="text" name="department" class="input-field" placeholder="e.g. Cardiology">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">License Number</label>
                    <input type="text" name="license_number" class="input-field">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showAddModal = false" class="btn-secondary">Cancel</button>
                    <button type="submit" class="btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
