<flux:button
    x-data
    x-on:click="$flux.appearance = $flux.dark ? 'light' : 'dark'"
    variant="ghost"
    size="sm"
    square
    aria-label="{{ __('Toggle appearance') }}"
>
    <flux:icon.moon x-show="!$flux.dark" x-cloak class="size-5 text-zinc-600 dark:text-zinc-300" />
    <flux:icon.sun x-show="$flux.dark" x-cloak class="size-5 text-amber-400" />
</flux:button>
