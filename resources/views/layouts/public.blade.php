<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Book Appointment') - MedOS</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 antialiased" x-data>

    <header class="bg-white border-b border-slate-200 px-4 py-3 sticky top-0 z-10">
        <div class="max-w-2xl mx-auto flex items-center gap-3">
            <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
            </div>
            <div class="min-w-0">
                <h1 class="text-base font-bold text-slate-800 truncate">@yield('brand', 'Book an Appointment')</h1>
                <p class="text-xs text-slate-500 truncate">@yield('subtitle', '')</p>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto px-4 py-6">
        @yield('content')
    </main>

    <p class="text-center text-slate-300 py-3" style="font-size:11px">Powered by MedOS</p>

    @stack('scripts')
</body>
</html>
