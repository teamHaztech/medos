@extends('layouts.app')
@section('title', 'Register Patient')
@section('page-title', 'Register Patient')

@section('content')
<div class="max-w-3xl">
    <a href="{{ route('web.admin.patients') }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; Back to patients</a>

    @if(session('error'))<div class="my-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="my-4 px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('web.admin.patients.store') }}" class="mt-3">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            @include('admin._patient_fields')
            <div class="flex items-center gap-3 pt-6 mt-6 border-t border-slate-100">
                <button type="submit" class="btn-primary px-6">Register Patient</button>
                <a href="{{ route('web.admin.patients') }}" class="btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
</div>
@endsection
