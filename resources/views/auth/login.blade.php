@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<div x-data="{ email: '{{ old('email') }}', password: '' }">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Email address</label>
            <input type="email" id="email" name="email" x-model="email" required autofocus autocomplete="email" class="input-field" placeholder="you@haztech.in">
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Password</label>
            <input type="password" id="password" name="password" x-model="password" required autocomplete="current-password" class="input-field" placeholder="Enter your password">
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-slate-600">Remember me</span>
            </label>
        </div>

        <button type="submit" class="btn-primary w-full py-2.5">
            Sign In
        </button>
    </form>

    {{-- Quick login buttons --}}
    <div class="mt-6 pt-5 border-t border-slate-200">
        <p class="text-xs text-slate-400 text-center mb-3">Quick Login</p>
        <div class="space-y-2">
            <button @click="email='superadmin@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-red-50 border border-slate-200 hover:border-red-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center text-xs font-bold">S</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Super Admin</p>
                    <p class="text-xs text-slate-500">superadmin@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-red-100 text-red-700 text-xs font-medium rounded">Super Admin</span>
            </button>

            <button @click="email='admin@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-blue-50 border border-slate-200 hover:border-blue-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center text-xs font-bold">A</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Admin</p>
                    <p class="text-xs text-slate-500">admin@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-medium rounded">Hospital Admin</span>
            </button>

            <button @click="email='priya@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-green-50 border border-slate-200 hover:border-green-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-green-500 text-white rounded-full flex items-center justify-center text-xs font-bold">P</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Dr. Priya Sharma</p>
                    <p class="text-xs text-slate-500">priya@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded">Pediatrics</span>
            </button>

            <button @click="email='amit@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-purple-50 border border-slate-200 hover:border-purple-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-purple-500 text-white rounded-full flex items-center justify-center text-xs font-bold">A</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Dr. Amit Patel</p>
                    <p class="text-xs text-slate-500">amit@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-purple-100 text-purple-700 text-xs font-medium rounded">Cardiology</span>
            </button>

            <button @click="email='rajesh.kumar@city-care.medos.local'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-green-50 border border-slate-200 hover:border-green-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-green-600 text-white rounded-full flex items-center justify-center text-xs font-bold">R</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Dr. Rajesh Kumar</p>
                    <p class="text-xs text-slate-500">rajesh.kumar@city-care.medos.local</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded">General Medicine</span>
            </button>

            <button @click="email='lab@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-amber-50 border border-slate-200 hover:border-amber-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-amber-500 text-white rounded-full flex items-center justify-center text-xs font-bold">L</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Lab Technician</p>
                    <p class="text-xs text-slate-500">lab@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-amber-100 text-amber-700 text-xs font-medium rounded">Pathology</span>
            </button>

            <button @click="email='pharmacy@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-teal-50 border border-slate-200 hover:border-teal-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-teal-500 text-white rounded-full flex items-center justify-center text-xs font-bold">P</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Pharmacist</p>
                    <p class="text-xs text-slate-500">pharmacy@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-teal-100 text-teal-700 text-xs font-medium rounded">Pharmacy</span>
            </button>

            <button @click="email='reception@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-indigo-50 border border-slate-200 hover:border-indigo-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-indigo-500 text-white rounded-full flex items-center justify-center text-xs font-bold">R</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Receptionist</p>
                    <p class="text-xs text-slate-500">reception@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded">Front Office</span>
            </button>

            <button @click="email='nurse@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-rose-50 border border-slate-200 hover:border-rose-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold">N</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Nurse</p>
                    <p class="text-xs text-slate-500">nurse@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-rose-100 text-rose-700 text-xs font-medium rounded">Nursing</span>
            </button>

            <button @click="email='billing@haztech.in'; password='password123'; $nextTick(() => $el.closest('div').previousElementSibling.querySelector('form').submit())"
                class="w-full flex items-center gap-3 px-3 py-2.5 bg-slate-50 hover:bg-cyan-50 border border-slate-200 hover:border-cyan-300 rounded-lg transition-all text-left">
                <div class="w-8 h-8 bg-cyan-500 text-white rounded-full flex items-center justify-center text-xs font-bold">B</div>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Billing Staff</p>
                    <p class="text-xs text-slate-500">billing@haztech.in</p>
                </div>
                <span class="ml-auto px-2 py-0.5 bg-cyan-100 text-cyan-700 text-xs font-medium rounded">Billing</span>
            </button>
        </div>
    </div>
</div>
@endsection
