@extends('layouts.app')
@section('title', 'Security')
@section('page-title', 'Security Center')

@section('content')
<x-dashboard-header :subtitle="$isSuper ? 'Platform-wide security monitoring (SIEM)' : 'Your hospital\'s security monitoring (SIEM)'" />

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

{{-- 14-day sign-in trend --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Sign-in Activity — 14 days</h3>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-green-500"></span>Success</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-red-400"></span>Failed</span>
        </div>
    </div>
    <div class="flex items-end justify-between gap-1" style="height:120px">
        @foreach($trend as $t)
            <div class="flex-1 flex flex-col items-center justify-end" title="{{ $t['label'] }}: {{ $t['logins'] }} sign-ins, {{ $t['failed'] }} failed">
                <div class="w-full flex items-end justify-center gap-0.5" style="height:104px">
                    <div class="bg-green-500 rounded-t" style="width:42%; height:{{ $t['logins'] ? max(3, round($t['logins']/$maxTrend*100)) : 0 }}%"></div>
                    <div class="bg-red-400 rounded-t" style="width:42%; height:{{ $t['failed'] ? max(3, round($t['failed']/$maxTrend*100)) : 0 }}%"></div>
                </div>
                <span class="text-slate-400 mt-1" style="font-size:9px">{{ substr($t['dow'], 0, 1) }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- Threat correlation + account alerts --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Threat Correlation</h3>
            @if(count($threats))<span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">{{ count($threats) }}</span>@endif
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($threats as $t)
                <div class="px-5 py-3 flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $t['level'] === 'high' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/></svg>
                    </span>
                    <div class="min-w-0"><p class="text-sm font-semibold text-slate-800">{{ $t['title'] }}</p><p class="text-xs text-slate-500">{{ $t['detail'] }}</p></div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">No correlated threats detected.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Account Alerts</h3>
            @if(count($flags))<span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold">{{ count($flags) }}</span>@endif
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($flags as $f)
                <div class="px-5 py-3 flex items-start gap-3">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 {{ $f['level'] === 'high' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-600' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </span>
                    <div class="min-w-0"><p class="text-sm font-semibold text-slate-800">{{ $f['title'] }}</p><p class="text-xs text-slate-500">{{ $f['detail'] }}</p></div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">No account alerts — all clear.</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Top source IPs --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-slate-200"><h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Top Source IPs</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">IP address</th><th class="table-header text-right">Events</th><th class="table-header text-right">Success</th><th class="table-header text-right">Failed</th><th class="table-header text-right">Accounts</th><th class="table-header">Last seen</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                @forelse($topIps as $r)
                    <tr class="{{ $r['failed'] >= 5 || $r['accounts'] >= 3 ? 'bg-red-50/40' : '' }}">
                        <td class="px-4 py-2 font-mono text-slate-700">{{ $r['ip'] }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">{{ $r['total'] }}</td>
                        <td class="px-4 py-2 text-right text-green-600">{{ $r['success'] }}</td>
                        <td class="px-4 py-2 text-right {{ $r['failed'] > 0 ? 'text-red-600 font-semibold' : 'text-slate-400' }}">{{ $r['failed'] }}</td>
                        <td class="px-4 py-2 text-right {{ $r['accounts'] >= 3 ? 'text-red-600 font-semibold' : 'text-slate-600' }}">{{ $r['accounts'] }}</td>
                        <td class="px-4 py-2 text-xs text-slate-400">{{ optional($r['last'])->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No IP activity recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
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

{{-- Event explorer --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-200">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">Event Explorer</h3>
            <a href="{{ route('web.admin.security.export') }}" class="text-xs px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50">Export JSON</a>
        </div>
        <form method="GET" action="{{ route('web.admin.security') }}" class="flex flex-wrap items-end gap-2">
            <div class="flex-1" style="min-width:220px">
                <label class="block text-slate-400 mb-1" style="font-size:10px">SEARCH</label>
                <input type="text" name="q" value="{{ $fSearch }}" placeholder="User, email, IP, or detail…" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 w-full">
            </div>
            @if($isSuper)
            <div>
                <label class="block text-slate-400 mb-1" style="font-size:10px">HOSPITAL</label>
                <select name="hospital" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                    <option value="">All hospitals</option>
                    @foreach($hospitals as $hidOpt => $hname)
                        <option value="{{ $hidOpt }}" {{ $fHospital === $hidOpt ? 'selected' : '' }}>{{ $hname }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-slate-400 mb-1" style="font-size:10px">ACTION</label>
                <select name="action" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                    <option value="">All actions</option>
                    <option value="login" {{ $fAction === 'login' ? 'selected' : '' }}>Sign-in</option>
                    <option value="failed_login" {{ $fAction === 'failed_login' ? 'selected' : '' }}>Failed sign-in</option>
                    <option value="logout" {{ $fAction === 'logout' ? 'selected' : '' }}>Sign-out</option>
                    <option value="update" {{ $fAction === 'update' ? 'selected' : '' }}>Admin action</option>
                </select>
            </div>
            <div>
                <label class="block text-slate-400 mb-1" style="font-size:10px">FROM</label>
                <input type="date" name="from" value="{{ $fFrom }}" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5">
            </div>
            <div>
                <label class="block text-slate-400 mb-1" style="font-size:10px">TO</label>
                <input type="date" name="to" value="{{ $fTo }}" class="text-xs border border-slate-200 rounded-lg px-2 py-1.5">
            </div>
            <button type="submit" class="text-xs px-3 py-1.5 rounded-lg bg-slate-800 text-white">Apply</button>
            <a href="{{ route('web.admin.security') }}" class="text-xs px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200">Reset</a>
        </form>
    </div>
    <div class="divide-y divide-slate-100">
        @forelse($events as $a)
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
            <div class="px-5 py-8 text-center text-sm text-slate-400">No matching events.</div>
        @endforelse
    </div>
    <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs text-slate-400">{{ $events->total() }} event{{ $events->total() === 1 ? '' : 's' }} match{{ $events->total() === 1 ? 'es' : '' }}.</span>
        <div>{{ $events->links() }}</div>
    </div>
</div>
@endsection
