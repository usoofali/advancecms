@props([
    'variant' => 'success',
    'title' => null,
    'dismissible' => true,
    'timeout' => 5000,
])

@php
    $safeVariant = in_array($variant, ['success', 'error', 'warning', 'info']) ? $variant : 'info';

    $classes = [
        'success' => 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-400',
        'error' => 'bg-rose-50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-400',
        'warning' => 'bg-amber-50 dark:bg-amber-950/20 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-400',
        'info' => 'bg-sky-50 dark:bg-sky-950/20 border-sky-200 dark:border-sky-800 text-sky-800 dark:text-sky-400',
    ][$safeVariant];

    $iconClasses = [
        'success' => 'text-emerald-500 dark:text-emerald-400',
        'error' => 'text-rose-500 dark:text-rose-400',
        'warning' => 'text-amber-500 dark:text-amber-400',
        'info' => 'text-sky-500 dark:text-sky-400',
    ][$safeVariant];

    $defaultTitles = [
        'success' => __('Success!'),
        'error' => __('Error!'),
        'warning' => __('Warning!'),
        'info' => __('Information'),
    ];

    $displayTitle = $title ?? $defaultTitles[$safeVariant];
@endphp

<div
    x-data="{ show: true }"
    x-init="@if($timeout > 0) setTimeout(() => show = false, {{ $timeout }}) @endif"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:translate-x-4"
    x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
    @class([
        'pointer-events-auto flex w-full max-w-sm overflow-hidden rounded-xl border p-4 shadow-lg ring-1 ring-black/5 dark:ring-white/10',
        $classes
    ])
    role="alert"
    {{ $attributes }}
>
    <div class="flex-shrink-0">
        @if($safeVariant === 'success')
            <flux:icon.check-circle variant="mini" @class(['size-5', $iconClasses]) />
        @elseif($safeVariant === 'error')
            <flux:icon.x-circle variant="mini" @class(['size-5', $iconClasses]) />
        @elseif($safeVariant === 'warning')
            <flux:icon.exclamation-triangle variant="mini" @class(['size-5', $iconClasses]) />
        @else
            <flux:icon.information-circle variant="mini" @class(['size-5', $iconClasses]) />
        @endif
    </div>

    <div class="ml-3 flex-1">
        <h3 class="text-sm font-bold leading-5">
            {{ $displayTitle }}
        </h3>
        <div class="mt-1 text-xs leading-5 opacity-90">
            {{ $slot }}
        </div>
    </div>

    @if($dismissible)
        <div class="ml-4 flex flex-shrink-0">
            <button
                type="button"
                @click="show = false"
                class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors opacity-50 hover:opacity-100"
            >
                <span class="sr-only">{{ __('Dismiss') }}</span>
                <flux:icon.x-mark variant="mini" class="size-4" />
            </button>
        </div>
    @endif

    {{-- Progress bar for timeout --}}
    @if($timeout > 0)
        <div class="absolute bottom-0 left-0 h-0.5 bg-black/10 dark:bg-white/10 w-full overflow-hidden">
            <div
                class="h-full bg-current opacity-30"
                x-init="$el.style.transition = 'width {{ $timeout }}ms linear'; $el.style.width = '100%'; setTimeout(() => $el.style.width = '0%', 10)"
                style="width: 100%"
            ></div>
        </div>
    @endif
</div>