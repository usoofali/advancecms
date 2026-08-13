<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen theme-textured-bg font-sans antialiased text-zinc-900 dark:text-zinc-100">
    {{ $slot }}

    {{-- @include('components.partials.flash-alerts') --}}
    @include('components.partials.livewire-notify-alerts')
    @stack('scripts')
    @fluxScripts
</body>
</html>
