{{-- Reusable lab-bookings table. Expects $labBookings (collection of Order). --}}
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Time</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Tests</th>
                    <th class="table-header">Type</th>
                    <th class="table-header">Source</th>
                    <th class="table-header">Token</th>
                    <th class="table-header">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($labBookings as $o)
                    @php
                        $tests = collect(is_array($o->items) ? $o->items : [])->pluck('name')->filter()->implode(', ');
                        $status = is_object($o->status) ? $o->status->value : $o->status;
                        $source = $o->ordered_by
                            ? 'Dr. referral'
                            : ucfirst(str_replace('_', ' ', $o->booking_source ?? 'self'));
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell font-medium">{{ $o->scheduled_for ? $o->scheduled_for->format('h:i A') : 'Walk-in' }}</td>
                        <td class="table-cell">{{ $o->patient?->name ?? 'Unknown' }}</td>
                        <td class="table-cell text-slate-600">{{ \Illuminate\Support\Str::limit($tests, 60) ?: '-' }}</td>
                        <td class="table-cell capitalize">{{ $o->type }}</td>
                        <td class="table-cell text-slate-500">{{ $source }}</td>
                        <td class="table-cell font-mono text-xs">{{ $o->notes ?? '-' }}</td>
                        <td class="table-cell">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $status === 'completed' ? 'bg-green-100 text-green-700' : ($status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400 text-sm">No lab bookings for this date.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
