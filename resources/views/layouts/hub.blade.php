<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Central Gateway Portal') }} - Hub Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.app.js'])
    @livewireStyles
</head>

<body
    class="min-h-screen bg-gradient-to-br from-slate-50 via-emerald-50/40 to-teal-50/30 text-slate-800 font-sans antialiased selection:bg-emerald-500 selection:text-white relative">
    
    <!-- Ambient Background Lighting Accent -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-emerald-300/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 w-96 h-96 bg-teal-300/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/4 w-96 h-96 bg-sky-200/20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative z-10 flex flex-col min-h-screen">
        <!-- Hub Top Navigation Bar -->
        <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-slate-200/80 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-gradient-to-tr from-emerald-600 via-teal-600 to-cyan-600 rounded-xl shadow-md shadow-emerald-600/20">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5m0 0h4m-4 0V9a2 2 0 012-2h2a2 2 0 012 2v12m-6 0a2 2 0 002-2v-4a2 2 0 00-2-2h-2a2 2 0 00-2 2v4a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold tracking-tight text-slate-900">Central Gateway Portal</span>
                            <span class="px-2 py-0.5 text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 border border-emerald-300/60 rounded-full">HUB NODE</span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium">Multi-Tenant Gateway & Data Harvesting Engine</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden md:flex items-center gap-2 px-3 py-1.5 bg-emerald-50/80 rounded-lg border border-emerald-200/60 text-xs text-emerald-900 font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Gateway Engine: <strong>Active</strong></span>
                    </div>

                    <form method="POST" action="{{ route('hub.logout') }}">
                        @csrf
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 border border-slate-200 hover:border-rose-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-xs">
                            <svg class="w-4 h-4 text-slate-500 hover:text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Logout Hub</span>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content Canvas -->
        <main class="py-8 flex-1">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>

</html>