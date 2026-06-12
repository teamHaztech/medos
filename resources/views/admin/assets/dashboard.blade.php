@extends('layouts.app')
@section('title', 'Asset Management')
@section('page-title', 'Asset Management')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.admin.assets.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Dashboard</a>
        <a href="{{ route('web.admin.assets.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Asset Register</a>
        <a href="{{ route('web.admin.vendors.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Vendors</a>
        <div class="ml-auto flex gap-2">
            <a href="{{ route('web.admin.assets.export') }}" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Export CSV</a>
            <a href="{{ route('web.admin.assets.report') }}" target="_blank" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Print Report</a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Assets</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['total_assets'] }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $stats['under_maintenance'] }} under maintenance</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">No Active Warranty</p>
            <p class="text-3xl font-bold {{ $stats['no_warranty'] ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $stats['no_warranty'] }}</p>
            <p class="text-xs text-slate-400 mt-1">assets uncovered</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Expiring ≤30 days</p>
            <p class="text-3xl font-bold text-amber-600 mt-1">{{ $expiring[30] }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $expiring[60] }} within 60 · {{ $expiring[90] }} within 90</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Vendors</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['vendors'] }}</p>
            <p class="text-xs text-slate-400 mt-1">active service providers</p>
        </div>
    </div>

    {{-- Contract overview --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        @foreach(['manufacturer' => 'Manufacturer', 'amc' => 'AMC', 'cmc' => 'CMC'] as $key => $label)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ $contractOverview[$key] ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Active {{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Expiring warranties --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Warranties Expiring (next 90 days)</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($expiringList as $w)
                    @php $days = $w->daysToExpiry(); @endphp
                    <a href="{{ route('web.admin.assets.show', $w->asset_id) }}" class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0 hover:bg-slate-50 rounded-lg px-2 -mx-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $w->asset?->asset_name ?? 'Asset' }}</p>
                            <p class="text-xs text-slate-500">{{ $w->typeLabel() }} · ends {{ optional($w->end_date)->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $days <= 30 ? 'bg-red-100 text-red-700' : ($days <= 60 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ $days }} days
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No warranties expiring soon 🎉</p>
                @endforelse
            </div>
        </div>

        {{-- Maintenance due --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Maintenance Due (next 30 days / overdue)</h3>
            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($maintenanceDue as $m)
                    @php $overdue = $m->next_due_date && $m->next_due_date->isPast(); @endphp
                    <a href="{{ route('web.admin.assets.show', $m->asset_id) }}" class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0 hover:bg-slate-50 rounded-lg px-2 -mx-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $m->asset?->asset_name ?? 'Asset' }}</p>
                            <p class="text-xs text-slate-500">{{ $m->typeLabel() }} · due {{ optional($m->next_due_date)->format('M d, Y') }}</p>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $overdue ? 'Overdue' : 'Due soon' }}
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">Nothing due in the next 30 days</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Assets without warranty --}}
    @if($withoutWarranty->count())
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Assets Without Active Warranty ({{ $withoutWarranty->count() }})</h3>
        <div class="flex flex-wrap gap-2">
            @foreach($withoutWarranty as $a)
                <a href="{{ route('web.admin.assets.show', $a->id) }}" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-red-50 border border-red-200 text-red-700 hover:bg-red-100">
                    {{ $a->asset_name }}<span class="text-red-400">{{ $a->department ? ' · ' . $a->department : '' }}</span>
                </a>
            @endforeach
        </div>
    </div>
    @endif
@endsection
