@extends('layouts.app')
@section('title', 'Activity Log')
@section('page-title', 'Activity Log')

@section('content')
@php
    $badge = fn ($a) => [
        'login' => 'bg-green-100 text-green-700',
        'logout' => 'bg-slate-100 text-slate-600',
        'failed_login' => 'bg-red-100 text-red-700',
        'create' => 'bg-blue-100 text-blue-700',
        'update' => 'bg-amber-100 text-amber-700',
        'delete' => 'bg-red-100 text-red-700',
    ][$a] ?? 'bg-slate-100 text-slate-600';
@endphp

<div>
    <p class="text-sm text-slate-500 mb-4">Who signed in and what they changed — an append-only trail for audit &amp; incident response. Showing <strong>{{ $scopeLabel }}</strong>.</p>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="input-field text-sm" placeholder="User, email, IP, or detail…">
            </div>
            @if($isSuperAdmin)
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Hospital</label>
                <select name="hospital" class="input-field text-sm">
                    <option value="">All hospitals</option>
                    @foreach($hospitals as $h)
                        <option value="{{ $h->id }}" {{ request('hospital') === $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Action</label>
                <select name="action" class="input-field text-sm">
                    <option value="">All actions</option>
                    @foreach($actionTypes as $k => $label)
                        <option value="{{ $k }}" {{ request('action') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="input-field text-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="input-field text-sm">
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button class="btn-primary text-sm px-4">Apply</button>
            <a href="{{ url()->current() }}" class="btn-secondary text-sm">Reset</a>
        </div>
    </form>

    {{-- Log table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header whitespace-nowrap">When</th>
                        <th class="table-header">User</th>
                        @if($isSuperAdmin)<th class="table-header">Hospital</th>@endif
                        <th class="table-header">Action</th>
                        <th class="table-header">Details</th>
                        <th class="table-header whitespace-nowrap">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($activities as $a)
                        <tr class="hover:bg-slate-50 align-top">
                            <td class="table-cell whitespace-nowrap text-xs text-slate-500">
                                {{ optional($a->created_at)->format('d M Y') }}<br>
                                <span class="text-slate-400">{{ optional($a->created_at)->format('h:i:s A') }}</span>
                            </td>
                            <td class="table-cell">
                                <p class="font-medium text-slate-800">{{ $a->user_name ?? '—' }}</p>
                                <p class="text-xs text-slate-400">{{ $a->user_email }}{{ $a->role ? ' · '.ucwords(str_replace('_',' ', $a->role)) : '' }}</p>
                            </td>
                            @if($isSuperAdmin)<td class="table-cell text-sm text-slate-600">{{ $a->hospital_name ?? '—' }}</td>@endif
                            <td class="table-cell">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badge($a->action) }}">{{ $a->actionLabel() }}</span>
                            </td>
                            <td class="table-cell text-xs text-slate-500 font-mono break-words" style="max-width:22rem">{{ $a->description ?? '—' }}</td>
                            <td class="table-cell whitespace-nowrap text-xs text-slate-400 font-mono">{{ $a->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isSuperAdmin ? 6 : 5 }}" class="p-10 text-center text-slate-400 text-sm">No activity recorded for these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $activities->links() }}</div>
</div>
@endsection
