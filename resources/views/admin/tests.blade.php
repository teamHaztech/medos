@extends('layouts.app')
@section('title', 'Manage Tests')
@section('page-title', 'Tests & Imaging')

@section('content')
<div x-data="{ showAdd: false }">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-slate-500">{{ $tests->count() }} tests available</p>
        <button @click="showAdd = !showAdd" class="btn-primary">+ Add Test</button>
    </div>

    <template x-if="showAdd">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4">
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
    </template>

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
                            <th class="table-header w-16"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($typeTests as $test)
                        <tr class="hover:bg-slate-50" x-data="{ editing: false }">
                            <template x-if="!editing">
                                <td class="table-cell font-medium" @dblclick="editing = true">{{ $test->name }}</td>
                            </template>
                            <template x-if="editing">
                                <td class="table-cell" colspan="5">
                                    <form method="POST" action="{{ route('web.admin.tests.update', $test->id) }}" class="flex items-center gap-2 flex-wrap">
                                        @csrf @method('PUT')
                                        <input type="text" name="name" value="{{ $test->name }}" required class="input-field w-40" placeholder="Name">
                                        <input type="number" name="price" value="{{ $test->price }}" step="0.01" class="input-field w-24" placeholder="Price">
                                        <input type="text" name="turnaround_time" value="{{ $test->turnaround_time }}" class="input-field w-32" placeholder="Turnaround">
                                        <input type="text" name="instructions" value="{{ $test->instructions }}" class="input-field w-48" placeholder="Instructions">
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium">Save</button>
                                        <button type="button" @click="editing = false" class="text-slate-400 hover:text-slate-600 text-sm">Cancel</button>
                                    </form>
                                </td>
                            </template>
                            <template x-if="!editing">
                                <td class="table-cell">{{ $test->category }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="table-cell">{{ \App\Modules\Core\Services\RegionService::currency() }}{{ number_format($test->price, 0) }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="table-cell">{{ $test->turnaround_time ?? '-' }}</td>
                            </template>
                            <template x-if="!editing">
                                <td class="table-cell text-xs text-slate-500">{{ $test->instructions ?? '-' }}</td>
                            </template>
                            <td class="table-cell" x-show="!editing">
                                <div class="flex items-center gap-2">
                                    <button @click="editing = true" class="text-blue-400 hover:text-blue-600 text-sm">Edit</button>
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
</div>
@endsection
