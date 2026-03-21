<?php

use App\Models\Program;
use App\Models\Department;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Programs')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int|string|null $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        if (!$this->deletingId) return;
        
        $program = Program::find($this->deletingId);
        if ($program) {
            $program->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Program deleted successfully.',
            ]);
        }
        
        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-program');
    }

    public function with(): array
    {
        return [
            'programs' => Program::query()
                ->with('department.institution')
                ->when(auth()->user()->institution_id, fn ($q) => $q->where('institution_id', auth()->user()->institution_id))
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
                <flux:heading size="xl">{{ __('Programs') }}</flux:heading>
                <flux:subheading>{{ __('Manage academic programs') }}</flux:subheading>
            </div>
            <flux:button icon="plus" variant="primary" :href="route('cms.programs.create')" wire:navigate>
                {{ __('Add Program') }}
            </flux:button>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search programs...')" class="max-w-sm" />

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Program Name') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Acronym') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Duration') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Award') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($programs as $program)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $program->id }}">
                            <td class="px-4 py-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $program->name }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 font-mono">
                                {{ $program->acronym ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $program->department->name }}
                                <div class="text-xs text-zinc-500 mt-0.5">{{ $program->department->institution->name }}</div>
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $program->duration_years }} {{ __('Years') }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ ucfirst($program->award_type) }}
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <flux:badge :color="$program->status === 'active' ? 'green' : 'zinc'" size="sm">
                                    {{ ucfirst($program->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil" :href="route('cms.programs.edit', $program)" wire:navigate />
                                    <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$wire.deletingId = {{ $program->id }}; $flux.modal('delete-program').show()" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No programs found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $programs->links() }}</div>

        <flux:modal name="delete-program" variant="filled" class="min-w-[22rem]">
            <form wire:submit="confirmDelete" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete Program?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. All student registrations and courses associated with this program will be affected.') }}
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
