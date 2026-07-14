@extends('layouts.app')

@section('title', 'Appointments')
@section('page-title', 'Appointments')

@section('content')
<div x-data="appointmentsPage()">

    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <p class="text-sm text-slate-500">{{ ($isPractitioner ?? false) ? 'My appointments' : 'Walk-in &amp; online appointments (one synced list)' }}</p>
        <div class="flex items-center gap-2">
            @include('admin._add_to_queue', ['addBtnClass' => 'btn-secondary'])
            <a href="{{ route('web.admin.appointments.schedule') }}" class="btn-primary">+ Book Appointment</a>
        </div>
    </div>

    {{-- View toggle --}}
    @php $base = request()->except(['view', 'page']); @endphp
    <div class="flex items-center gap-2 mb-4">
        @foreach(['list' => 'List', 'board' => 'Day Board', 'upcoming' => 'Upcoming'] as $vk => $vlabel)
            <a href="{{ route('web.admin.appointments', array_merge($base, ['view' => $vk])) }}"
               class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ $view === $vk ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50' }}">{{ $vlabel }}</a>
        @endforeach
    </div>

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-end gap-4 mb-6">
        @if($view !== 'upcoming')
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Date</label>
            <input type="date" x-model="filterDate" @change="applyFilters()" class="input-field" value="{{ request('date', now()->toDateString()) }}">
        </div>
        @endif
        <div class="flex-1 min-w-0">
            <label class="block text-xs font-medium text-slate-500 mb-1">Search</label>
            <input type="text" x-model="filterSearch" @keydown.enter="applyFilters()" class="input-field" placeholder="Patient name, phone, or token…">
        </div>
        @unless($isPractitioner ?? false)
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">Doctor</label>
            <select x-model="filterDoctor" @change="applyFilters()" class="input-field">
                <option value="">All Doctors</option>
                @foreach($doctors ?? [] as $doctor)
                    <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                @endforeach
            </select>
        </div>
        @endunless
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
        <div>
            <button @click="applyFilters()" class="btn-secondary">Apply</button>
        </div>
    </div>

    @if($view === 'board')
    {{-- Day board: appointments grouped by doctor --}}
    <div class="space-y-4">
        @forelse($board as $docName => $appts)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">{{ $docName }}</h3>
                    <span class="text-xs text-slate-400">{{ $appts->count() }} appt(s)</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($appts as $apt)
                        @php $bst = is_object($apt->status) ? $apt->status->value : ($apt->status ?? 'scheduled'); @endphp
                        <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50">
                            <div class="w-20 text-sm font-semibold text-slate-700">{{ $apt->slot_start?->format('g:i A') ?? '—' }}</div>
                            <div class="flex-1 min-w-0">
                                <a href="{{ route('web.admin.patients.show', $apt->patient_id ?? 0) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ $apt->patient?->name ?? 'Unknown' }}</a>
                                <p class="text-xs text-slate-400">Token {{ $apt->notes ?? '—' }}</p>
                            </div>
                            <x-status-badge :status="$bst" type="appointment" />
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400 text-sm">No appointments for this day.</div>
        @endforelse
    </div>
    @else
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
                        <th class="table-header">Token</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Source</th>
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
                            @php
                                $src = is_object($apt->booking_source ?? null) ? $apt->booking_source->value : ($apt->booking_source ?? '');
                                $srcMap = ['walk_in' => ['Walk-in', 'bg-blue-100 text-blue-700'], 'whatsapp' => ['Online', 'bg-green-100 text-green-700'], 'kiosk' => ['Kiosk', 'bg-purple-100 text-purple-700'], 'referral' => ['Referral', 'bg-amber-100 text-amber-700']];
                                [$sl, $sc] = $srcMap[$src] ?? [$src ? ucfirst(str_replace('_', ' ', $src)) : '—', 'bg-slate-100 text-slate-600'];
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $sc }}">{{ $sl }}</span>
                        </td>
                        <td class="table-cell">
                            @php $st = is_object($apt->status) ? $apt->status->value : ($apt->status ?? ''); @endphp
                            <div class="flex items-center gap-2">
                                @if(($consultModule ?? null) && $apt->patient_id && ! in_array($st, ['cancelled', 'no_show', 'completed']))
                                    <a href="{{ url($consultModule) }}?patient={{ $apt->patient_id }}"
                                        class="text-xs px-2 py-1 rounded bg-teal-100 text-teal-700 hover:bg-teal-200 font-semibold transition-colors">
                                        Consult →
                                    </a>
                                @endif
                                @if(in_array($st, ['scheduled', 'confirmed']))
                                    <button type="button"
                                        @click="checkIn('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-green-100 text-green-700 hover:bg-green-200 font-medium transition-colors">
                                        Check In
                                    </button>
                                    <button type="button"
                                        @click="openReschedule('{{ $apt->id }}', '{{ $apt->doctor_id }}')"
                                        class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 font-medium transition-colors">
                                        Reschedule
                                    </button>
                                    <button type="button"
                                        @click="noShow('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium transition-colors">
                                        No-show
                                    </button>
                                    <button type="button"
                                        @click="cancelAppointment('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 font-medium transition-colors">
                                        Cancel
                                    </button>
                                @elseif($st === 'checked_in')
                                    <button type="button"
                                        @click="noShow('{{ $apt->id }}')"
                                        class="text-xs px-2 py-1 rounded bg-slate-100 text-slate-600 hover:bg-slate-200 font-medium transition-colors">
                                        No-show
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-slate-400">
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
    @endif

    {{-- Lab bookings (tests/scans booked directly or via referral) --}}
    <div class="mt-8">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            <h3 class="text-base font-semibold text-slate-700">Lab Bookings <span class="text-slate-400 font-normal">({{ $labBookings->count() }})</span></h3>
        </div>
        @include('lab._bookings_table', ['labBookings' => $labBookings])
    </div>

    {{-- Reschedule modal --}}
    <x-modal show="rescheduleOpen" title="Reschedule Appointment" max="lg">
            <form method="POST" :action="rescheduleAction">
                @csrf
                <div x-show="rLoading" class="text-sm text-slate-400 py-4">Loading availability…</div>
                <template x-if="!rLoading && rDays.length">
                    <div>
                        <div class="flex gap-1 overflow-x-auto pb-2">
                            <template x-for="day in rDays" :key="day.date">
                                <button type="button" @click="rDay = day; rSlot = null"
                                    :class="rDay?.date===day.date ? 'bg-blue-500 text-white' : (day.available>0 ? 'bg-white border border-slate-200 text-slate-700 hover:bg-blue-50' : 'bg-slate-100 text-slate-400')"
                                    class="flex flex-col items-center px-3 py-2 rounded-lg flex-shrink-0" style="min-width:60px">
                                    <span class="font-semibold" style="font-size:10px" x-text="day.day"></span>
                                    <span class="text-sm font-bold" x-text="day.dateFmt.split(' ')[1]"></span>
                                    <span style="font-size:10px" :class="day.available>0?'text-green-600':'text-red-400'" x-text="day.available+' free'"></span>
                                </button>
                            </template>
                        </div>
                        <template x-if="rDay">
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="slot in rDay.slots" :key="slot.time">
                                    <button type="button" :disabled="!slot.available"
                                        @click="rSlot = {date: rDay.date, time: slot.time, display: slot.display}"
                                        :class="rSlot && rSlot.date===rDay.date && rSlot.time===slot.time ? 'bg-green-500 text-white ring-2 ring-green-300' : (slot.available ? 'bg-white border border-slate-200 text-slate-700 hover:bg-green-50' : 'bg-slate-100 text-slate-300 line-through')"
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium" x-text="slot.display"></button>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <p x-show="!rLoading && rDays.length===0" class="text-sm text-slate-400 py-3">No schedule/slots available for this doctor.</p>
                <input type="hidden" name="slot_start" :value="rSlot ? (rSlot.date + ' ' + rSlot.time) : ''">
                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="btn-primary px-5 py-2.5" :disabled="!rSlot" :class="!rSlot ? 'opacity-40 cursor-not-allowed' : ''">Confirm New Time</button>
                    <button type="button" @click="rescheduleOpen = false" class="btn-secondary">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection

