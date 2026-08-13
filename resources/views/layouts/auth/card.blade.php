<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen theme-textured-bg antialiased text-zinc-900 dark:text-zinc-100">
    <div class="relative z-10 flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-md flex-col gap-6">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                <span class="flex items-center justify-center rounded-md">
                    <x-app-logo-icon class="h-22 w-auto max-w-[280px] fill-current text-black dark:text-white" />
                </span>

                <span class="text-xl font-semibold text-center dark:text-white">{{ config('app.name', 'Laravel') }}</span>
            </a>

            <div class="flex flex-col gap-6">
                <div
                    class="rounded-xl border bg-white/95 dark:bg-zinc-900/90 dark:border-zinc-800/80 text-zinc-800 dark:text-zinc-200 shadow-xl backdrop-blur-md">
                    <div class="px-10 py-8">{{ $slot }}</div>
                </div>
            </div>
        </div>
    </div>
    @fluxScripts
</body>

</html>