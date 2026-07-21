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
    <div id="public-website-app"></div>
</body>
</html>
