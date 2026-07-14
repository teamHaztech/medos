@extends('layouts.app')
@section('title', 'Housekeeping')
@section('page-title', 'Housekeeping Monitoring')

@php use App\Modules\Housekeeping\Models\HousekeepingLog;
$prCls = ['low'=>'bg-slate-100 text-slate-600','medium'=>'bg-amber-100 text-amber-700','high'=>'bg-red-100 text-red-700'];
$stCls = ['open'=>'bg-blue-100 text-blue-700','in_progress'=>'bg-amber-100 text-amber-700','closed'=>'bg-green-100 text-green-700'];
@endphp

@section('content')
<div x-data="hk()">
    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif

    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Open</p><p class="text-2xl font-bold text-blue-700">{{ $counts['open'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">In progress</p><p class="text-2xl font-bold text-amber-600">{{ $counts['in_progress'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">High priority open</p><p class="text-2xl font-bold text-red-600">{{ $counts['high'] }}</p></div>
        <div class="bg-white rounded-xl border border-slate-200 p-4"><p class="text-xs text-slate-500">Closed</p><p class="text-2xl font-bold text-green-600">{{ $counts['closed'] }}</p></div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-4">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All statuses</option>
                @foreach(HousekeepingLog::STATUSES as $k => $label)<option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
            <select name="priority" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All priority</option>
                @foreach(HousekeepingLog::PRIORITIES as $k => $label)<option value="{{ $k }}" {{ request('priority') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
            <select name="category" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-2 py-1.5 bg-white">
                <option value="">All types</option>
                @foreach(HousekeepingLog::CATEGORIES as $k => $label)<option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach
            </select>
        </form>
        <button type="button" @click="openLog()" class="ml-auto btn-primary">+ Log item</button>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200"><tr>
                    <th class="table-header">Location</th><th class="table-header">Type</th><th class="table-header">Priority</th><th class="table-header">Reported</th><th class="table-header">Status</th><th class="table-header text-right"></th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($logs as $l)
                    <tr>
                        <td class="px-4 py-2.5 text-sm text-slate-800">{{ $l->location }}<span class="block text-xs text-slate-400 truncate max-w-xs">{{ $l->description }}</span></td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ HousekeepingLog::CATEGORIES[$l->category] ?? $l->category }}</td>
                        <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $prCls[$l->priority] ?? 'bg-slate-100' }}">{{ HousekeepingLog::PRIORITIES[$l->priority] ?? $l->priority }}</span></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $l->reported_by_name ?? '' }}<span class="block">{{ optional($l->created_at)->format('M d, H:i') }}</span></td>
                        <td class="px-4 py-2.5"><span class="text-xs px-2 py-0.5 rounded-full {{ $stCls[$l->status] ?? 'bg-slate-100' }}">{{ HousekeepingLog::STATUSES[$l->status] ?? $l->status }}</span></td>
                        <td class="px-4 py-2.5 text-right"><button type="button" @click="openManage({ id: @js($l->id), location: @js($l->location), category: @js(HousekeepingLog::CATEGORIES[$l->category] ?? $l->category), description: @js($l->description), reportedBy: @js($l->reported_by_name ?? ''), status: @js($l->status), priority: @js($l->priority), assigned: @js($l->assigned_to_name ?? ''), closureNotes: @js($l->closure_notes ?? '') })" class="text-xs font-medium text-blue-600 hover:text-blue-800">Manage</button></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">Nothing logged.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Log modal --}}
    <x-modal show="logModal" title="Log Housekeeping Item" max="lg">
        <form method="POST" action="{{ route('web.housekeeping.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Location / Area</label>
                <input type="text" name="location" required maxlength="150" class="input-field" placeholder="e.g. Ward 3 toilet, OT-2 corridor">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                    <select name="category" class="input-field">@foreach(HousekeepingLog::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                    <select name="priority" class="input-field"><option value="medium">Medium</option><option value="low">Low</option><option value="high">High</option></select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="3" required class="input-field" placeholder="What's wrong / missing / not in place…"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="logModal=false" class="btn-secondary text-sm">Cancel</button>
                <button type="submit" class="btn-primary">Log</button>
            </div>
        </form>
    </x-modal>

    {{-- Manage modal --}}
    <x-modal show="manageModal" title-expr="'Item · ' + item.location" max="lg">
        <div class="space-y-3">
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm">
                <p><span class="text-slate-500">Type:</span> <span class="font-medium" x-text="item.category"></span></p>
                <p class="text-slate-700 pt-1" x-text="item.description"></p>
                <p class="text-xs text-slate-400 pt-1">Reported by <span x-text="item.reportedBy || ''"></span></p>
            </div>
            <form method="POST" :action="'/housekeeping/' + item.id" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" x-model="item.status" class="input-field">@foreach(HousekeepingLog::STATUSES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                        <select name="priority" x-model="item.priority" class="input-field">@foreach(HousekeepingLog::PRIORITIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Assigned to</label>
                    <input type="text" name="assigned_to_name" x-model="item.assigned" class="input-field" placeholder="Housekeeping staff">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Closure notes</label>
                    <textarea name="closure_notes" x-model="item.closureNotes" rows="2" class="input-field" placeholder="How it was resolved"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="manageModal=false" class="btn-secondary text-sm">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </x-modal>
</div>

@push('scripts')
<script>
function hk() {
    return {
        logModal: false, manageModal: false,
        item: { id: '', location: '', category: '', description: '', reportedBy: '', status: 'open', priority: 'medium', assigned: '', closureNotes: '' },
        openLog() { this.logModal = true; },
        openManage(i) { this.item = { ...i }; this.manageModal = true; },
    };
}
</script>
@endpush
@endsection
