<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Kiosk') - MedOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 antialiased" x-data>

    {{-- Hospital branding header --}}
    <header class="bg-white border-b border-slate-200 px-6 py-4">
        <div class="flex items-center justify-center gap-4">
            <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </div>
            <div class="text-center">
                @php $kioskHospital = \App\Modules\Core\Models\Hospital::where('is_active', true)->first(); @endphp
                <h1 class="text-2xl font-bold text-slate-800">{{ $kioskHospital?->name ?? 'MedOS Hospital' }}</h1>
                <p class="text-sm text-slate-500">@yield('subtitle', 'Welcome')</p>
            </div>
        </div>
    </header>

    {{-- Full-screen content --}}
    <main class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10" style="min-height: calc(100vh - 88px);">
        @yield('content')
    </main>

    @include('layouts._region_data')
    @stack('scripts')
</body>
</html>
