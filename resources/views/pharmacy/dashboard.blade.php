@extends('layouts.app')
@section('title', 'Pharmacy')
@section('page-title', 'Pharmacy')

@section('content')
<div x-data="pharmacyDashboard()" x-init="init()">
    {{-- Pending Orders --}}
    <div class="mb-8">
        <h3 class="text-sm font-semibold text-slate-700 mb-3">Pending Prescriptions</h3>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Date</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Medications</th>
                        <th class="table-header">Doctor</th>
                        <th class="table-header">Priority</th>
                        <th class="table-header">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="order in pendingOrders" :key="order.id">
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell text-sm" x-text="formatDate(order.created_at)"></td>
                            <td class="table-cell font-medium" x-text="order.patient?.name ?? 'Unknown'"></td>
                            <td class="table-cell">
                                <template x-for="item in (order.items || [])" :key="item.name + (item.dosage || '')">
                                    <div class="text-sm mb-1">
                                        <span class="font-medium" x-text="item.name"></span>
                                        <span class="text-slate-500" x-text="item.dosage ? ' - ' + item.dosage : ''"></span>
                                        <span class="text-slate-400 text-xs" x-text="freqLabel(item.frequency)"></span>
                                        <template x-if="item.duration">
                                            <span class="text-slate-400 text-xs" x-text="' x ' + item.duration"></span>
                                        </template>
                                        <template x-if="item.timing">
                                            <span class="text-slate-400 text-xs" x-text="' (' + item.timing + ')'"></span>
                                        </template>
                                    </div>
                                </template>
                            </td>
                            <td class="table-cell text-sm" x-text="order.ordered_by?.name ?? (order.ordered_by_staff?.name ?? (order.ordered_by_name ?? '-'))"></td>
                            <td class="table-cell">
                                <span :class="{
                                    'bg-red-100 text-red-700': order.priority === 'stat',
                                    'bg-amber-100 text-amber-700': order.priority === 'urgent',
                                    'bg-slate-100 text-slate-600': order.priority === 'routine' || !order.priority
                                }" class="px-2 py-0.5 rounded-full text-xs font-medium" x-text="order.priority || 'routine'"></span>
                            </td>
                            <td class="table-cell">
                                <button @click="dispense(order.id)" class="text-xs bg-green-600 text-white px-3 py-1.5 rounded-lg hover:bg-green-700 transition-colors">
                                    Dispense
                                </button>
                            </td>
                        </tr>
                    </template>
                    <template x-if="pendingOrders.length === 0">
                        <tr>
                            <td colspan="6" class="table-cell text-center text-slate-400 py-8">No pending prescriptions</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Dispensed Orders --}}
    <div x-show="dispensedOrders.length > 0">
        <h3 class="text-sm font-semibold text-slate-400 mb-3">Recently Dispensed</h3>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden opacity-60">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="table-header">Date</th>
                        <th class="table-header">Patient</th>
                        <th class="table-header">Medications</th>
                        <th class="table-header">Doctor</th>
                        <th class="table-header">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="order in dispensedOrders" :key="order.id">
                        <tr>
                            <td class="table-cell text-sm" x-text="formatDate(order.created_at)"></td>
                            <td class="table-cell font-medium" x-text="order.patient?.name ?? 'Unknown'"></td>
                            <td class="table-cell">
                                <template x-for="item in (order.items || [])" :key="item.name + (item.dosage || '')">
                                    <span class="inline-block bg-slate-100 text-slate-600 text-xs px-2 py-0.5 rounded mr-1 mb-1" x-text="item.name"></span>
                                </template>
                            </td>
                            <td class="table-cell text-sm" x-text="order.ordered_by?.name ?? (order.ordered_by_staff?.name ?? (order.ordered_by_name ?? '-'))"></td>
                            <td class="table-cell">
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-medium">Dispensed</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function pharmacyDashboard() {
    return {
        orders: @json($orders),

        init() {
            // Auto-refresh so prescriptions from doctors appear without a reload.
            setInterval(() => this.refreshData(), 5000);
        },

        async refreshData() {
            try {
                const res = await fetch('{{ route('web.pharmacy.dashboard') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.orders = data.orders;
                }
            } catch (e) {}
        },

        get pendingOrders() {
            return this.orders.filter(o => o.status === 'ordered' || o.status === 'accepted');
        },

        get dispensedOrders() {
            return this.orders.filter(o => o.status === 'dispensed');
        },

        formatDate(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },

        freqLabel(freq) {
            const labels = {
                'OD': 'Once daily', 'BD': 'Twice daily', 'TDS': 'Thrice daily', 'QID': 'Four times daily',
                'HS': 'At bedtime', 'SOS': 'As needed', 'STAT': 'Immediately',
                'QD': 'Once daily', 'BID': 'Twice daily', 'TID': 'Thrice daily',
            };
            return freq ? ' - ' + (labels[freq] || freq) : '';
        },

        async dispense(id) {
            if (!confirm('Mark as dispensed?')) return;
            const res = await fetch('/pharmacy/' + id + '/dispense', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
            });
            if (res.ok) window.location.reload();
        }
    }
}
</script>
@endsection
