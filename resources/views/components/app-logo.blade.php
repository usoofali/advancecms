@props([
'sidebar' => false,
])

@php
    $system_logo = \Illuminate\Support\Facades\Cache::rememberForever('system_logo', function () {
        try {
            return \App\Models\SystemSetting::where('key', 'system_logo')->value('value');
        } catch (\Exception $e) {
            return null;
        }
    });
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ auth()->user()?->institution?->acronym ?? 'SUPER CMS' }}" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-10 items-center justify-center rounded-md overflow-hidden {{ !$system_logo && !auth()->user()?->institution?->logo_path ? 'bg-accent-content text-accent-foreground' : '' }}">
            @if (auth()->user()?->institution?->logo_path)
            <img src="{{ auth()->user()->institution->logo_url }}" class="w-full h-full object-cover">
            @elseif ($system_logo)
            <img src="data:image/png;base64,{{ $system_logo }}" class="w-full h-full object-cover">
            @else
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ auth()->user()?->institution?->acronym ?? 'SUPER CMS' }}" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-10 items-center justify-center rounded-md overflow-hidden {{ !$system_logo && !auth()->user()?->institution?->logo_path ? 'bg-accent-content text-accent-foreground' : '' }}">
            @if (auth()->user()?->institution?->logo_path)
            <img src="{{ auth()->user()->institution->logo_url }}" class="w-full h-full object-cover">
            @elseif ($system_logo)
            <img src="data:image/png;base64,{{ $system_logo }}" class="w-full h-full object-cover">
            @else
            <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif