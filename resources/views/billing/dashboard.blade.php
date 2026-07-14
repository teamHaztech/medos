@extends('layouts.app')
@section('title', 'Billing Dashboard')
@section('page-title', 'Billing')

@php $cur = \App\Modules\Core\Services\RegionService::currency(); @endphp

@section('content')
    {{-- Sub-nav --}}
    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('web.billing.dashboard') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-blue-600 text-white">Dashboard</a>
        <a href="{{ route('web.billing.index') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Bills</a>
        <a href="{{ route('web.billing.services') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Service Master</a>
        <a href="{{ route('web.billing.new') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">+ New Bill</a>
        <a href="{{ route('web.billing.audit') }}" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 text-slate-600 hover:bg-slate-50">Audit &amp; Reports</a>
        <form method="GET" class="ml-auto flex items-end gap-2">
            <div><label class="block text-[10px] text-slate-400 uppercase">From</label><input type="date" name="from" value="{{ $from }}" class="input-field text-sm"></div>
            <div><label class="block text-[10px] text-slate-400 uppercase">To</label><input type="date" name="to" value="{{ $to }}" class="input-field text-sm"></div>
            <button class="btn-primary px-4">Apply</button>
        </form>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Today's Collection</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $cur }}{{ number_format($stats['today_collection']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Collection (range)</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $cur }}{{ number_format($stats['range_collection']) }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ \Illuminate\Support\Carbon::parse($from)->format('M d') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('M d') }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Outstanding</p>
            <p class="text-3xl font-bold {{ $stats['outstanding'] > 0 ? 'text-red-600' : 'text-slate-800' }} mt-1">{{ $cur }}{{ number_format($stats['outstanding']) }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $stats['bills_pending'] }} pending · {{ $stats['bills_partial'] }} partial</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Paid Bills</p>
            <p class="text-3xl font-bold text-slate-800 mt-1">{{ $stats['bills_paid'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Collection by method --}}
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Collection by Method</h3>
            @php $methodTotal = max(1, $byMethod->where('total', '>', 0)->sum('total')); @endphp
            <div class="space-y-3">
                @forelse($byMethod as $m)
                    @php $neg = (float) $m->total < 0; @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="text-slate-600 capitalize">{{ $m->method === 'refund' ? 'Refunds' : (\App\Modules\Billing\Models\BillPayment::METHODS[$m->method] ?? $m->method) }} <span class="text-xs text-slate-400">({{ $m->cnt }})</span></span>
                            <span class="font-semibold {{ $neg ? 'text-purple-600' : 'text-slate-700' }}">{{ $neg ? '−' : '' }}{{ $cur }}{{ number_format(abs($m->total)) }}</span>
                        </div>
                        <div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full {{ $neg ? 'bg-purple-400' : 'bg-blue-500' }} rounded-full" style="width: {{ max(0, round((abs($m->total) / $methodTotal) * 100)) }}%"></div></div>
                    </div>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No collections in this range.</p>
                @endforelse
            </div>
        </div>

        {{-- Recent payments --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">Recent Payments</h3>
            <div class="space-y-2 max-h-80 overflow-y-auto">
                @forelse($recent as $p)
                    <a href="{{ route('web.billing.show', $p->bill_id) }}" class="flex items-center justify-between py-2 border-b border-slate-50 last:border-0 hover:bg-slate-50 rounded-lg px-2 -mx-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $p->bill?->patient?->name ?? 'Patient' }}</p>
                            <p class="text-xs text-slate-400">{{ optional($p->paid_at)->format('M d, h:i A') }} · {{ $p->methodLabel() }}{{ $p->received_by_name ? ' · ' . $p->received_by_name : '' }}</p>
                        </div>
                        <span class="text-sm font-semibold {{ (float) $p->amount < 0 ? 'text-purple-600' : 'text-green-700' }}">{{ (float) $p->amount < 0 ? '− ' : '' }}{{ $cur }}{{ number_format(abs($p->amount), 2) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-400 text-center py-6">No payments yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Top outstanding --}}
    @if($outstandingBills->count())
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mt-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Top Outstanding Bills</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-slate-400 uppercase"><tr><th class="text-left py-1">Bill #</th><th class="text-left">Patient</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($outstandingBills as $b)
                        <tr>
                            <td class="py-2 font-mono text-slate-600">{{ $b->bill_number }}</td>
                            <td class="text-slate-800">{{ $b->patient?->name ?? '-' }}</td>
                            <td class="text-right text-slate-600">{{ $cur }}{{ number_format($b->total_amount, 2) }}</td>
                            <td class="text-right text-green-700">{{ $cur }}{{ number_format($b->amount_paid, 2) }}</td>
                            <td class="text-right font-semibold text-red-600">{{ $cur }}{{ number_format($b->balance_due, 2) }}</td>
                            <td class="text-right"><a href="{{ route('web.billing.show', $b->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">Collect</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection
