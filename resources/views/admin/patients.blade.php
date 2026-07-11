@extends('layouts.app')

@section('title', 'Patients')
@section('page-title', 'Patients')

@section('content')
<div x-data="{ showAddModal: false }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <form method="GET" action="{{ route('web.admin.patients') }}" class="flex-1 max-w-md">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search by name or phone..."
                    class="input-field pl-10"
                >
            </div>
        </form>
        <div class="flex items-center gap-2">
            <button @click="showAddModal = true" class="btn-secondary">
                <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Quick Add
            </button>
            <a href="{{ route('web.admin.patients.register') }}" class="btn-primary">
                <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Full Registration
            </a>
        </div>
    </div>
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Name</th>
                        <th class="table-header">Phone</th>
                        <th class="table-header">Age</th>
                        <th class="table-header">Gender</th>
                        <th class="table-header">Language</th>
                        <th class="table-header">Insurance</th>
                        <th class="table-header">Last Visit</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($patients ?? [] as $patient)
                        <x-patient-row :patient="$patient" />
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <p class="text-sm">No patients found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($patients) && $patients->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $patients->withQueryString()->links() }}
        </div>
        @endif
    </div>

    {{-- Add Patient Modal --}}
    <x-modal show="showAddModal" title="Register New Patient" max="2xl">
        <form method="POST" action="{{ route('web.admin.patients.store') }}">
            @csrf
            @include('admin._patient_fields')
            <div class="flex justify-end gap-3 pt-6 mt-6 border-t border-slate-100">
                <button type="button" @click="showAddModal = false" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Register Patient</button>
            </div>
        </form>
    </x-modal>
</div>
@endsection
