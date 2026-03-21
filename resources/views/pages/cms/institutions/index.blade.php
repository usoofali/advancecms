<?php

use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Institutions')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public int|string|null $deletingId = null;

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            abort(403, 'Unauthorized. Only Super Admins can manage institutions.');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $institution = Institution::find($this->deletingId);
        if ($institution) {
            $institution->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Institution deleted successfully.',
            ]);
        }

        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-institution');
    }

    public function with(): array
    {
        return [
            'institutions' => Institution::query()
                ->when(auth()->user()->institution_id, fn ($q) => $q->where('id', auth()->user()->institution_id))
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('acronym', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(15),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Institutions') }}</flux:heading>
                <flux:subheading>{{ __('Manage academic institutions') }}</flux:subheading>
            </div>
            <flux:button icon="plus" variant="primary" :href="route('cms.institutions.create')" wire:navigate>
                {{ __('Add Institution') }}
            </flux:button>
        </div>

        <div class="flex items-center gap-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search institutions...')" class="max-w-sm" />
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Acronym') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Email') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($institutions as $institution)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $institution->id }}">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 flex-shrink-0 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden flex items-center justify-center">
                                        @if ($institution->logo_path)
                                            <img src="{{ $institution->logo_url }}" class="h-full w-full object-cover">
                                        @else
                                            <flux:icon icon="building-library" class="h-5 w-5 text-zinc-400" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $institution->name }}</div>
                                        @if ($institution->address)
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $institution->address }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $institution->acronym ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $institution->email ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <flux:badge :color="$institution->status === 'active' ? 'green' : 'zinc'" size="sm">
                                    {{ ucfirst($institution->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil" :href="route('cms.institutions.edit', $institution)" wire:navigate />
                                    <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$wire.deletingId = {{ $institution->id }}; $flux.modal('delete-institution').show()" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ $search ? __('No institutions found matching your search.') : __('No institutions yet. Add one to get started.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $institutions->links() }}
        </div>

        <flux:modal name="delete-institution" variant="filled" class="min-w-[22rem]">
            <form wire:submit="confirmDelete" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete Institution?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. All departments and programs under this institution will be affected.') }}
                    </flux:subheading>
                </div>

                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                </div>
            </form>
        </flux:modal>
</div>
