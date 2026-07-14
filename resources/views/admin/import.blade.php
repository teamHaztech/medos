@extends('layouts.app')
@section('title', 'Import Data')
@section('page-title', 'Import Data')

@section('content')
<div class="max-w-4xl">

    <p class="text-sm text-slate-500 mb-6">
        Bulk-load your hospital's master data from a spreadsheet. Save your Excel sheet as <strong>.csv</strong>,
        download a template to see the exact columns, fill it in, then upload. Rows are validated and duplicates
        are skipped automatically. Everything imports into <strong>{{ auth()->user()?->hospital?->name ?? 'your hospital' }}</strong>.
    </p>

    @if(session('import_errors') && count(session('import_errors')))
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4">
        <p class="text-sm font-semibold text-amber-800 mb-2">Some rows need attention ({{ count(session('import_errors')) }} shown):</p>
        <ul class="text-xs text-amber-700 space-y-0.5 max-h-48 overflow-y-auto">
            @foreach(session('import_errors') as $err)
            <li>• {{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="space-y-4">
        @php
            $cards = [
                'patients'  => ['title' => 'Patients', 'desc' => 'name, phone (required) + demographics, blood group, health ID.', 'accent' => 'blue'],
                'medicines' => ['title' => 'Medicines / Pharmacy', 'desc' => 'name (required) + generic name, category, dosage, form.', 'accent' => 'emerald'],
                'tests'     => ['title' => 'Tests / Imaging', 'desc' => 'name, type=lab/imaging/procedure (required) + category, price, turnaround.', 'accent' => 'violet'],
            ];
        @endphp

        @foreach($cards as $type => $c)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-slate-900">{{ $c['title'] }}</h3>
                    <p class="text-sm text-slate-500 mt-0.5">{{ $c['desc'] }}</p>
                    <a href="{{ route('web.admin.import.template', $type) }}"
                       class="inline-flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900 mt-2 border border-slate-300 rounded-lg px-2.5 py-1">
                        ⤓ Download {{ $c['title'] }} template
                    </a>
                </div>
                <form method="POST" action="{{ route('web.admin.import.run', $type) }}"
                      enctype="multipart/form-data" class="flex items-center gap-2 shrink-0">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required
                           class="text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer">
                    <button type="submit" class="btn-primary text-xs px-3 py-1.5 whitespace-nowrap">Upload &amp; Import</button>
                </form>
            </div>
            @error('file')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>
        @endforeach
    </div>

    <div class="mt-6 text-xs text-slate-400">
        <p>Tips: the first row must be the column headers (as in the template). Phone numbers with 10 digits get
        a <code>+91</code> prefix automatically. Dates use <code>YYYY-MM-DD</code>. Duplicate patients (same phone)
        and existing medicines/tests (same name) are skipped, not overwritten.</p>
    </div>

</div>
@endsection
