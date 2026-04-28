@props([
    'patient' => null,
])

@php
    $p = $patient;
    $age = $p->date_of_birth ? \Carbon\Carbon::parse($p->date_of_birth)->age : ($p->age_approximate ?? '-');
    $hasInsurance = !empty($p->insurance_details);
@endphp

<tr class="hover:bg-slate-50 cursor-pointer transition-colors" onclick="window.location='{{ route('web.admin.patients.show', $p->id) }}'">
    <td class="table-cell">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-sm font-medium">
                {{ strtoupper(substr($p->name ?? 'U', 0, 1)) }}
            </div>
            <div>
                <p class="font-medium text-slate-900">{{ $p->name ?? 'Unknown' }}</p>
                <p class="text-xs text-slate-500">{{ $p->phone }}</p>
            </div>
        </div>
    </td>
    <td class="table-cell">{{ $p->phone ?? '-' }}</td>
    <td class="table-cell">{{ $age }}</td>
    <td class="table-cell capitalize">{{ $p->gender ?? '-' }}</td>
    <td class="table-cell uppercase">{{ $p->language_preference ?? 'en' }}</td>
    <td class="table-cell">
        @if($hasInsurance)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Insured</span>
        @else
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Self-pay</span>
        @endif
    </td>
    <td class="table-cell text-slate-500">{{ $p->updated_at?->diffForHumans() ?? '-' }}</td>
    <td class="table-cell">
        <a href="{{ route('web.admin.patients.show', $p->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View</a>
    </td>
</tr>
