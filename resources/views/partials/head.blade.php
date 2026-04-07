<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title . ' - ' . config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ config('theme.favicon_ico') }}" sizes="any">
<link rel="icon" href="{{ config('theme.favicon_svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ config('theme.apple_touch_icon') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@if (file_exists(public_path('build/manifest.json')))
    <link rel="stylesheet" href="{{ asset('build/assets/app-Cnqnnf2g.css') }}">
    <script type="module" src="{{ asset('build/assets/app-l0sNRNKZ.js') }}"></script>
@else
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif
@fluxAppearance
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