@extends('layouts.app')
@section('title', 'Insurance Claims')
@section('page-title', 'Insurance Claims')

@php $currency = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
@if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif
@if(session('error'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif

<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="{{ route('web.claims.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ !request('status') ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">All</a>
    @foreach(['submitted'=>'Submitted','approved'=>'Approved','denied'=>'Denied'] as $k=>$l)
        <a href="{{ route('web.claims.index', ['status'=>$k]) }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold {{ request('status')===$k ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-600' }}">{{ $l }}</a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200"><tr>
                <th class="table-header">Patient</th><th class="table-header">Insurer / Policy</th><th class="table-header">Bill</th><th class="table-header text-right">Requested</th><th class="table-header text-right">Approved</th><th class="table-header">Filed</th><th class="table-header text-center">Status</th>
            </tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($claims as $claim)
                @php $cs = ['submitted'=>'bg-blue-100 text-blue-700','pending'=>'bg-amber-100 text-amber-700','approved'=>'bg-green-100 text-green-700','partially_approved'=>'bg-green-100 text-green-700','denied'=>'bg-red-100 text-red-700']; @endphp
                <tr>
                    <td class="px-4 py-2.5 text-sm text-slate-800">{{ $claim->patient?->name ?? '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-700">{{ $claim->insurer_name ?? $claim->insurer_code }}<span class="block text-xs text-slate-400">{{ $claim->policy_number }}</span></td>
                    <td class="px-4 py-2.5 text-sm">
                        @if($claim->bill)<a href="{{ route('web.billing.show', $claim->bill->id) }}" class="text-blue-600 hover:text-blue-800">{{ $claim->bill->bill_number }}</a>@else<span class="text-slate-400"></span>@endif
                    </td>
                    <td class="px-4 py-2.5 text-sm text-slate-700 text-right">{{ $currency }}{{ number_format($claim->requested_amount, 2) }}</td>
                    <td class="px-4 py-2.5 text-sm text-slate-700 text-right">{{ $claim->approved_amount !== null ? $currency.number_format($claim->approved_amount, 2) : '' }}</td>
                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ optional($claim->submitted_at)->format('d M, Y') }}</td>
                    <td class="px-4 py-2.5 text-center"><span class="text-xs px-2 py-0.5 rounded-full {{ $cs[$claim->status] ?? 'bg-slate-100 text-slate-600' }}">{{ ucfirst(str_replace('_',' ', $claim->status)) }}</span></td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-400">No claims filed yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $claims->withQueryString()->links() }}</div>
@endsection
