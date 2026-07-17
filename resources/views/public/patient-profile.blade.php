@extends('layouts.public')
@section('title', 'Complete your details')
@section('brand', $hospital->name ?? 'Your details')
@section('subtitle', 'Help us keep your records accurate')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    @if($saved)
        <div class="mb-4 px-4 py-3 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">
            <strong>Thank you!</strong> Your details have been saved. You can close this page — or update anything else below.
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <h2 class="text-lg font-bold text-slate-900">Hello {{ \Illuminate\Support\Str::of($patient->name)->explode(' ')->first() }} 👋</h2>
        <p class="text-sm text-slate-500 mt-1">
            Please complete your details so {{ $hospital->name ?? 'the hospital' }} has the right information for your care.
            Everything is optional — fill in what you're comfortable sharing.
        </p>

        <div class="mt-3 px-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-500">
            Registered mobile: <span class="font-semibold text-slate-700">{{ $patient->phone }}</span>
            <span class="block mt-0.5">To change your phone number, please contact the hospital.</span>
        </div>

        <form method="POST" action="{{ $updateUrl }}" class="mt-5 space-y-4">
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

            <div class="pt-2 border-t border-slate-100">
                <p class="text-sm font-semibold text-slate-700 mb-2">Emergency contact</p>
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
            <div class="pt-2 border-t border-slate-100">
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

            <button type="submit" class="btn-primary w-full py-3">Save my details</button>
            <p class="text-xs text-center text-slate-400">Your information is kept private and used only for your care at {{ $hospital->name ?? 'this hospital' }}.</p>
        </form>
    </div>
</div>
@endsection
