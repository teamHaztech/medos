@extends('layouts.public')
@section('title', 'Complete your details')
@section('brand', $hospital->name ?? 'Your details')
@section('subtitle', 'Help us keep your records accurate')

@section('content')

@if($saved)
    <div class="mb-4 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm flex items-start gap-2">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span><strong>Thank you!</strong> Your details have been saved. You can close this page — or update anything else below.</span>
    </div>
@endif

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">

    {{-- Greeting --}}
    <div class="px-5 pt-5">
        <h2 class="text-lg font-bold text-slate-900">Hello {{ \Illuminate\Support\Str::of($patient->name)->explode(' ')->first() }} 👋</h2>
        <p class="text-sm text-slate-500 mt-1">
            Please complete your details so {{ $hospital->name ?? 'the hospital' }} has the right information for your care.
            Everything is optional — fill in what you're comfortable sharing.
        </p>
    </div>

    {{-- Completeness meter --}}
    <div class="px-5 pt-4">
        @if($completeness['percent'] >= 100)
            <div class="px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm font-medium">
                🎉 Your profile is complete. Thank you!
            </div>
        @else
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-semibold text-slate-600">Profile {{ $completeness['percent'] }}% complete</span>
                <span class="text-xs text-slate-400">{{ $completeness['filled'] }} of {{ $completeness['total'] }} done</span>
            </div>
            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-blue-600 rounded-full transition-all" style="width: {{ max(4, $completeness['percent']) }}%"></div>
            </div>
            @if(!empty($completeness['missing']))
            <div class="mt-3 flex flex-wrap gap-1.5">
                <span class="text-xs text-slate-400 mr-0.5">Still needed:</span>
                @foreach($completeness['missing'] as $m)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200">{{ $m }}</span>
                @endforeach
            </div>
            @endif
        @endif
    </div>

    {{-- Registered mobile --}}
    <div class="px-5 pt-4">
        <div class="px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-500">
            Registered mobile: <span class="font-semibold text-slate-700">{{ $patient->phone }}</span>
            <span class="block mt-0.5">To change your phone number, please contact the hospital.</span>
        </div>
    </div>

    <form method="POST" action="{{ $updateUrl }}" class="px-5 py-5 mt-1 space-y-4">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                <select name="gender" class="input-field">
                    <option value="">Prefer not to say</option>
                    @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l)
                        <option value="{{ $k }}" {{ old('gender', $patient->gender) === $k ? 'selected' : '' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Date of birth</label>
                <input type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($patient->date_of_birth)->format('Y-m-d')) }}" class="input-field">
                @if(! $patient->date_of_birth && $patient->age_approximate)
                    <p class="text-xs text-slate-400 mt-1">We have your age as about {{ $patient->age_approximate }}.</p>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Blood group</label>
                <select name="blood_group" class="input-field">
                    <option value="">Not known</option>
                    @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)
                        <option value="{{ $bg }}" {{ old('blood_group', $patient->blood_group) === $bg ? 'selected' : '' }}>{{ $bg }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $patient->email) }}" class="input-field" placeholder="you@example.com">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
            <input type="text" name="city" value="{{ old('city', $patient->city) }}" class="input-field" placeholder="e.g. {{ $hospital->city ?? 'Panaji' }}">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
            <textarea name="address" rows="2" class="input-field" placeholder="House / street / area">{{ old('address', $patient->address) }}</textarea>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <p class="text-sm font-semibold text-slate-700 mb-1">Emergency contact</p>
            <p class="text-xs text-slate-400 mb-3">Someone we can call if you need urgent help.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                    <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $patient->emergency_contact_name) }}" class="input-field" placeholder="e.g. Spouse / parent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="tel" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $patient->emergency_contact_phone) }}" class="input-field" placeholder="Mobile number">
                </div>
            </div>
        </div>

        @if(\App\Modules\Core\Services\RegionService::healthIdEnabled())
        <div class="pt-3 border-t border-slate-100">
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ \App\Modules\Core\Services\RegionService::healthIdLabel() }}</label>
            <input type="text" name="abha_number" value="{{ old('abha_number', $patient->abha_number) }}" class="input-field" placeholder="14-digit number (optional)">
            <p class="text-xs text-slate-400 mt-1">Linking it lets your records follow you between hospitals.</p>
        </div>
        @endif

        @if($errors->any())
            <div class="px-4 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <button type="submit" class="btn-primary w-full py-3 text-base">Save my details</button>
        <p class="text-xs text-center text-slate-400 flex items-center justify-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Your information is kept private and used only for your care at {{ $hospital->name ?? 'this hospital' }}.
        </p>
    </form>
</div>
@endsection
