@extends('layouts.app')
@section('title', 'Referrals')
@section('page-title', 'Referrals')

@section('content')
<div x-data="{ tab: 'incoming', actionLoading: null }">

    {{-- Tabs --}}
    <div class="flex gap-1 bg-white rounded-xl shadow-sm border border-slate-200 p-1 mb-4 w-fit">
        <button @click="tab='incoming'" :class="tab==='incoming'?'bg-blue-500 text-white shadow-sm':'text-slate-600 hover:bg-slate-100'" class="px-4 py-2 rounded-lg text-sm font-semibold transition-all">
            Incoming
            @if($incoming->where('status', 'pending')->count())
                <span class="ml-1 px-1.5 py-0.5 bg-red-500 text-white text-xs rounded-full">{{ $incoming->where('status', 'pending')->count() }}</span>
            @endif
        </button>
        <button @click="tab='outgoing'" :class="tab==='outgoing'?'bg-blue-500 text-white shadow-sm':'text-slate-600 hover:bg-slate-100'" class="px-4 py-2 rounded-lg text-sm font-semibold transition-all">
            Sent by Me
        </button>
    </div>

    {{-- Incoming referrals --}}
    <div x-show="tab==='incoming'">
        <div class="space-y-3">
            @forelse($incoming as $ref)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 {{ $ref->status === 'pending' ? 'border-l-4 border-l-amber-400' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            @if($ref->urgency === 'emergency')
                                <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded text-xs font-bold">🔴 Emergency</span>
                            @elseif($ref->urgency === 'priority')
                                <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-bold">🟡 Priority</span>
                            @else
                                <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-bold">🟢 Normal</span>
                            @endif
                            <span class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($ref->created_at)->diffForHumans() }}</span>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">{{ $ref->patient_name }}</h3>
                        <p class="text-sm text-slate-500">{{ $ref->patient_phone }} · {{ ucfirst($ref->patient_gender ?? '') }} {{ $ref->patient_age ? ', ' . $ref->patient_age . ' yrs' : '' }}</p>
                    </div>
                    <div class="text-right">
                        @if($ref->status === 'pending')
                            <span class="px-2.5 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-semibold">Pending</span>
                        @elseif($ref->status === 'accepted' || $ref->status === 'scheduled')
                            <span class="px-2.5 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Accepted</span>
                        @elseif($ref->status === 'declined')
                            <span class="px-2.5 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Declined</span>
                        @else
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">{{ ucfirst($ref->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm mb-3">
                    <div>
                        <span class="text-xs text-slate-400">From</span>
                        <p class="font-medium">{{ $ref->from_doctor_name }}</p>
                        <p class="text-xs text-slate-500">{{ $ref->from_department }}</p>
                    </div>
                    @if($ref->preferred_date)
                    <div>
                        <span class="text-xs text-slate-400">Preferred Slot</span>
                        <p class="font-medium">{{ \Carbon\Carbon::parse($ref->preferred_date)->format('M d, Y') }}</p>
                        @if($ref->preferred_time)
                            <p class="text-xs text-slate-500">{{ \Carbon\Carbon::createFromFormat('H:i', $ref->preferred_time)->format('g:i A') }}</p>
                        @endif
                    </div>
                    @endif
                </div>

                @if($ref->reason)
                <div class="p-2.5 bg-slate-50 rounded-lg mb-3">
                    <p class="text-xs text-slate-400 mb-0.5">Reason</p>
                    <p class="text-sm text-slate-700">{{ $ref->reason }}</p>
                </div>
                @endif

                @if($ref->complaint)
                <div class="p-2.5 bg-blue-50 rounded-lg mb-3">
                    <p class="text-xs text-blue-400 mb-0.5">Original Complaint</p>
                    <p class="text-sm text-slate-700">{{ $ref->complaint }}</p>
                </div>
                @endif

                @if($ref->status === 'pending')
                <div class="flex gap-2">
                    <button
                        @click="actionLoading='accept-{{ $ref->id }}'; fetch('/doctor/referrals/{{ $ref->id }}/accept', {method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{if(d.success)location.reload();}).finally(()=>actionLoading=null)"
                        :disabled="actionLoading==='accept-{{ $ref->id }}'"
                        class="flex-1 btn-success py-2 text-sm">
                        <span x-show="actionLoading!=='accept-{{ $ref->id }}'">Accept & Schedule</span>
                        <span x-show="actionLoading==='accept-{{ $ref->id }}'">Scheduling...</span>
                    </button>
                    <button
                        @click="if(confirm('Decline this referral?')){actionLoading='decline-{{ $ref->id }}'; fetch('/doctor/referrals/{{ $ref->id }}/decline', {method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{if(d.success)location.reload();}).finally(()=>actionLoading=null)}"
                        :disabled="actionLoading==='decline-{{ $ref->id }}'"
                        class="btn-danger py-2 text-sm px-4">
                        Decline
                    </button>
                </div>
                @endif
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-slate-400">No incoming referrals</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- Outgoing referrals --}}
    <div x-show="tab==='outgoing'" style="display:none">
        <div class="space-y-3">
            @forelse($outgoing as $ref)
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">{{ $ref->patient_name }}</h3>
                        <p class="text-xs text-slate-500">Referred to <span class="font-semibold">{{ $ref->to_doctor_name }}</span> ({{ $ref->to_department }})</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($ref->urgency === 'emergency')
                            <span class="px-1.5 py-0.5 bg-red-100 text-red-800 rounded text-xs font-medium">🔴</span>
                        @elseif($ref->urgency === 'priority')
                            <span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-medium">🟡</span>
                        @endif
                        @if($ref->status === 'pending')
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded text-xs font-semibold">Pending</span>
                        @elseif(in_array($ref->status, ['accepted', 'scheduled']))
                            <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded text-xs font-semibold">Accepted</span>
                        @elseif($ref->status === 'declined')
                            <span class="px-2 py-0.5 bg-red-100 text-red-800 rounded text-xs font-semibold">Declined</span>
                        @endif
                    </div>
                </div>
                @if($ref->reason)<p class="text-xs text-slate-500">{{ $ref->reason }}</p>@endif
                <p class="text-xs text-slate-400 mt-1">{{ \Carbon\Carbon::parse($ref->created_at)->diffForHumans() }}</p>
            </div>
            @empty
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-8 text-center">
                <p class="text-slate-400">No outgoing referrals</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
