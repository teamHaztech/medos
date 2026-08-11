@extends('layouts.auth')

@section('title', 'Change Password')

@section('content')
<div>
    @if($forced)
    <div class="mb-5 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
        For your security, please set a new password before continuing. Your account was created or reset with a temporary password.
    </div>
    @else
    <p class="text-sm text-slate-500 mb-5">Update your account password. Choose something only you know.</p>
    @endif

    @if(session('success'))<div class="mb-4 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-800 text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-5">
        @csrf

        <div>
            <label for="current_password" class="block text-sm font-medium text-slate-700 mb-1">Current password</label>
            <input type="password" id="current_password" name="current_password" required autofocus autocomplete="current-password" class="input-field" placeholder="Your current / temporary password">
            @error('current_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">New password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" class="input-field" placeholder="At least 8 characters">
            <p class="text-xs text-slate-400 mt-1">Minimum 8 characters, with upper &amp; lower case letters and a number.</p>
            @error('password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" class="input-field" placeholder="Re-enter the new password">
        </div>

        <button type="submit" class="btn-primary w-full py-2.5">Update Password</button>
    </form>

    @unless($forced)
    <div class="mt-5 pt-4 border-t border-slate-200 text-center">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
    </div>
    @endunless

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs text-slate-400 hover:text-slate-600">Sign out</button>
    </form>
</div>
@endsection
