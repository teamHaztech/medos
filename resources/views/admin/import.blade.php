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

    @if(session('import_credentials') && count(session('import_credentials')))
    <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4">
        <p class="text-sm font-semibold text-green-800 mb-2">Login credentials for {{ count(session('import_credentials')) }} new staff — copy &amp; share securely (they should change on first login):</p>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead><tr class="text-left text-green-700"><th class="pr-4 pb-1">Name</th><th class="pr-4 pb-1">Email (login)</th><th class="pb-1">Temp password</th></tr></thead>
                <tbody class="text-green-800 font-mono">
                    @foreach(session('import_credentials') as $cred)
                    <tr><td class="pr-4 py-0.5">{{ $cred['name'] }}</td><td class="pr-4 py-0.5">{{ $cred['email'] }}</td><td class="py-0.5">{{ $cred['password'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
                'patients'       => ['title' => 'Patients', 'desc' => 'name, phone (required) + demographics, blood group, health ID.'],
                'staff'          => ['title' => 'Staff / Users', 'desc' => 'name, email, role=doctor/nurse/receptionist/pharmacist/lab_tech/billing_staff/… (required). A login is created per row; passwords are shown after import.'],
                'medicines'      => ['title' => 'Medicines', 'desc' => 'name (required) + generic name, category, dosage, form. The pharmacy catalogue.'],
                'pharmacy_stock' => ['title' => 'Pharmacy Stock', 'desc' => 'medicine_name, batch_number, expiry_date, quantity (required) + purchase/selling price, supplier. Medicine must already exist.'],
                'tests'          => ['title' => 'Lab Tests / Imaging', 'desc' => 'name, type=lab/imaging/procedure (required) + category, price, turnaround.'],
                'services'       => ['title' => 'Services / Rate Card', 'desc' => 'name, category, price (required) + code, gst_rate, hsn_sac. The billing service master.'],
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
        a <code>+91</code> prefix automatically. Dates use <code>YYYY-MM-DD</code>. Duplicates are skipped, not
        overwritten — patients by phone, staff by email, medicines/tests/services by name, stock by medicine+batch.
        <strong>Staff</strong> rows create a login each (temp password shown above after import; add a
        <code>password</code> column to set your own). <strong>Pharmacy Stock</strong> needs the medicine to exist
        in the catalogue already (import Medicines first).</p>
    </div>

</div>
@endsection
