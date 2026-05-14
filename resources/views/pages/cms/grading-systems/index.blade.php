<?php

use App\Models\GradingSystem;
use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Grading Systems')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int|string|null $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('grading_systems.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        Gate::authorize('grading_systems.delete');

        if (!$this->deletingId) return;
        
        $system = GradingSystem::find($this->deletingId);
        if ($system) {
            // Check if any department is using this system
            if ($system->departments()->exists()) {
                $this->dispatch('notify', [
                    'type' => 'danger',
                    'message' => 'Cannot delete: This grading system is currently in use by one or more departments.',
                ]);
            } else {
                $system->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Grading system deleted successfully.',
                ]);
            }
        }
        
        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-grading-system');
    }

    public function with(): array
    {
        return [
            'gradingSystems' => GradingSystem::query()
                ->with(['institution'])
                ->when(auth()->user()->institution_id, function ($q) {
                    $q->where('institution_id', auth()->user()->institution_id)
                      ->orWhereNull('institution_id');
                })
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(15),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Grading Systems') }}</flux:heading>
            <flux:subheading>{{ __('Define and manage academic grading scales') }}</flux:subheading>
        </div>
        @can('grading_systems.create')
        <flux:button icon="plus" variant="primary" :href="route('cms.grading-systems.create')" wire:navigate>
            {{ __('Add System') }}
        </flux:button>
        @endcan
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search systems...')" class="max-w-sm" />

    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('System Name') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Institution') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Scale Details') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($gradingSystems as $system)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $system->id }}">
                        <td class="px-4 py-4 font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $system->name }}
                        </td>
                        <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $system->institution->name ?? __('Global / System Default') }}
                        </td>
                        <td class="px-4 py-4 text-xs font-mono text-zinc-500 max-w-md truncate">
                            @foreach($system->scale as $level)
                                <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 mr-1 mb-1">
                                    {{ $level['grade'] }}: {{ $level['min'] }}+
                                </span>
                            @endforeach
                        </td>
                        <td class="px-4 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('grading_systems.edit')
                                <flux:button size="sm" variant="ghost" icon="pencil" :href="route('cms.grading-systems.edit', $system)" wire:navigate />
                                @endcan
                                @can('grading_systems.delete')
                                <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$wire.deletingId = {{ $system->id }}; $flux.modal('delete-grading-system').show()" />
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No grading systems found.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $gradingSystems->links() }}</div>

    <flux:modal name="delete-grading-system" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Grading System?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This action cannot be undone. You can only delete systems that are not currently assigned to any department.') }}
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
