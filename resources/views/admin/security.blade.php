@extends('layouts.app')
@section('title', 'Security')
@section('page-title', 'Security Center')

@section('content')
<x-dashboard-header :subtitle="$isSuper ? 'Platform-wide account security & activity' : 'Your hospital\'s account security & activity'" />

@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

{{-- KPIs --}}
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
    <x-stat-card label="Accounts" :value="$kpis['accounts']" accent="blue" icon="users" />
    <x-stat-card label="Active" :value="$kpis['active']" accent="green" icon="check" />
    <x-stat-card label="Disabled" :value="$kpis['disabled']" accent="slate" icon="clock" />
    <x-stat-card label="Sign-ins (24h)" :value="$kpis['logins_24h']" accent="purple" icon="check" />
    <x-stat-card label="Failed (24h)" :value="$kpis['failed_24h']" :accent="$kpis['failed_24h'] > 0 ? 'red' : 'slate'" icon="alert" />
    <x-stat-card label="Never signed in" :value="$kpis['never']" accent="amber" icon="clock" />
</div>

{{-- Security alerts --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Security Alerts</h3>
        @if(count($flags))<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">{{ count($flags) }} to review</span>@endif
    </div>
    <div class="divide-y divide-slate-100">
        @forelse($flags as $f)
            <div class="px-5 py-3 flex items-start gap-3">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $f['level'] === 'high' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">{{ $f['title'] }}</p>
                    <p class="text-xs text-slate-500">{{ $f['detail'] }}</p>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-slate-400">No security alerts — all clear.</div>
        @endforelse
    </div>
</div>

{{-- Accounts --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Accounts ({{ $users->count() }})</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">Account</th>
                <th class="table-header">Role</th>
                @if($isSuper)<th class="table-header">Hospital</th>@endif
                <th class="table-header">Last sign-in</th>
                <th class="table-header">Last IP</th>
                <th class="table-header">Status</th>
                <th class="table-header text-right">Actions</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($users as $u)
                    @php
                        $rv = is_object($u->role) ? $u->role->value : $u->role;
                        $isSelf = $u->id === $actor->id;
                        $canManage = ! $isSelf && ($isSuper || $rv !== 'super_admin');
                    @endphp
                    <tr class="{{ $u->is_active ? '' : 'opacity-60' }}">
                        <td class="px-4 py-2.5 text-sm">
                            <span class="font-medium text-slate-800">{{ $u->name }}</span>@if($isSelf)<span class="text-xs text-blue-600"> · you</span>@endif
                            <span class="block text-xs text-slate-400">{{ $u->email }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ ucwords(str_replace('_', ' ', $rv)) }}</td>
                        @if($isSuper)<td class="px-4 py-2.5 text-xs text-slate-500">{{ $u->hospital_id ? ($hospitals[$u->hospital_id] ?? 'Unknown') : 'Platform' }}</td>@endif
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'Never' }}</td>
                        <td class="px-4 py-2.5 text-xs font-mono text-slate-500">{{ $u->last_login_ip ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if($u->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Active</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-medium">Disabled</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            @if($canManage)
                                <form method="POST" action="{{ route('web.admin.security.reset', $u->id) }}" class="inline" onsubmit="return confirm('Reset password for {{ $u->email }}? A new password will be shown.')">
                                    @csrf<button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800">Reset password</button>
                                </form>
                                <span class="text-slate-300 mx-1">·</span>
                                <form method="POST" action="{{ route('web.admin.security.toggle', $u->id) }}" class="inline" onsubmit="return confirm('{{ $u->is_active ? 'Disable' : 'Enable' }} {{ $u->email }}?')">
                                    @csrf<button type="submit" class="text-xs font-medium {{ $u->is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' }}">{{ $u->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                            @else
                                <span class="text-xs text-slate-300">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Recent activity --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Recent Activity</h3></div>
    <div class="divide-y divide-slate-100">
        @forelse($recent as $a)
            @php
                $badge = match($a->action) {
                    'login' => 'bg-green-100 text-green-700',
                    'logout' => 'bg-slate-100 text-slate-600',
                    'failed_login' => 'bg-red-100 text-red-700',
                    default => 'bg-blue-100 text-blue-700',
                };
            @endphp
            <div class="px-5 py-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badge }} whitespace-nowrap">{{ $a->actionLabel() }}</span>
                <span class="text-slate-700 font-medium">{{ $a->user_name ?? $a->user_email ?? 'Unknown' }}</span>
                @if($a->description)<span class="text-slate-500 text-xs">{{ $a->description }}</span>@endif
                @if($a->ip_address)<span class="text-xs font-mono text-slate-400">{{ $a->ip_address }}</span>@endif
                @if($isSuper && $a->hospital_name)<span class="text-xs text-slate-400">· {{ $a->hospital_name }}</span>@endif
                <span class="ml-auto text-xs text-slate-400 whitespace-nowrap">{{ optional($a->created_at)->diffForHumans() }}</span>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-slate-400">No activity recorded yet.</div>
        @endforelse
    </div>
</div>
@endsection
