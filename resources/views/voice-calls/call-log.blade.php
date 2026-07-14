@extends('layouts.app')

@section('title', 'Call Log')
@section('page-title', 'Call Log')

@section('content')
<div>
    {{-- Filter row --}}
    <form method="GET" action="{{ route('web.voice-calls.calls') }}" class="flex flex-wrap items-end gap-3 mb-5">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="input-field">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="input-field">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select name="status" class="input-field">
                <option value="">All Statuses</option>
                @foreach(['ringing' => 'Ringing', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'missed' => 'Missed', 'failed' => 'Failed', 'transferred' => 'Transferred'] as $val => $label)
                    <option value="{{ $val }}" {{ request('status') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Direction</label>
            <select name="direction" class="input-field">
                <option value="">All</option>
                <option value="inbound" {{ request('direction') === 'inbound' ? 'selected' : '' }}>Inbound</option>
                <option value="outbound" {{ request('direction') === 'outbound' ? 'selected' : '' }}>Outbound</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Purpose</label>
            <select name="call_purpose" class="input-field">
                <option value="">All Purposes</option>
                @foreach(['appointment_booking' => 'Appointment Booking', 'schedule_check' => 'Schedule Check', 'lab_results' => 'Lab Results', 'queue_status' => 'Queue Status', 'general_inquiry' => 'General Inquiry', 'emergency' => 'Emergency', 'callback' => 'Callback'] as $val => $label)
                    <option value="{{ $val }}" {{ request('call_purpose') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Language</label>
            <select name="language_used" class="input-field">
                <option value="">All Languages</option>
                @foreach(['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi', 'kok' => 'Konkani', 'ar' => 'Arabic', 'ta' => 'Tamil', 'te' => 'Telugu', 'kn' => 'Kannada', 'bn' => 'Bengali'] as $val => $label)
                    <option value="{{ $val }}" {{ request('language_used') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="{{ route('web.voice-calls.calls') }}" class="text-sm text-slate-500 hover:text-slate-700">Reset</a>
        </div>
    </form>

    {{-- Summary bar --}}
    @if(!empty($stats))
    @php
        $avgMin = intdiv($stats['avg_duration'] ?? 0, 60);
        $avgSec = ($stats['avg_duration'] ?? 0) % 60;
    @endphp
    <div class="flex flex-wrap gap-3 mb-5">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-700 text-sm font-medium">
            Total: <strong>{{ $stats['total'] ?? 0 }}</strong>
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-green-100 text-green-700 text-sm font-medium">
            Answered: <strong>{{ $stats['answered'] ?? 0 }}</strong>
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-red-100 text-red-700 text-sm font-medium">
            Missed: <strong>{{ $stats['missed'] ?? 0 }}</strong>
        </span>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-blue-100 text-blue-700 text-sm font-medium">
            Avg: <strong>{{ $avgMin }}m {{ $avgSec }}s</strong>
        </span>
    </div>
    @endif

    {{-- Calls table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Date/Time</th>
                        <th class="table-header">Caller Number</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Direction</th>
                        <th class="table-header">Purpose</th>
                        <th class="table-header">Duration</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Sentiment</th>
                        <th class="table-header">Handler</th>
                        <th class="table-header"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($calls as $call)
                    @php
                        $dur = $call->duration_seconds ?? 0;
                        $durMin = intdiv($dur, 60);
                        $durSec = $dur % 60;

                        $statusColors = [
                            'ringing'      => 'bg-yellow-100 text-yellow-700',
                            'in_progress'  => 'bg-blue-100 text-blue-700',
                            'completed'    => 'bg-green-100 text-green-700',
                            'missed'       => 'bg-red-100 text-red-700',
                            'failed'       => 'bg-slate-100 text-slate-600',
                            'transferred'  => 'bg-purple-100 text-purple-700',
                        ];
                        $statusClass = $statusColors[$call->status] ?? 'bg-slate-100 text-slate-600';

                        $sentimentMap = [
                            'positive'  => '😊',
                            'neutral'   => '😐',
                            'negative'  => '😟',
                            'urgent'    => '🚨',
                            'frustrated'=> '😤',
                        ];
                        $sentimentEmoji = $sentimentMap[$call->sentiment] ?? '';

                        $purposeLabels = [
                            'appointment_booking' => 'Appointment',
                            'schedule_check'      => 'Schedule',
                            'lab_results'         => 'Lab Results',
                            'queue_status'        => 'Queue',
                            'general_inquiry'     => 'General',
                            'emergency'           => 'Emergency',
                            'callback'            => 'Callback',
                            'cancellation'        => 'Cancellation',
                            'transfer_request'    => 'Transfer',
                        ];
                        $purposeLabel = $purposeLabels[$call->call_purpose] ?? ($call->call_purpose ? ucfirst(str_replace('_', ' ', $call->call_purpose)) : '');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium whitespace-nowrap">{{ $call->created_at?->format('M j, g:i A') ?? '' }}</td>
                        <td class="table-cell whitespace-nowrap">{{ $call->caller_number ?: '' }}</td>
                        <td class="table-cell">{{ $call->patient?->name ?? 'Unknown' }}</td>
                        <td class="table-cell">
                            @if($call->direction === 'inbound')
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                                    In
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                    Out
                                </span>
                            @endif
                        </td>
                        <td class="table-cell">{{ $purposeLabel }}</td>
                        <td class="table-cell whitespace-nowrap">{{ $durMin }}m {{ $durSec }}s</td>
                        <td class="table-cell">
                            <span class="text-xs font-medium px-2 py-0.5 rounded-full {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $call->status ?? 'unknown')) }}</span>
                        </td>
                        <td class="table-cell text-center">{{ $sentimentEmoji }}</td>
                        <td class="table-cell">
                            @if($call->ai_handled)
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">AI</span>
                            @else
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Human</span>
                            @endif
                        </td>
                        <td class="table-cell">
                            <a href="{{ route('web.voice-calls.calls.show', $call->id) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <p class="text-sm">No calls found</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($calls instanceof \Illuminate\Pagination\LengthAwarePaginator && $calls->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $calls->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
