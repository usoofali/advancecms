<?php

use App\Models\Institution;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Module Add-ons')] class extends Component
{
    public array $addonStates = [];

    /** All available addons with label and description. */
    public array $availableAddons = [
        'exam_module' => [
            'label' => 'CBT Examination Module',
            'description' => 'Enables computer-based testing, question management, and result synchronization with Lab Servers.',
            'icon' => 'academic-cap',
            'color' => 'blue',
        ],
        'admission' => [
            'label' => 'Admission Management',
            'description' => 'Enables application forms, applicant tracking, and admission letter generation.',
            'icon' => 'clipboard-document-list',
            'color' => 'green',
        ],
        'attendance' => [
            'label' => 'Attendance Tracking',
            'description' => 'Enables course attendance recording, allowance management, and lecturer payment workflows.',
            'icon' => 'calendar-days',
            'color' => 'amber',
        ],
        'results' => [
            'label' => 'Academic Results',
            'description' => 'Enables result entry, approval workflows, transcript generation, and student result portal.',
            'icon' => 'chart-bar',
            'color' => 'purple',
        ],
        'portal' => [
            'label' => 'Student Portal',
            'description' => 'Enables student self-service: course registration, invoice viewing, and exam card generation.',
            'icon' => 'user-circle',
            'color' => 'indigo',
        ],
    ];

    public function mount(): void
    {
        Gate::authorize('system.manage_addons');

        foreach (Institution::all() as $institution) {
            $this->addonStates[$institution->id] = array_fill_keys(
                array_keys($this->availableAddons),
                false
            );

            foreach (array_keys($this->availableAddons) as $key) {
                $this->addonStates[$institution->id][$key] = $institution->hasAddon($key);
            }
        }
    }

    public function toggleAddon(int $institutionId, string $addonKey): void
    {
        Gate::authorize('system.manage_addons');

        if (! array_key_exists($addonKey, $this->availableAddons)) {
            return;
        }

        $institution = Institution::findOrFail($institutionId);
        $addons = is_array($institution->addons) ? $institution->addons : [];

        if (in_array($addonKey, $addons)) {
            $addons = array_values(array_filter($addons, fn ($a) => $a !== $addonKey));
            $this->addonStates[$institutionId][$addonKey] = false;
        } else {
            $addons[] = $addonKey;
            $this->addonStates[$institutionId][$addonKey] = true;
        }

        $institution->update(['addons' => $addons]);

        $label = $this->availableAddons[$addonKey]['label'];
        $status = $this->addonStates[$institutionId][$addonKey] ? 'enabled' : 'disabled';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "{$label} {$status} for {$institution->name}.",
        ]);
    }

    public function with(): array
    {
        return [
            'institutions' => Institution::orderBy('name')->get(),
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Module Add-ons') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Module Add-ons')"
        :subheading="__('Enable or disable feature modules for each institution.')"
    >
        <div class="space-y-8 max-w-none w-full">

            @if($institutions->isEmpty())
                <flux:callout icon="building-library" color="zinc">
                    <flux:callout.heading>{{ __('No Institutions Found') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('Create at least one institution to manage its add-ons.') }}</flux:callout.text>
                </flux:callout>
            @endif

            @foreach($institutions as $institution)
            <div class="space-y-4">
                {{-- Institution Header --}}
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center flex-shrink-0">
                        @if($institution->logo_path)
                            <img src="{{ $institution->logo_url }}" class="h-full w-full object-cover">
                        @else
                            <flux:icon icon="building-library" class="h-4 w-4 text-zinc-400" />
                        @endif
                    </div>
                    <div>
                        <flux:heading size="sm" weight="semibold">{{ $institution->name }}</flux:heading>
                        <flux:text size="xs" class="text-zinc-400">{{ $institution->acronym }}</flux:text>
                    </div>
                    <flux:spacer />
                    <flux:badge :color="$institution->status === 'active' ? 'green' : 'zinc'" size="sm">
                        {{ ucfirst($institution->status) }}
                    </flux:badge>
                </div>

                {{-- Addon Cards --}}
                <div class="grid grid-cols-1 gap-3">
                    @foreach($availableAddons as $key => $addon)
                    @php
                        $isEnabled = $addonStates[$institution->id][$key] ?? false;
                    @endphp
                    <div
                        class="flex items-start gap-4 p-4 rounded-xl border transition-colors duration-200 {{ $isEnabled ? 'bg-zinc-50 dark:bg-zinc-900/50 border-zinc-200 dark:border-zinc-700' : 'bg-white dark:bg-zinc-900 border-zinc-100 dark:border-zinc-800' }}"
                        wire:key="addon-{{ $institution->id }}-{{ $key }}"
                    >
                        {{-- Icon --}}
                        <div class="p-2 rounded-lg flex-shrink-0 {{ $isEnabled ? 'bg-'.$addon['color'].'-100 dark:bg-'.$addon['color'].'-900/30 text-'.$addon['color'].'-600 dark:text-'.$addon['color'].'-400' : 'bg-zinc-100 dark:bg-zinc-800 text-zinc-400' }}">
                            <flux:icon :icon="$addon['icon']" class="size-5" />
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <flux:text weight="semibold" class="text-zinc-900 dark:text-zinc-100">{{ $addon['label'] }}</flux:text>
                                @if($isEnabled)
                                    <flux:badge size="sm" color="green" inset="top bottom">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </div>
                            <flux:text size="xs" class="text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $addon['description'] }}</flux:text>
                        </div>

                        {{-- Toggle --}}
                        <div class="flex-shrink-0 pt-0.5">
                            <flux:switch
                                :checked="$isEnabled"
                                wire:click="toggleAddon({{ $institution->id }}, '{{ $key }}')"
                                wire:loading.attr="disabled"
                            />
                        </div>
                    </div>
                    @endforeach
                </div>

                @if(!$loop->last)
                    <flux:separator class="my-6" />
                @endif
            </div>
            @endforeach

        </div>
    </x-pages::settings.layout>
</section>
