@extends('layouts.app')
@section('title', 'My Appointments')
@section('page-title', 'My Appointments')

@section('content')
    <div class="flex items-center gap-3 mb-4">
        <form method="GET" action="{{ route('web.doctor.appointments') }}" class="flex items-center gap-2">
            <input type="date" name="date" value="{{ $date }}" class="input-field" onchange="this.form.submit()">
        </form>
        <p class="text-sm text-slate-500">{{ count($appointments) }} appointments</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Time</th>
                    <th class="table-header">Token</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Phone</th>
                    <th class="table-header">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($appointments as $apt)
                <tr class="hover:bg-slate-50">
                    <td class="table-cell font-medium">{{ $apt['time'] }}</td>
                    <td class="table-cell font-mono text-xs">{{ $apt['token'] }}</td>
                    <td class="table-cell font-medium">{{ $apt['patient'] }}</td>
                    <td class="table-cell text-slate-500">{{ $apt['phone'] }}</td>
                    <td class="table-cell"><x-status-badge :status="$apt['status']" type="appointment" /></td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-400">No appointments for this date</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
