<?php

use App\Models\Department;
use App\Models\Institution;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Departments')] class extends Component {
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
        
        $department = Department::find($this->deletingId);
        if ($department) {
            $department->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Department deleted successfully.',
            ]);
        }
        
        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-department');
    }

    public function with(): array
    {
        return [
            'departments' => Department::query()
                ->with(['institution', 'hod'])
                ->when(auth()->user()->institution_id, fn ($q) => $q->where('institution_id', auth()->user()->institution_id))
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('faculty', 'like', "%{$this->search}%"))
                ->latest()
                ->paginate(15),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Departments') }}</flux:heading>
                <flux:subheading>{{ __('Manage academic departments') }}</flux:subheading>
            </div>
            <flux:button icon="plus" variant="primary" :href="route('cms.departments.create')" wire:navigate>
                {{ __('Add Department') }}
            </flux:button>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search departments...')" class="max-w-sm" />

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Faculty') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Institution') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Head Of Dept') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($departments as $department)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $department->id }}">
                            <td class="px-4 py-4 font-medium text-zinc-900 dark:text-zinc-100">
                                {{ $department->name }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $department->faculty ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $department->institution->name }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                @if ($department->hod)
                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $department->hod->first_name }} {{ $department->hod->last_name }}</div>
                                @else
                                    <span class="text-zinc-400 italic text-xs">{{ __('Not assigned') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm">
                                <flux:badge :color="$department->status === 'active' ? 'green' : 'zinc'" size="sm">
                                    {{ ucfirst($department->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil" :href="route('cms.departments.edit', $department)" wire:navigate />
                                    <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$wire.deletingId = {{ $department->id }}; $flux.modal('delete-department').show()" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No departments found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $departments->links() }}</div>

        <flux:modal name="delete-department" variant="filled" class="min-w-[22rem]">
            <form wire:submit="confirmDelete" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Delete Department?') }}</flux:heading>
                    <flux:subheading>
                        {{ __('This action cannot be undone. All programs under this department will be affected.') }}
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
