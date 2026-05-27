@extends('layouts.app')

@section('title', 'Appointments')
@section('page-title', 'Appointments')

@section('content')
<div x-data="appointmentsPage()">

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date</label>
            <input type="date" x-model="filterDate" @change="applyFilters()" class="input-field" value="{{ request('date', now()->toDateString()) }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Doctor</label>
            <select x-model="filterDoctor" @change="applyFilters()" class="input-field">
                <option value="">All Doctors</option>
                @foreach($doctors ?? [] as $doctor)
                    <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
            <select x-model="filterStatus" @change="applyFilters()" class="input-field">
                <option value="">All Statuses</option>
                <option value="scheduled">Scheduled</option>
                <option value="confirmed">Confirmed</option>
                <option value="checked_in">Checked In</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="no_show">No Show</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>

    {{-- Appointments Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Time</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Doctor</th>
                        <th class="table-header">Department</th>
                        <th class="table-header">Reason</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($appointments ?? [] as $apt)
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $apt->slot_start?->format('h:i A') ?? '-' }}</td>
                        <td class="table-cell">
                            <a href="{{ route('web.admin.patients.show', $apt->patient_id ?? 0) }}" class="text-blue-600 hover:underline">
                                {{ $apt->patient?->name ?? 'Unknown' }}
                            </a>
                        </td>
                        <td class="table-cell">{{ $apt->doctor?->name ?? '-' }}</td>
                        <td class="table-cell">{{ $apt->doctor?->department ?? '-' }}</td>
                        <td class="table-cell text-slate-500">{{ \Illuminate\Support\Str::limit($apt->notes ?? '-', 40) }}</td>
                        <td class="table-cell">
                            <x-status-badge :status="$apt->status?->value ?? 'scheduled'" type="appointment" />
                        </td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                @if(in_array(is_object($apt->status) ? $apt->status->value : ($apt->status ?? ''), ['scheduled', 'confirmed']))
                                    <button type="button"
                                        @click="checkIn('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 font-medium transition-colors">
                                        Check In
                                    </button>
                                    <button type="button"
                                        @click="cancelAppointment('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 font-medium transition-colors">
                                        Cancel
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-sm">No appointments found for this date</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($appointments) && $appointments instanceof \Illuminate\Pagination\LengthAwarePaginator && $appointments->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">
            {{ $appointments->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function appointmentsPage() {
    return {
        filterDate: '{{ request('date', now()->toDateString()) }}',
        filterDoctor: '{{ request('doctor', '') }}',
        filterStatus: '{{ request('status', '') }}',

        applyFilters() {
            const params = new URLSearchParams();
            if (this.filterDate) params.set('date', this.filterDate);
            if (this.filterDoctor) params.set('doctor', this.filterDoctor);
            if (this.filterStatus) params.set('status', this.filterStatus);
            window.location.href = '{{ route('web.admin.appointments') }}?' + params.toString();
        },

        async checkIn(id) {
            if (confirm('Check in this patient?')) {
                try {
                    const res = await fetch(`/admin/appointments/${id}/check-in`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                    });
                    if (res.ok) window.location.reload();
                    else alert('Failed to check in patient.');
                } catch(e) { console.error(e); }
            }
        },

        async cancelAppointment(id) {
            if (confirm('Cancel this appointment?')) {
                const reason = prompt('Cancellation reason (optional):') || 'Cancelled by admin';
                try {
                    const res = await fetch(`/admin/appointments/${id}/cancel`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ cancellation_reason: reason }),
                    });
                    if (res.ok) window.location.reload();
                    else alert('Failed to cancel appointment.');
                } catch(e) { console.error(e); }
            }
        }
    };
}
</script>
@endpush