@push('scripts')
<script>
function appointmentsPage() {
    return {
        filterDate: '{{ request('date', now()->toDateString()) }}',
        filterDoctor: '{{ request('doctor', '') }}',
        filterStatus: '{{ request('status', '') }}',
        filterSearch: @js(request('search', '')),
        filterView: '{{ $view }}',

        applyFilters() {
            const params = new URLSearchParams();
            if (this.filterView && this.filterView !== 'list') params.set('view', this.filterView);
            if (this.filterView !== 'upcoming' && this.filterDate) params.set('date', this.filterDate);
            if (this.filterDoctor) params.set('doctor', this.filterDoctor);
            if (this.filterStatus) params.set('status', this.filterStatus);
            if (this.filterSearch) params.set('search', this.filterSearch);
            window.location.href = '{{ route('web.admin.appointments') }}?' + params.toString();
        },

        async noShow(id) {
            if (!confirm('Mark this appointment as no-show?')) return;
            try {
                const res = await fetch(`/admin/appointments/${id}/no-show`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });
                if (res.ok) window.location.reload();
                else { const d = await res.json().catch(() => ({})); alert(d.message || 'Failed to mark no-show.'); }
            } catch (e) { console.error(e); }
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
        },

        rescheduleOpen: false, rAptId: '', rDoctorId: '', rDays: [], rDay: null, rSlot: null, rLoading: false,
        get rescheduleAction() { return `/admin/appointments/${this.rAptId}/reschedule`; },
        async openReschedule(aptId, doctorId) {
            this.rAptId = aptId; this.rDoctorId = doctorId;
            this.rDays = []; this.rDay = null; this.rSlot = null;
            this.rescheduleOpen = true; this.rLoading = true;
            try {
                const r = await fetch('/ajax/doctor-slots/'+doctorId, {headers:{'Accept':'application/json'}});
                if (r.ok) { const d = await r.json(); this.rDays = d.days || []; this.rDay = this.rDays.find(x => x.available > 0) || this.rDays[0] || null; }
            } catch(e){}
            this.rLoading = false;
        }
    };
}
</script>
@endpush
