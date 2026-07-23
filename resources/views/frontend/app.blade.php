<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Advance CMS') }}</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ config('theme.favicon_ico') }}" sizes="any">
    <link rel="icon" href="{{ config('theme.favicon_svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ config('theme.apple_touch_icon') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/public.js'])
    <style>
        :root {
            --color-accent: {{ config('theme.accent') }};
            --color-accent-content: {{ config('theme.accent_content') }};
            --color-accent-foreground: {{ config('theme.accent_foreground') }};
        }

        .dark {
            --color-accent: {{ config('theme.dark_accent') }};
            --color-accent-content: {{ config('theme.dark_accent_content') }};
            --color-accent-foreground: {{ config('theme.dark_accent_foreground') }};
        }
    </style>
</head>
<body class="antialiased bg-zinc-50 text-zinc-900 font-sans">
    <div id="public-website-app">
        <div class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-950">
            <div class="relative flex flex-col items-center p-8 sm:p-10 rounded-3xl bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl border border-zinc-200/80 dark:border-zinc-800 shadow-2xl max-w-sm w-full mx-4 space-y-6">
                <div class="relative flex items-center justify-center">
                    <div class="w-16 h-16 rounded-2xl bg-zinc-900 text-white dark:bg-white dark:text-zinc-900 flex items-center justify-center font-bold text-3xl shadow-lg animate-pulse">
                        {{ strtoupper(substr(config('app.name', 'A'), 0, 1)) }}
                    </div>
                </div>
                <div class="text-center space-y-1">
                    <h3 class="font-bold text-xl text-zinc-900 dark:text-white tracking-tight">{{ config('app.name', 'CMS') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">Loading workspace...</p>
                </div>
                <div class="w-44 h-1.5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden relative">
                    <div class="h-full bg-zinc-900 dark:bg-white rounded-full animate-pulse w-3/4 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
