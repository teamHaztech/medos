@props([
    'status' => 'unknown',
    'type' => 'appointment',
])

@php
    // Handle enum objects — extract string value
    $statusStr = is_object($status) ? ($status->value ?? (string)$status) : (string)$status;

    $colors = match($type) {
        'appointment' => match($statusStr) {
            'scheduled'   => 'bg-blue-100 text-blue-800',
            'confirmed'   => 'bg-green-100 text-green-800',
            'checked_in'  => 'bg-yellow-100 text-yellow-800',
            'in_progress' => 'bg-purple-100 text-purple-800',
            'completed'   => 'bg-slate-100 text-slate-600',
            'no_show'     => 'bg-red-100 text-red-800',
            'cancelled'   => 'bg-red-100 text-red-800',
            'rescheduled' => 'bg-orange-100 text-orange-800',
            default       => 'bg-slate-100 text-slate-600',
        },
        'encounter' => match($statusStr) {
            'initiated', 'intake_complete' => 'bg-blue-100 text-blue-800',
            'triaged'      => 'bg-yellow-100 text-yellow-800',
            'booked'       => 'bg-blue-100 text-blue-800',
            'checked_in'   => 'bg-yellow-100 text-yellow-800',
            'in_progress'  => 'bg-purple-100 text-purple-800',
            'completed'    => 'bg-green-100 text-green-800',
            'cancelled'    => 'bg-red-100 text-red-800',
            'no_show'      => 'bg-red-100 text-red-800',
            default        => 'bg-slate-100 text-slate-600',
        },
        'payment' => match($statusStr) {
            'pending'   => 'bg-yellow-100 text-yellow-800',
            'paid'      => 'bg-green-100 text-green-800',
            'partial'   => 'bg-orange-100 text-orange-800',
            'overdue'   => 'bg-red-100 text-red-800',
            'refunded'  => 'bg-purple-100 text-purple-800',
            'waived'    => 'bg-slate-100 text-slate-600',
            default     => 'bg-slate-100 text-slate-600',
        },
        'triage' => match($statusStr) {
            'emergency'   => 'bg-red-100 text-red-800',
            'urgent'      => 'bg-orange-100 text-orange-800',
            'semi_urgent' => 'bg-yellow-100 text-yellow-800',
            'routine'     => 'bg-green-100 text-green-800',
            default       => 'bg-slate-100 text-slate-600',
        },
        default => 'bg-slate-100 text-slate-600',
    };

    $label = ucwords(str_replace('_', ' ', $statusStr));
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$colors}"]) }}>
    {{ $label }}
</span>
