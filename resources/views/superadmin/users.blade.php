@extends('layouts.app')
@section('title', 'User Accounts (IAM)')
@section('page-title', 'User Accounts — IAM')

@php
    $roleColors = [
        'super_admin'    => 'bg-slate-800 text-white',
        'hospital_admin' => 'bg-indigo-100 text-indigo-700',
        'doctor'         => 'bg-blue-100 text-blue-700',
        'nurse'          => 'bg-pink-100 text-pink-700',
        'receptionist'   => 'bg-cyan-100 text-cyan-700',
        'billing_staff'  => 'bg-green-100 text-green-700',
        'lab_tech'       => 'bg-amber-100 text-amber-700',
        'pharmacist'     => 'bg-purple-100 text-purple-700',
    ];
@endphp

@php
    // Reusable row renderer
    if (! function_exists('sa_role_label')) {
        function sa_role_label($r) { return ucwords(str_replace('_', ' ', $r)); }
    }
@endphp

@section('content')
@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

<div class="flex items-center justify-between mb-4">
    <a href="{{ route('web.superadmin.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Super Admin</a>
</div>

{{-- Summary --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Total accounts</p><p class="text-2xl font-bold text-slate-800">{{ $counts['total'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Active</p><p class="text-2xl font-bold text-green-600">{{ $counts['active'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Inactive</p><p class="text-2xl font-bold text-slate-500">{{ $counts['inactive'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Hospitals</p><p class="text-2xl font-bold text-slate-800">{{ $counts['hospitals'] }}</p></div>
    <div class="bg-white rounded-xl border {{ $counts['unknown'] ? 'border-red-300 bg-red-50' : 'border-slate-200' }} p-4"><p class="text-xs text-slate-500">Unknown accounts</p><p class="text-2xl font-bold {{ $counts['unknown'] ? 'text-red-600' : 'text-slate-800' }}">{{ $counts['unknown'] }}</p></div>
</div>

{{-- UNKNOWN / UNASSIGNED ACCOUNTS --}}
@if($unknown->isNotEmpty())
<div class="bg-white rounded-xl border-2 border-red-300 overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-red-200 bg-red-50">
        <h3 class="text-sm font-bold text-red-700 uppercase tracking-wider">⚠ Unknown / unassigned accounts ({{ $unknown->count() }})</h3>
        <p class="text-xs text-red-500 mt-0.5">Not linked to any hospital, or using an unrecognised role. Review and remove if not legitimate.</p>
    </div>
    <table class="w-full">
        <tbody class="divide-y divide-slate-100">
            @foreach($unknown as $u)
            <tr class="{{ $u->is_active ? '' : 'opacity-60' }}">
                <td class="px-4 py-2.5">
                    <a href="{{ route('web.superadmin.users.show', $u->id) }}" class="text-sm font-medium text-slate-800 hover:text-blue-600">{{ $u->name }}</a>
                    <p class="text-xs text-slate-400">{{ $u->email }}@if($u->last_login_at) · last seen {{ $u->last_login_at->diffForHumans() }}@endif</p>
                </td>
                <td class="px-4 py-2.5">
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $u->role_known ? ($roleColors[$u->role_str] ?? 'bg-slate-100 text-slate-600') : 'bg-red-100 text-red-700' }}">{{ sa_role_label($u->role_str) }}</span>
                    @unless($u->role_known)<span class="text-xs text-red-600 ml-1">unknown role</span>@endunless
                </td>
                <td class="px-4 py-2.5 text-xs text-slate-500">
                    {{ $u->hospital_name ?? ($u->hospital_id ? 'Missing hospital ('.\Illuminate\Support\Str::limit($u->hospital_id,8,'').')' : 'No hospital') }}
                </td>
                <td class="px-4 py-2.5 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <form method="POST" action="{{ route('web.superadmin.users.toggle', $u->id) }}">@csrf<button type="submit" class="text-xs font-medium {{ $u->is_active ? 'text-slate-500 hover:text-slate-700' : 'text-green-600 hover:text-green-800' }}">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                        <form method="POST" action="{{ route('web.superadmin.users.delete', $u->id) }}" onsubmit="return confirm('Permanently delete {{ $u->email }}? This cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Delete</button></form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- PER-HOSPITAL --}}
@foreach($hospitals as $hid => $hospital)
    @php $hUsers = $grouped[$hid] ?? []; @endphp
    @if(count($hUsers))
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">{{ $hospital->name }}</h3>
                <p class="text-xs text-slate-400">{{ count($hUsers) }} account{{ count($hUsers) === 1 ? '' : 's' }} @if(! $hospital->is_active)· <span class="text-red-500">hospital inactive</span>@endif</p>
            </div>
            <a href="{{ route('web.superadmin.hospitals.show', $hid) }}" class="text-xs text-blue-600 hover:text-blue-800">Manage hospital →</a>
        </div>
        <table class="w-full">
            <tbody class="divide-y divide-slate-100">
                @foreach($hUsers as $u)
                <tr class="{{ $u->is_active ? '' : 'opacity-60' }}">
                    <td class="px-4 py-2.5">
                        <p class="text-sm font-medium text-slate-800">{{ $u->name }}</p>
                        <p class="text-xs text-slate-400">{{ $u->email }}</p>
                    </td>
                    <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $roleColors[$u->role_str] ?? 'bg-slate-100 text-slate-600' }}">{{ sa_role_label($u->role_str) }}</span></td>
                    <td class="px-4 py-2.5">@if($u->is_active)<span class="text-xs text-green-600">● Active</span>@else<span class="text-xs text-slate-400">○ Inactive</span>@endif</td>
                    <td class="px-4 py-2.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <form method="POST" action="{{ route('web.superadmin.users.toggle', $u->id) }}">@csrf<button type="submit" class="text-xs font-medium {{ $u->is_active ? 'text-slate-500 hover:text-slate-700' : 'text-green-600 hover:text-green-800' }}">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                            <form method="POST" action="{{ route('web.superadmin.users.delete', $u->id) }}" onsubmit="return confirm('Permanently delete {{ $u->email }}?')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Delete</button></form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
@endforeach

{{-- SYSTEM (super admins) --}}
@if($system->isNotEmpty())
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-4">
    <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">System — Super Admins</h3></div>
    <table class="w-full">
        <tbody class="divide-y divide-slate-100">
            @foreach($system as $u)
            <tr class="{{ $u->is_active ? '' : 'opacity-60' }}">
                <td class="px-4 py-2.5"><p class="text-sm font-medium text-slate-800">{{ $u->name }}</p><p class="text-xs text-slate-400">{{ $u->email }}</p></td>
                <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-white">Super Admin</span></td>
                <td class="px-4 py-2.5">@if($u->is_active)<span class="text-xs text-green-600">● Active</span>@else<span class="text-xs text-slate-400">○ Inactive</span>@endif</td>
                <td class="px-4 py-2.5 text-right">
                    @if($u->id === auth()->id())<span class="text-xs text-slate-400">you</span>
                    @else<form method="POST" action="{{ route('web.superadmin.users.toggle', $u->id) }}">@csrf<button type="submit" class="text-xs font-medium text-slate-500 hover:text-slate-700">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button></form>@endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
