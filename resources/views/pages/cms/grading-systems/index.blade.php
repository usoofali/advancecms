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

    <flux:card>
        <div class="relative overflow-x-auto">
            <flux:table :paginate="$gradingSystems">
                <flux:table.columns>
                    <flux:table.column>{{ __('System Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Institution') }}</flux:table.column>
                    <flux:table.column>{{ __('Scale Details') }}</flux:table.column>
                    <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($gradingSystems as $system)
                        <flux:table.row :key="$system->id">
                            <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $system->name }}
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                {{ $system->institution->name ?? __('Global / System Default') }}
                            </flux:table.cell>
                            <flux:table.cell class="text-xs font-mono text-zinc-500 max-w-md truncate">
                                @foreach($system->scale as $level)
                                    <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-700 mr-1 mb-1">
                                        {{ $level['grade'] }}: {{ $level['min'] }}+
                                    </span>
                                @endforeach
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                    <flux:menu>
                                        @can('grading_systems.edit')
                                            <flux:menu.item icon="pencil" :href="route('cms.grading-systems.edit', $system)" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endcan
                                        @can('grading_systems.delete')
                                            @can('grading_systems.edit')
                                                <flux:menu.separator />
                                            @endcan
                                            <flux:menu.item icon="trash" variant="danger" x-on:click="$wire.deletingId = {{ $system->id }}; $flux.modal('delete-grading-system').show()">
                                                {{ __('Delete') }}
                                            </flux:menu.item>
                                        @endcan
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No grading systems found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </flux:card>

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
