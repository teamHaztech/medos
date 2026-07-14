<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - MedOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased" x-data="{ sidebarOpen: false }">

    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false"></div>

    {{-- Sidebar --}}
    <aside
        x-bind:class="sidebarOpen ? '' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-800 transition-transform duration-200 ease-in-out flex flex-col lg:!translate-x-0"
    >
        {{-- Brand --}}
        <div class="flex items-center gap-3 px-6 py-5 border-b border-slate-700">
            <div class="w-9 h-9 bg-blue-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <div>
                <span class="text-lg font-bold text-white">MedOS</span>
                <span class="block text-xs text-slate-400">Hospital OS</span>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto"
             x-data
             x-init="$el.scrollTop = parseInt(localStorage.getItem('sidebar_scroll') || 0)"
             @click="setTimeout(() => localStorage.setItem('sidebar_scroll', $el.scrollTop), 50)"
        >
            @include('layouts._sidebar_nav')
        </nav>

        {{-- Hospital Info (all users) / Switcher (super admin only) --}}
        @php
            $currentHospital = auth()->user()?->hospital;
            $region = \App\Modules\Core\Services\RegionService::get($currentHospital?->country);
            $userRole = is_object(auth()->user()?->role) ? auth()->user()->role->value : (auth()->user()?->role ?? '');
            $isSuperAdmin = $userRole === 'super_admin';
        @endphp
        <div class="px-3 py-2 border-t border-slate-700" x-data="{ open: false }">
            @if($isSuperAdmin)
            {{-- Super admin operates at the platform level — manage hospitals from the dashboard --}}
            <a href="{{ route('web.superadmin.index') }}" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-700 transition-colors">
                <span class="text-lg">🏥</span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-white truncate">All Hospitals</p>
                    <p class="text-[10px] text-slate-400">Super Admin · Platform</p>
                </div>
            </a>
            @else
            {{-- Non-super admins see their hospital (read only, no switcher) --}}
            <div class="flex items-center gap-2 px-3 py-2">
                <span class="text-lg">{{ $currentHospital?->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                <div class="min-w-0">
                    <p class="text-xs font-medium text-white truncate">{{ $currentHospital?->name ?? 'Hospital' }}</p>
                    <p class="text-[10px] text-slate-400">{{ $currentHospital?->city }}</p>
                </div>
            </div>
            @endif
        </div>

        {{-- Sidebar footer --}}
        <div class="px-4 py-3 border-t border-slate-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-slate-600 rounded-full flex items-center justify-center text-sm font-medium text-white">
                    {{ substr(auth()->user()?->name ?? 'U', 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()?->name ?? 'User' }}</p>
                    <p class="text-xs text-slate-400 truncate">{{ auth()->user()?->role?->label() ?? 'Staff' }}</p>
                </div>
            </div>
            <p class="text-[10px] text-slate-600 mt-2 text-center">MedOS v{{ config('medos.version') }}</p>
        </div>
    </aside>

    {{-- Main content area --}}
    <div class="lg:ml-64 flex flex-col min-h-screen">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 bg-white border-b border-slate-200 px-4 sm:px-6 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    {{-- Mobile hamburger --}}
                    <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-800">@yield('page-title', 'Dashboard')</h1>
                </div>

                <div class="flex items-center gap-4">
                    {{-- Doctor/Staff name --}}
                    <span class="hidden sm:inline-block text-sm font-medium text-slate-700">{{ auth()->user()?->name ?? 'User' }}</span>

                    {{-- Hospital + Region (Super Admin is platform-level, not tied to a hospital) --}}
                    @php $topRole = is_object(auth()->user()?->role) ? auth()->user()->role->value : (auth()->user()?->role ?? ''); @endphp
                    <span class="hidden md:inline-flex items-center gap-1.5 text-sm text-slate-400">
                        @if($topRole === 'super_admin')
                            <span>🏥</span> All Hospitals
                        @else
                            <span>{{ auth()->user()?->hospital?->country === 'AE' ? '🇦🇪' : '🇮🇳' }}</span>
                            {{ auth()->user()?->hospital?->name ?? 'MedOS Hospital' }}
                        @endif
                    </span>

                    {{-- Role badge --}}
                    <span class="hidden sm:inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                        {{ auth()->user()?->role?->label() ?? 'Staff' }}
                    </span>

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
             class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
            <button @click="show = false" class="text-green-500 hover:text-green-700">&times;</button>
        </div>
        @endif

        @if(session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
             class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-sm">{{ session('error') }}</span>
            </div>
            <button @click="show = false" class="text-red-500 hover:text-red-700">&times;</button>
        </div>
        @endif

        @if($errors->any())
        <div x-data="{ show: true }" x-show="show" x-transition
             class="mx-4 sm:mx-6 mt-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 p-4 sm:p-6">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
