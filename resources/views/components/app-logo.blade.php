@props([
'sidebar' => false,
])

@if($sidebar)
<flux:sidebar.brand name="{{ auth()->user()?->institution?->acronym ?? 'SUPER CMS' }}" {{ $attributes }}>
    <x-slot name="logo"
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
        @if (auth()->user()?->institution?->logo_path)
        <img src="{{ auth()->user()->institution->logo_url }}" class="w-full h-full object-cover">
        @else
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        @endif
    </x-slot>
</flux:sidebar.brand>
@else
<flux:brand name="{{ auth()->user()?->institution?->acronym ?? 'SUPER CMS' }}" {{ $attributes }}>
    <x-slot name="logo"
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
        @if (auth()->user()?->institution?->logo_path)
        <img src="{{ auth()->user()->institution->logo_url }}" class="w-full h-full object-cover">
        @else
        <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
        @endif
    </x-slot>
</flux:brand>
@endif