@extends('layouts.app')
@section('title', 'Lab Dashboard')
@section('page-title', 'Pathology Lab')

@section('content')
<div x-data="labDashboard()" x-init="startAutoRefresh()">

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold text-slate-500 uppercase">Pending</p>
            <p class="text-2xl font-bold text-yellow-600" x-text="stats.pending">{{ $stats['pending'] }}</p>
            <p class="text-xs text-slate-400 mt-1">Awaiting collection</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold text-slate-500 uppercase">In Progress</p>
            <p class="text-2xl font-bold text-blue-600" x-text="stats.in_progress">{{ $stats['in_progress'] }}</p>
            <p class="text-xs text-slate-400 mt-1">Samples collected</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4">
            <p class="text-xs font-semibold text-slate-500 uppercase">Completed</p>
            <p class="text-2xl font-bold text-green-600" x-text="stats.completed">{{ $stats['completed'] }}</p>
            <p class="text-xs text-slate-400 mt-1">Results verified</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-4" :class="stats.stat_urgent > 0 ? 'ring-2 ring-red-300 bg-red-50' : ''">
            <p class="text-xs font-semibold text-slate-500 uppercase">STAT / Urgent</p>
            <p class="text-2xl font-bold text-red-600" x-text="stats.stat_urgent">{{ $stats['stat_urgent'] }}</p>
            <p class="text-xs text-slate-400 mt-1">Priority pending</p>
        </div>
    </div>

    {{-- Filters Row --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        {{-- Status Tabs --}}
        <div class="flex items-center gap-1.5">
            <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
                All <span class="text-xs opacity-75" x-text="'(' + orders.length + ')'"></span>
            </button>
            <button @click="filter = 'pending'" :class="filter === 'pending' ? 'bg-yellow-600 text-white' : 'bg-white text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
                Pending
            </button>
            <button @click="filter = 'in_progress'" :class="filter === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
                In Progress
            </button>
            <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-slate-600'" class="px-3 py-1.5 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
                Completed
            </button>
        </div>

        {{-- Date Filter --}}
        <div class="flex items-center gap-1.5 ml-auto">
            <a href="?date=today" class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors {{ $dateFilter === 'today' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">Today</a>
            <a href="?date=week" class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors {{ $dateFilter === 'week' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">This Week</a>
            <a href="?date=all" class="px-3 py-1.5 rounded-lg text-sm font-medium border transition-colors {{ $dateFilter === 'all' ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">All</a>
        </div>

        {{-- Search --}}
        <div class="w-full sm:w-auto">
            <input type="text" x-model="search" placeholder="Search patient, test, doctor..." class="input-field w-full sm:w-64 text-sm">
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Tests</th>
                        <th class="table-header">Ordered By</th>
                        <th class="table-header">Priority</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Time</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="order in filteredOrders" :key="order.id">
                        <tr class="hover:bg-slate-50" :class="(order.priority === 'stat' && order.status !== 'completed') ? 'bg-red-50/50' : ''">
                            {{-- Patient --}}
                            <td class="table-cell">
                                <p class="font-semibold text-slate-900 text-sm" x-text="order.patient?.name ?? 'Unknown'"></p>
                                <p class="text-xs text-slate-400" x-text="(order.patient?.gender ? (order.patient.gender.charAt(0).toUpperCase()) : '') + (order.patient?.age_approximate ? ', ' + order.patient.age_approximate + 'y' : '') + (order.patient?.phone ? ' · ' + order.patient.phone : '')"></p>
                            </td>
                            {{-- Tests --}}
                            <td class="table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="item in (order.items || [])" :key="item.name">
                                        <span class="inline-block bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded font-medium" x-text="item.name"></span>
                                    </template>
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5" x-show="(order.items || []).length > 1" x-text="(order.items || []).length + ' tests'"></p>
                            </td>
                            {{-- Ordered By --}}
                            <td class="table-cell">
                                <p class="text-sm text-slate-700" x-text="order.ordered_by?.name ?? '-'"></p>
                            </td>
                            {{-- Priority --}}
                            <td class="table-cell">
                                <span :class="{
                                    'bg-red-100 text-red-700 ring-1 ring-red-300': order.priority === 'stat',
                                    'bg-amber-100 text-amber-700': order.priority === 'urgent',
                                    'bg-slate-100 text-slate-500': order.priority === 'routine' || !order.priority
                                }" class="px-2 py-0.5 rounded-full text-xs font-bold uppercase" x-text="order.priority || 'routine'"></span>
                            </td>
                            {{-- Status --}}
                            <td class="table-cell">
                                <span :class="{
                                    'bg-yellow-100 text-yellow-700': order.status === 'ordered' || order.status === 'accepted',
                                    'bg-blue-100 text-blue-700': order.status === 'in_progress',
                                    'bg-green-100 text-green-700': order.status === 'completed'
                                }" class="px-2 py-0.5 rounded-full text-xs font-medium" x-text="statusLabel(order.status)"></span>
                                <p class="text-xs text-slate-400 mt-0.5" x-show="order.sample_collected_at" x-text="'Collected: ' + formatTime(order.sample_collected_at)"></p>
                            </td>
                            {{-- Time --}}
                            <td class="table-cell">
                                <p class="text-sm text-slate-700" x-text="formatTime(order.created_at)"></p>
                                <p class="text-xs text-slate-400" x-text="formatDate(order.created_at)"></p>
                            </td>
                            {{-- Actions --}}
                            <td class="table-cell">
                                <div class="flex items-center gap-1.5">
                                    <template x-if="order.status === 'ordered' || order.status === 'accepted'">
                                        <button @click="collectSample(order.id)" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                            Collect Sample
                                        </button>
                                    </template>
                                    <template x-if="order.status === 'in_progress'">
                                        <div class="flex items-center gap-1.5">
                                            <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                                                Enter Results
                                            </a>
                                            <button @click="verify(order.id)" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-colors font-medium">
                                                Verify & Send
                                            </button>
                                        </div>
                                    </template>
                                    <template x-if="order.status === 'completed'">
                                        <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-200 transition-colors font-medium">
                                            View Results
                                        </a>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="filteredOrders.length === 0">
                        <tr>
                            <td colspan="7" class="text-center text-slate-400 py-12">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                                <p class="text-sm font-medium">No lab orders found</p>
                                <p class="text-xs mt-1">Orders from doctors will appear here automatically</p>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Auto-refresh indicator --}}
    <p class="text-xs text-slate-400 text-center mt-3">Auto-refreshes every 10 seconds</p>
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

        get filteredOrders() {
            let result = this.orders;

            // Status filter
            if (this.filter === 'pending') {
                result = result.filter(o => o.status === 'ordered' || o.status === 'accepted');
            } else if (this.filter !== 'all') {
                result = result.filter(o => o.status === this.filter);
            }

            // Search filter
            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                result = result.filter(o => {
                    const patient = (o.patient?.name || '').toLowerCase();
                    const phone = (o.patient?.phone || '').toLowerCase();
                    const doctor = (o.ordered_by_staff?.name || '').toLowerCase();
                    const tests = (o.items || []).map(i => i.name.toLowerCase()).join(' ');
                    return patient.includes(q) || phone.includes(q) || doctor.includes(q) || tests.includes(q);
                });
            }

            return result;
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            const today = new Date();
            if (d.toDateString() === today.toDateString()) return 'Today';
            const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);
            if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        },

        formatTime(dateStr) {
            if (!dateStr) return '-';
            return new Date(dateStr).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        },

        statusLabel(status) {
            const labels = { ordered: 'Pending', accepted: 'Pending', in_progress: 'In Progress', completed: 'Completed' };
            return labels[status] || status;
        },

        async collectSample(id) {
            if (!confirm('Mark sample as collected?')) return;
            const res = await fetch('/lab/' + id + '/collect', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            });
            if (res.ok) this.refreshData();
        },

        async verify(id) {
            if (!confirm('Verify results and notify patient via WhatsApp?')) return;
            const res = await fetch('/lab/' + id + '/verify', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
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
                    this.orders = data.orders;
                    this.stats = data.stats;
                }
            } catch (e) {
                // Silent fail on refresh
            }
        },

        startAutoRefresh() {
            this.refreshInterval = setInterval(() => this.refreshData(), 10000);
        }
    }
}
</script>
@endpush
