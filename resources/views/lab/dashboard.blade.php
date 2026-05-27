@extends('layouts.app')
@section('title', 'Pathology Lab')
@section('page-title', 'Pathology Lab')

@section('content')
<div x-data="labDashboard()" x-init="init()">

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Today</p>
            <p class="text-xl font-bold text-slate-800 mt-0.5" x-text="stats.total">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-3.5">
            <p class="text-[10px] font-bold text-yellow-600 uppercase tracking-wider">Pending</p>
            <p class="text-xl font-bold text-yellow-700 mt-0.5" x-text="stats.pending">{{ $stats['pending'] }}</p>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-3.5">
            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Processing</p>
            <p class="text-xl font-bold text-blue-700 mt-0.5" x-text="stats.in_progress">{{ $stats['in_progress'] }}</p>
        </div>
        <div class="bg-green-50 rounded-xl border border-green-200 p-3.5">
            <p class="text-[10px] font-bold text-green-600 uppercase tracking-wider">Completed</p>
            <p class="text-xl font-bold text-green-700 mt-0.5" x-text="stats.completed">{{ $stats['completed'] }}</p>
        </div>
        <div class="rounded-xl border p-3.5" :class="stats.critical > 0 ? 'bg-red-50 border-red-300 ring-2 ring-red-200 animate-pulse' : 'bg-white border-slate-200'">
            <p class="text-[10px] font-bold uppercase tracking-wider" :class="stats.critical > 0 ? 'text-red-600' : 'text-slate-400'">Critical</p>
            <p class="text-xl font-bold mt-0.5" :class="stats.critical > 0 ? 'text-red-700' : 'text-slate-800'" x-text="stats.critical">{{ $stats['critical'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-3.5">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg TAT</p>
            <p class="text-xl font-bold text-slate-800 mt-0.5" x-text="stats.avg_tat">{{ $stats['avg_tat'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex items-center gap-1.5">
            <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition-colors">All</button>
            <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition-colors">Pending</button>
            <button @click="filter = 'in_progress'" :class="filter === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition-colors">Processing</button>
            <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition-colors">Completed</button>
        </div>
        <div class="flex items-center gap-1.5 ml-auto">
            <a href="?date=today" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ $dateFilter === 'today' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">Today</a>
            <a href="?date=week" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ $dateFilter === 'week' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">Week</a>
            <a href="?date=all" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors {{ $dateFilter === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">All</a>
        </div>
        <input type="text" x-model="search" placeholder="Search patient, test, doctor..." class="input-field w-full sm:w-56 text-sm">
    </div>

    {{-- Orders as Cards --}}
    <div class="space-y-3">
        <template x-for="order in filteredOrders" :key="order.id">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden transition-all"
                 :class="{
                     'border-red-300 bg-red-50/30': order.priority === 'stat' && order.status !== 'completed',
                     'border-amber-300 bg-amber-50/20': order.priority === 'urgent' && order.status !== 'completed',
                     'border-slate-200': order.priority === 'routine' || !order.priority || order.status === 'completed',
                     'ring-2 ring-red-400': order.has_critical && !order.critical_acknowledged
                 }">
                <div class="p-4">
                    {{-- Top: Patient + Badges --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
                                 :class="order.patient?.gender === 'male' ? 'bg-blue-500' : (order.patient?.gender === 'female' ? 'bg-pink-500' : 'bg-slate-400')"
                                 x-text="(order.patient?.name ?? 'U').charAt(0).toUpperCase()"></div>
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900 text-sm truncate" x-text="order.patient?.name ?? 'Unknown'"></p>
                                <p class="text-xs text-slate-400" x-text="[order.patient?.gender ? order.patient.gender.charAt(0).toUpperCase() : '', order.patient?.age_approximate ? order.patient.age_approximate + 'y' : '', order.patient?.phone || ''].filter(Boolean).join(' · ')"></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span x-show="order.sample_id" class="font-mono bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[10px]" x-text="order.sample_id"></span>
                            <span :class="{
                                'bg-red-100 text-red-700 ring-1 ring-red-300': order.priority === 'stat',
                                'bg-amber-100 text-amber-700': order.priority === 'urgent',
                                'bg-slate-100 text-slate-500': !order.priority || order.priority === 'routine'
                            }" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase" x-text="order.priority || 'routine'"></span>
                            <span :class="{
                                'bg-yellow-100 text-yellow-700': order.status === 'ordered' || order.status === 'accepted',
                                'bg-blue-100 text-blue-700': order.status === 'in_progress',
                                'bg-green-100 text-green-700': order.status === 'completed'
                            }" class="px-2 py-0.5 rounded-full text-[10px] font-bold" x-text="statusLabel(order.status)"></span>
                        </div>
                    </div>

                    {{-- Tests --}}
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <template x-for="item in (order.items || [])" :key="item.name">
                            <span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full font-medium" x-text="item.name"></span>
                        </template>
                    </div>

                    {{-- Meta row --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400 mb-3">
                        <span x-text="'Dr. ' + (order.ordered_by?.name ?? '-')"></span>
                        <span x-text="formatTime(order.created_at) + ' · ' + formatDate(order.created_at)"></span>
                        <span :class="{'text-green-600': order.tat_color === 'green', 'text-yellow-600 font-semibold': order.tat_color === 'yellow', 'text-red-600 font-bold': order.tat_color === 'red'}"
                              x-text="'TAT: ' + order.tat_display"
                              x-show="order.status !== 'completed'"></span>
                        <span x-show="order.lab_status" class="font-medium text-slate-500" x-text="labStatusLabel(order.lab_status)"></span>
                    </div>

                    {{-- Critical alert banner --}}
                    <div x-show="order.has_critical && !order.critical_acknowledged" class="bg-red-100 border border-red-300 rounded-lg px-3 py-2 mb-3 flex items-center justify-between">
                        <p class="text-xs font-bold text-red-700">CRITICAL VALUES — requires doctor acknowledgment</p>
                        <button @click="acknowledgeCritical(order.id)" class="text-xs bg-red-600 text-white px-3 py-1 rounded-lg hover:bg-red-700 font-medium flex-shrink-0 ml-2">Acknowledge</button>
                    </div>

                    {{-- Actions --}}
                        <div class="flex flex-wrap items-center gap-2">
                            <template x-if="order.status === 'ordered' || order.status === 'accepted'">
                                <button @click="collectSample(order.id)" class="text-xs bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                                    Collect Sample
                                </button>
                            </template>
                            <template x-if="order.status === 'in_progress' && (!order.lab_status || order.lab_status === 'collected')">
                                <button @click="updateStatus(order.id, 'received')" class="text-xs bg-slate-600 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 font-medium">
                                    Mark Received
                                </button>
                            </template>
                            <template x-if="order.status === 'in_progress' && order.lab_status === 'received'">
                                <button @click="updateStatus(order.id, 'processing')" class="text-xs bg-slate-600 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 font-medium">
                                    Start Processing
                                </button>
                            </template>
                            <template x-if="order.status === 'in_progress'">
                                <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
                                    Enter Results
                                </a>
                            </template>
                            <template x-if="order.status === 'in_progress' && order.result_entered_at">
                                <button @click="verify(order.id, order.has_critical && !order.critical_acknowledged)" class="text-xs bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors font-semibold">
                                    Verify & Release
                                </button>
                            </template>
                            <template x-if="order.status === 'completed'">
                                <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 font-medium">
                                    View Results
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

        {{-- Empty State --}}
        <template x-if="filteredOrders.length === 0">
            <div class="text-center py-16">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                <p class="text-sm font-semibold text-slate-500">No lab orders found</p>
                <p class="text-xs text-slate-400 mt-1">Orders from doctors will appear here automatically</p>
            </div>
        </template>
    </div>

    {{-- Refresh indicator --}}
    <p class="text-[10px] text-slate-400 text-center mt-4">Auto-refreshes every 5 seconds</p>

    {{-- Sound for new orders --}}
    <audio id="alertSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdH2JkZeXlZGMh4J+fH19gIWKjo+OjIqHhIKBgIB/gIGDhoeIiIeGhYSDgoGBgA==" type="audio/wav">
    </audio>
</div>

@endsection

@push('scripts')
<script>
function labDashboard() {
    return {
        filter: 'all',
        search: '',
        orders: @json($orders),
        stats: @json($stats),
        refreshInterval: null,
        lastOrderCount: @json(count($orders)),

        get filteredOrders() {
            let result = this.orders;
            if (this.filter === 'pending') result = result.filter(o => o.status === 'ordered' || o.status === 'accepted');
            else if (this.filter !== 'all') result = result.filter(o => o.status === this.filter);

            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                result = result.filter(o => {
                    return (o.patient?.name || '').toLowerCase().includes(q)
                        || (o.patient?.phone || '').includes(q)
                        || (o.ordered_by?.name || '').toLowerCase().includes(q)
                        || (o.sample_id || '').toLowerCase().includes(q)
                        || (o.items || []).some(i => i.name.toLowerCase().includes(q));
                });
            }
            return result;
        },

        init() {
            this.refreshInterval = setInterval(() => this.refreshData(), 5000);
        },

        formatDate(d) {
            if (!d) return '-';
            const date = new Date(d);
            const today = new Date();
            if (date.toDateString() === today.toDateString()) return 'Today';
            return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        },

        formatTime(d) {
            if (!d) return '-';
            return new Date(d).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        },

        statusLabel(s) {
            return { ordered: 'Pending', accepted: 'Pending', in_progress: 'Processing', completed: 'Completed' }[s] || s;
        },

        labStatusLabel(s) {
            return { collected: 'Sample Collected', transported: 'In Transport', received: 'Received in Lab', processing: 'Processing', result_entry: 'Results Entered', verified: 'Verified', released: 'Released' }[s] || s;
        },

        async collectSample(id) {
            if (!confirm('Collect sample for this order?')) return;
            const res = await fetch('/lab/' + id + '/collect', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                if (data.sample_id) alert('Sample ID: ' + data.sample_id);
                this.refreshData();
            }
        },

        async updateStatus(id, status) {
            const res = await fetch('/lab/' + id + '/status', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ lab_status: status })
            });
            if (res.ok) this.refreshData();
        },

        async verify(id, hasCriticalUnacknowledged) {
            if (hasCriticalUnacknowledged) {
                alert('Cannot verify — critical values must be acknowledged first.');
                return;
            }
            if (!confirm('Verify results and send report to patient via WhatsApp?')) return;
            const res = await fetch('/lab/' + id + '/verify', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            });
            if (res.ok) this.refreshData();
            else {
                const data = await res.json().catch(() => ({}));
                alert(data.message || 'Failed to verify');
            }
        },

        async acknowledgeCritical(id) {
            const action = prompt('Action taken for critical values:', 'Reviewed and noted');
            if (action === null) return;
            const res = await fetch('/lab/' + id + '/acknowledge-critical', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action })
            });
            if (res.ok) this.refreshData();
        },

        async refreshData() {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('_', Date.now());
                const res = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    // New order alert
                    if (data.orders.length > this.lastOrderCount) {
                        try { document.getElementById('alertSound')?.play(); } catch(e) {}
                    }
                    this.lastOrderCount = data.orders.length;
                    this.orders = data.orders;
                    this.stats = data.stats;
                }
            } catch (e) {}
        }
    }
}
</script>
@endpush
