@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-stat-card title="Patients Today" :value="$patientsToday" icon="users" color="blue" />
        <x-stat-card title="Avg Wait Time" :value="$avgWaitTime . ' min'" icon="clock" color="yellow" />
        <x-stat-card title="AI Automation" :value="$aiRate . '%'" icon="cpu" color="purple" />
        <x-stat-card title="Revenue Today" :value="\App\Modules\Core\Services\RegionService::currency() . number_format($revenueToday)" icon="currency" color="green" />
    </div>

    {{-- Main grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Recent Activity Feed --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Recent Activity</h3>
            <div class="space-y-1 max-h-80 overflow-y-auto">
                @forelse($recentActivity as $event)
                    <div class="flex items-start gap-3 py-2.5 border-b border-slate-50 last:border-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                            @if($event['type'] === 'check_in') bg-green-100 text-green-600
                            @elseif($event['type'] === 'completed') bg-purple-100 text-purple-600
                            @elseif($event['type'] === 'cancelled') bg-red-100 text-red-600
                            @else bg-blue-100 text-blue-600
                            @endif
                        ">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700">{{ $event['message'] }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $event['time'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-8">No activity today yet</p>
                @endforelse
            </div>
        </div>

        {{-- Active Queues --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Active Queues</h3>
            <div class="space-y-3">
                @forelse($queues as $queue)
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $queue['doctor'] }}</p>
                            <p class="text-xs text-slate-500">{{ $queue['department'] }}</p>
                        </div>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold
                            @if($queue['depth'] > 5) bg-red-100 text-red-700
                            @elseif($queue['depth'] > 2) bg-yellow-100 text-yellow-700
                            @else bg-green-100 text-green-700
                            @endif
                        ">{{ $queue['depth'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-4">No active queues</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mt-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $quickStats['pendingAppointments'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Pending Appointments</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-yellow-600">{{ $quickStats['insurancePending'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Insurance Pending</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-red-600">{{ $quickStats['billsUnpaid'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Bills Unpaid</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $quickStats['consultationsDone'] }}</p>
            <p class="text-xs text-slate-500 mt-1">Consultations Done</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-slate-500">{{ $quickStats['noShows'] }}</p>
            <p class="text-xs text-slate-500 mt-1">No-shows</p>
        </div>
    </div>
@endsection
