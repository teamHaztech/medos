@extends('layouts.app')
@section('title', 'Lab Dashboard')
@section('page-title', 'Lab Dashboard')

@section('content')
<div x-data="labDashboard()" x-init="startAutoRefresh()">
    {{-- Filter Tabs --}}
    <div class="flex items-center gap-2 mb-4">
        <button @click="filter = 'all'" :class="filter === 'all' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'" class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
            All <span class="ml-1 text-xs" x-text="'(' + orders.length + ')'"></span>
        </button>
        <button @click="filter = 'ordered'" :class="filter === 'ordered' ? 'bg-yellow-600 text-white' : 'bg-white text-slate-700'" class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
            Pending <span class="ml-1 text-xs" x-text="'(' + orders.filter(o => o.status === \'ordered\' || o.status === \'accepted\').length + ')'"></span>
        </button>
        <button @click="filter = 'in_progress'" :class="filter === 'in_progress' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'" class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
            In Progress <span class="ml-1 text-xs" x-text="'(' + orders.filter(o => o.status === \'in_progress\').length + ')'"></span>
        </button>
        <button @click="filter = 'completed'" :class="filter === 'completed' ? 'bg-green-600 text-white' : 'bg-white text-slate-700'" class="px-4 py-2 rounded-lg text-sm font-medium border border-slate-200 transition-colors">
            Completed <span class="ml-1 text-xs" x-text="'(' + orders.filter(o => o.status === \'completed\').length + ')'"></span>
        </button>
    </div>

    {{-- Orders Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="table-header">Order Date</th>
                    <th class="table-header">Patient</th>
                    <th class="table-header">Tests</th>
                    <th class="table-header">Priority</th>
                    <th class="table-header">Status</th>
                    <th class="table-header">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <template x-for="order in filteredOrders" :key="order.id">
                    <tr class="hover:bg-slate-50">
                        <td class="table-cell text-sm" x-text="formatDate(order.created_at)"></td>
                        <td class="table-cell font-medium" x-text="order.patient?.name ?? 'Unknown'"></td>
                        <td class="table-cell">
                            <template x-for="item in (order.items || [])" :key="item.name">
                                <span class="inline-block bg-slate-100 text-slate-700 text-xs px-2 py-0.5 rounded mr-1 mb-1" x-text="item.name"></span>
                            </template>
                        </td>
                        <td class="table-cell">
                            <span :class="{
                                'bg-red-100 text-red-700': order.priority === 'stat',
                                'bg-amber-100 text-amber-700': order.priority === 'urgent',
                                'bg-slate-100 text-slate-600': order.priority === 'routine' || !order.priority
                            }" class="px-2 py-0.5 rounded-full text-xs font-medium" x-text="order.priority || 'routine'"></span>
                        </td>
                        <td class="table-cell">
                            <span :class="{
                                'bg-yellow-100 text-yellow-700': order.status === 'ordered' || order.status === 'accepted',
                                'bg-blue-100 text-blue-700': order.status === 'in_progress',
                                'bg-green-100 text-green-700': order.status === 'completed'
                            }" class="px-2 py-0.5 rounded-full text-xs font-medium" x-text="statusLabel(order.status)"></span>
                        </td>
                        <td class="table-cell">
                            <div class="flex items-center gap-2">
                                <template x-if="order.status === 'ordered' || order.status === 'accepted'">
                                    <button @click="collectSample(order.id)" class="text-xs bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">
                                        Collect Sample
                                    </button>
                                </template>
                                <template x-if="order.status === 'in_progress'">
                                    <div class="flex items-center gap-2">
                                        <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition-colors">
                                            Enter Results
                                        </a>
                                        <button @click="verify(order.id)" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-colors">
                                            Verify
                                        </button>
                                    </div>
                                </template>
                                <template x-if="order.status === 'completed'">
                                    <a :href="'/lab/' + order.id + '/results'" class="text-xs bg-slate-600 text-white px-3 py-1.5 rounded-lg hover:bg-slate-700 transition-colors">
                                        View Results
                                    </a>
                                </template>
                            </div>
                        </td>
                    </tr>
                </template>
                <template x-if="filteredOrders.length === 0">
                    <tr>
                        <td colspan="6" class="table-cell text-center text-slate-400 py-8">No orders found</td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>

<script>
function labDashboard() {
    return {
        filter: 'all',
        orders: @json($orders),
        refreshInterval: null,

        get filteredOrders() {
            if (this.filter === 'all') return this.orders;
            if (this.filter === 'ordered') return this.orders.filter(o => o.status === 'ordered' || o.status === 'accepted');
            return this.orders.filter(o => o.status === this.filter);
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
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
            if (!confirm('Verify and complete this order?')) return;
            const res = await fetch('/lab/' + id + '/verify', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            });
            if (res.ok) this.refreshData();
        },

        async refreshData() {
            try {
                const res = await fetch('/lab', { headers: { 'Accept': 'text/html' } });
                const html = await res.text();
                const match = html.match(/orders:\s*(\[[\s\S]*?\]),\s*refreshInterval/);
                // Simple reload approach
                window.location.reload();
            } catch (e) {}
        },

        startAutoRefresh() {
            this.refreshInterval = setInterval(() => {
                window.location.reload();
            }, 10000);
        }
    }
}
</script>
@endsection
