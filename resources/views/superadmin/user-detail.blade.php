@extends('layouts.app')
@section('title', 'Account — ' . $user->name)
@section('page-title', 'Account Detail')

@php
    $roleStr = $user->role instanceof \BackedEnum ? $user->role->value : (string) $user->role;
    $roleColors = [
        'super_admin'=>'bg-slate-800 text-white','hospital_admin'=>'bg-indigo-100 text-indigo-700',
        'doctor'=>'bg-blue-100 text-blue-700','nurse'=>'bg-pink-100 text-pink-700',
        'receptionist'=>'bg-cyan-100 text-cyan-700','billing_staff'=>'bg-green-100 text-green-700',
        'lab_tech'=>'bg-amber-100 text-amber-700','pharmacist'=>'bg-purple-100 text-purple-700',
    ];
    $actIcon = ['login'=>'text-green-600','logout'=>'text-slate-400','failed_login'=>'text-red-600'];
@endphp

@section('content')
@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

<a href="{{ route('web.superadmin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; All accounts</a>

{{-- Profile header --}}
<div class="bg-white rounded-xl border border-slate-200 p-6 mt-3 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">{{ $user->name }}</h2>
            <p class="text-sm text-slate-500">{{ $user->email }}{{ $user->phone ? ' · '.$user->phone : '' }}</p>
            <div class="flex items-center gap-2 mt-3">
                <span class="text-xs px-2 py-0.5 rounded-full {{ $roleColors[$roleStr] ?? 'bg-slate-100 text-slate-600' }}">{{ ucwords(str_replace('_',' ',$roleStr)) }}</span>
                @if($user->is_active)<span class="text-xs text-green-600">● Active</span>@else<span class="text-xs text-slate-400">○ Inactive</span>@endif
                <span class="text-xs text-slate-400">· {{ $hospitalName ?? 'No hospital' }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('web.superadmin.users.toggle', $user->id) }}">@csrf<button type="submit" class="btn-secondary text-sm">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button></form>
            <form method="POST" action="{{ route('web.superadmin.users.delete', $user->id) }}" onsubmit="return confirm('Permanently delete {{ $user->email }}?')">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100">Delete</button></form>
        </div>
    </div>
</div>

{{-- Security stats --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Last login</p><p class="text-sm font-bold text-slate-800">{{ $stats['last_login'] ? $stats['last_login']->format('d M Y, g:i A') : 'Never' }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Last IP</p><p class="text-sm font-bold text-slate-800">{{ $stats['last_ip'] ?? '' }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Total logins</p><p class="text-2xl font-bold text-slate-800">{{ $stats['logins'] }}</p></div>
    <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Audited actions</p><p class="text-2xl font-bold text-slate-800">{{ $stats['actions'] }}</p></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Login / security history --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Sign-in History</h3></div>
        <div class="overflow-x-auto overflow-y-auto" style="max-height:520px">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0"><tr>
                    <th class="table-header">Event</th><th class="table-header">When</th><th class="table-header">IP / Device</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($activity as $a)
                    <tr>
                        <td class="px-4 py-2.5"><span class="text-sm font-medium {{ $actIcon[$a->action] ?? 'text-slate-600' }}">{{ $a->actionLabel() }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($a->created_at)->format('d M Y, g:i:s A') }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $a->ip_address ?? '' }}<span class="block text-slate-400 truncate" style="max-width:220px;">{{ \Illuminate\Support\Str::limit($a->user_agent, 60) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No sign-in activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Audited actions --}}
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Recent Actions</h3></div>
        <div class="overflow-x-auto overflow-y-auto" style="max-height:520px">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0"><tr>
                    <th class="table-header">Action</th><th class="table-header">On</th><th class="table-header">When</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($actions as $log)
                    <tr>
                        <td class="px-4 py-2.5 text-sm text-slate-700">{{ ucwords(str_replace('_',' ', $log->action ?? '')) }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ class_basename($log->entity_type ?? '') ?: '' }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($log->created_at)->format('d M Y, g:i A') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-400">No audited actions for this account.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
