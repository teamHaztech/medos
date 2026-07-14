@extends('layouts.app')
@section('title', 'Manage Tests')
@section('page-title', 'Tests & Imaging')

@section('content')
<div x-data="{
        showAdd: false,
        editOpen: false,
        edit: { id:'', name:'', type:'lab', category:'', price:'', turnaround_time:'', instructions:'' },
        openEdit(t) { this.edit = Object.assign({}, t); this.editOpen = true; },
        get editAction() { return '{{ url('admin/tests') }}/' + this.edit.id; }
    }">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ $tests->count() }} tests available</p>
        <button @click="showAdd = !showAdd" class="btn-primary">+ Add Test</button>
    </div>

    <div x-show="showAdd" style="display:none" class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
        <form method="POST" action="{{ route('web.admin.tests.store') }}" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @csrf
            <input type="text" name="name" required class="input-field" placeholder="Test name *">
            <select name="type" required class="input-field">
                <option value="lab">Lab</option>
                <option value="imaging">Imaging</option>
                <option value="procedure">Procedure</option>
            </select>
            <input type="text" name="category" class="input-field" placeholder="Category">
            <input type="number" name="price" step="0.01" class="input-field" placeholder="Price (₹)">
            <input type="text" name="turnaround_time" class="input-field" placeholder="Turnaround (e.g. 2 hours)">
            <input type="text" name="instructions" class="input-field col-span-2" placeholder="Instructions (e.g. Fasting required)">
            <button type="submit" class="btn-success">Save Test</button>
        </form>
    </div>

    @foreach(['lab' => 'Lab Tests', 'imaging' => 'Imaging', 'procedure' => 'Procedures'] as $type => $label)
        @php $typeTests = $tests->where('type', $type); @endphp
        @if($typeTests->count())
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-slate-700 mb-2">{{ $label }} ({{ $typeTests->count() }})</h3>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="table-header">Name</th>
                            <th class="table-header">Category</th>
                            <th class="table-header">Price</th>
                            <th class="table-header">Turnaround</th>
                            <th class="table-header">Instructions</th>
                            <th class="table-header w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($typeTests as $test)
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell font-medium">{{ $test->name }}</td>
                            <td class="table-cell">{{ $test->category }}</td>
                            <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($test->price, 0) }}</td>
                            <td class="table-cell">{{ $test->turnaround_time ?? '-' }}</td>
                            <td class="table-cell text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($test->instructions ?? '-', 40) }}</td>
                            <td class="table-cell">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                        @click="openEdit({ id:'{{ $test->id }}', name: @js($test->name), type: @js($test->type), category: @js($test->category ?? ''), price: '{{ $test->price }}', turnaround_time: @js($test->turnaround_time ?? ''), instructions: @js($test->instructions ?? '') })"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-800">Edit</button>
                                    <form method="POST" action="{{ route('web.admin.tests.delete', $test->id) }}" onsubmit="return confirm('Remove this test?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-400 hover:text-red-600 text-sm">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endforeach

    {{-- Edit modal --}}
    <x-modal show="editOpen" title="Edit Test" max="lg">
            <form method="POST" :action="editAction" class="grid grid-cols-2 gap-4">
                @csrf @method('PUT')
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Name *</label>
                    <input type="text" name="name" required x-model="edit.name" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Type</label>
                    <select name="type" x-model="edit.type" class="input-field">
                        <option value="lab">Lab</option>
                        <option value="imaging">Imaging</option>
                        <option value="procedure">Procedure</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Category</label>
                    <input type="text" name="category" x-model="edit.category" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Price (₹)</label>
                    <input type="number" name="price" step="0.01" x-model="edit.price" class="input-field">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Turnaround</label>
                    <input type="text" name="turnaround_time" x-model="edit.turnaround_time" class="input-field">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Instructions</label>
                    <input type="text" name="instructions" x-model="edit.instructions" class="input-field">
                </div>
                <div class="col-span-2 flex items-center gap-3 pt-2">
                    <button type="submit" class="btn-primary px-5 py-2.5">Save Changes</button>
                    <button type="button" @click="editOpen = false" class="text-sm text-slate-500 hover:text-slate-700 px-2 py-2">Cancel</button>
                </div>
            </form>
    </x-modal>
</div>
@endsection
