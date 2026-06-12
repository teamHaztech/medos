@extends('layouts.app')
@section('title', 'Service Requests')
@section('page-title', 'Asset Management')

@section('content')
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.admin.assets.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Dashboard</a>
        <a href="{{ route('web.admin.assets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Asset Register</a>
        <a href="{{ route('web.admin.vendors.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Vendors</a>
        <a href="{{ route('web.admin.tickets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Service Requests</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4 flex flex-wrap gap-3">
        <select name="status" class="input-field w-44">
            <option value="">Any status</option>
            @foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
        </select>
        <select name="priority" class="input-field w-44">
            <option value="">Any priority</option>
            @foreach($priorities as $k => $l)<option value="{{ $k }}" @selected(($filters['priority'] ?? '') === $k)>{{ $l }}</option>@endforeach
        </select>
        <button type="submit" class="btn-primary px-5">Filter</button>
        <a href="{{ route('web.admin.tickets.index') }}" class="px-4 py-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
        <span class="ml-auto self-center text-sm text-slate-500">{{ $tickets->count() }} requests</span>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Asset</th>
                    <th class="table-header">Issue</th>
                    <th class="table-header">Priority</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Reported</th>
                    <th class="table-header w-20"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($tickets as $t)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $t->asset?->asset_name ?? '—' }}</td>
                        <td class="table-cell text-slate-600">{{ \Illuminate\Support\Str::limit($t->issue, 60) }}</td>
                        <td class="table-cell">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ in_array($t->priority, ['critical','high']) ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' }}">{{ $t->priorityLabel() }}</span>
                        </td>
                        <td class="table-cell">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $t->status === 'open' ? 'bg-amber-100 text-amber-700' : ($t->status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') }}">{{ $t->statusLabel() }}</span>
                        </td>
                        <td class="table-cell text-slate-500 text-xs">{{ optional($t->reported_at)->format('M d, Y') }}{{ $t->reported_by ? ' · ' . $t->reported_by : '' }}</td>
                        <td class="table-cell">
                            <a href="{{ route('web.admin.assets.show', $t->asset_id) }}" class="text-blue-500 hover:text-blue-700 text-sm font-medium">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-10">No service requests found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
