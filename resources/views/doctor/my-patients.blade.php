@extends('layouts.app')
@section('title', 'My Patients')
@section('page-title', 'My Patients')

@section('content')
    <div class="mb-4">
        <form method="GET" action="{{ route('web.doctor.patients') }}" class="max-w-md">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or phone..." class="input-field pl-10">
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Phone</th>
                    <th class="table-header">Age</th>
                    <th class="table-header">Gender</th>
                    <th class="table-header">Last Visit</th>
                    <th class="table-header"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($patients as $p)
                @php $age = $p->date_of_birth ? \Carbon\Carbon::parse($p->date_of_birth)->age : ($p->age_approximate ?? ''); @endphp
                <tr class="hover:bg-slate-50">
                    <td class="table-cell">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-sm font-bold">{{ strtoupper(substr($p->name, 0, 1)) }}</div>
                            <div>
                                <p class="font-medium text-slate-900">{{ $p->name }}</p>
                                @if(!empty($p->allergies))<p class="text-xs text-red-500">⚠ {{ is_array($p->allergies) ? implode(', ', $p->allergies) : '' }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="table-cell">{{ $p->phone }}</td>
                    <td class="table-cell">{{ $age }}</td>
                    <td class="table-cell capitalize">{{ $p->gender ?? '' }}</td>
                    <td class="table-cell text-slate-500">{{ $p->updated_at?->diffForHumans() }}</td>
                    <td class="table-cell"><a href="{{ route('web.doctor.patients.show', $p->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No patients yet</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($patients->hasPages())
        <div class="px-4 py-3 border-t border-slate-200">{{ $patients->withQueryString()->links() }}</div>
        @endif
    </div>
@endsection
