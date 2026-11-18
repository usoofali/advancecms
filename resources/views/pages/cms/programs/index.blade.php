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
    public array $scopedDepartmentIds = [];

    public function mount(): void
    {
        Gate::authorize('programs.view');

        $user = auth()->user();

        // 1. Check new polymorphic scoped roles
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', Department::class),
            $user->getScopedModelIds('Academic Secretary', Department::class),
            $user->getScopedModelIds('Exam Officer', Department::class)
        ));

        if (!empty($scopedDeptIds)) {
            $this->scopedDepartmentIds = $scopedDeptIds;
            return;
        }

        // 2. Fallback: Legacy hod_id check
        $staff = \App\Models\Staff::where('email', $user->email)->first();
        if ($staff) {
            $deptIds = Department::where('hod_id', $staff->id)->pluck('id')->toArray();
            if (!empty($deptIds)) {
                $this->scopedDepartmentIds = $deptIds;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        Gate::authorize('programs.delete');

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

    public function toggleResultLock(int $id): void
    {
        Gate::authorize('programs.edit');

        $program = Program::find($id);
        if ($program) {
            $program->update(['results_locked' => !$program->results_locked]);
            $statusText = $program->results_locked ? 'locked' : 'unlocked';
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Program results {$statusText} successfully.",
            ]);
        }
    }

    public function with(): array
    {
        return [
            'programs' => Program::query()
                ->with('department.institution')
                ->when(auth()->user()->institution_id, fn ($q) => $q->where('institution_id', auth()->user()->institution_id))
                ->when(!empty($this->scopedDepartmentIds), fn ($q) => $q->whereIn('department_id', $this->scopedDepartmentIds))
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
            @can('programs.create')
            <flux:button icon="plus" variant="primary" :href="route('cms.programs.create')" wire:navigate>
                {{ __('Add Program') }}
            </flux:button>
            @endcan
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search programs...')" class="max-w-sm" />

        <flux:card>
            <div class="relative overflow-x-auto">
                <flux:table :paginate="$programs">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Program Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Acronym') }}</flux:table.column>
                        <flux:table.column>{{ __('Department') }}</flux:table.column>
                        <flux:table.column>{{ __('Duration') }}</flux:table.column>
                        <flux:table.column>{{ __('Award') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Result Status') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($programs as $program)
                            <flux:table.row :key="$program->id">
                                <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $program->name }}
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400 font-mono">
                                    {{ $program->acronym ?? '—' }}
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                    {{ $program->department->name }}
                                    <div class="text-xs text-zinc-500 mt-0.5">{{ $program->department->institution->name }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                    {{ $program->duration_years }} {{ __('Years') }}
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                                    {{ ucfirst($program->award_type) }}
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$program->status === 'active' ? 'green' : 'zinc'" size="sm">
                                        {{ ucfirst($program->status) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($program->results_locked)
                                        <flux:badge color="red" size="sm" icon="lock-closed">
                                            {{ __('Locked') }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm" icon="lock-open">
                                            {{ __('Available') }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />
                                        <flux:menu>
                                            @can('programs.edit')
                                                <flux:menu.item :icon="$program->results_locked ? 'lock-open' : 'lock-closed'" wire:click="toggleResultLock({{ $program->id }})">
                                                    {{ $program->results_locked ? __('Unlock Results') : __('Lock Results') }}
                                                </flux:menu.item>
                                                <flux:menu.item icon="pencil" :href="route('cms.programs.edit', $program)" wire:navigate>
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                            @endcan
                                            @can('programs.delete')
                                                @can('programs.edit')
                                                    <flux:menu.separator />
                                                @endcan
                                                <flux:menu.item icon="trash" variant="danger" x-on:click="$wire.deletingId = {{ $program->id }}; $flux.modal('delete-program').show()">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            @endcan
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="8" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                                    {{ __('No programs found.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>

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
