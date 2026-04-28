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
                        <tr class="hover:bg-slate-50">
                            <td class="table-cell font-medium">{{ $test->name }}</td>
                            <td class="table-cell">{{ $test->category }}</td>
                            <td class="table-cell">₹{{ number_format($test->price, 0) }}</td>
                            <td class="table-cell">{{ $test->turnaround_time ?? '-' }}</td>
                            <td class="table-cell text-xs text-slate-500">{{ $test->instructions ?? '-' }}</td>
                            <td class="table-cell">
                                <form method="POST" action="{{ route('web.admin.tests.delete', $test->id) }}" onsubmit="return confirm('Remove this test?')">
                                    @csrf @method('DELETE')
                                    <button class="text-red-400 hover:text-red-600 text-sm">Remove</button>
                                </form>
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
