<?php

use App\Models\Staff;
use App\Models\Department;
use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Staff Management')] class extends Component {
    use WithPagination;

    public string $search = '';
    public int|string $role_id = '';
    public int|string|null $deletingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        if (!$this->deletingId) return;

        $staff = Staff::find($this->deletingId);
        if ($staff) {
            $staff->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Staff record deleted successfully.',
            ]);
        }

        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-staff');
    }

    public function with(): array
    {
        $institution_id = auth()->user()->institution_id;

        return [
            'staffMembers' => Staff::query()
                ->with('role')
                ->when($institution_id, fn($q) => $q->where('institution_id', $institution_id))
                ->when($this->search, function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('staff_number', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
                ->when($this->role_id, fn($q) => $q->where('role_id', $this->role_id))
                ->orderBy('last_name')
                ->paginate(20),
            'roles' => Role::query()
                ->whereNotIn('role_id', [1, 9])
                ->orderBy('role_name')
                ->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Staff Directory') }}</flux:heading>
            <flux:subheading>{{ __('Manage academic and non-academic staff members') }}</flux:subheading>
        </div>
        <flux:button icon="plus" variant="primary" :href="route('cms.staff.create')" wire:navigate>
            {{ __('Add Staff') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search staff...')"
            class="max-w-md" />
        <flux:select wire:model.live="role_id" class="max-w-xs">
            <flux:select.option value="null">{{ __('All Roles') }}</flux:select.option>
            @foreach ($roles as $role)
            <flux:select.option :value="$role->role_id">{{ $role->role_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div
        class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Name') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Staff ID') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Contact') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Role') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Designation') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{
                        __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($staffMembers as $staff)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $staff->id }}">
                    <td class="px-4 py-4">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $staff->first_name }} {{
                            $staff->last_name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 font-mono uppercase">{{
                            $staff->staff_number }}</div>
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 font-mono uppercase">
                        {{ $staff->staff_number }}
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                        <div>{{ $staff->email }}</div>
                        <div class="text-xs">{{ $staff->phone }}</div>
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $staff->role->role_name ?? '—' }}
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 italic">
                        {{ $staff->designation }}
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <flux:badge :color="$staff->status === 'active' ? 'green' : 'zinc'" size="sm">
                            {{ ucfirst($staff->status) }}
                        </flux:badge>
                    </td>
                    <td class="px-4 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil-square"
                                :href="route('cms.staff.edit', $staff->id)" wire:navigate
                                title="{{ __('Edit Staff') }}" />
                            <flux:button size="sm" variant="ghost" icon="trash" title="{{ __('Delete Staff') }}"
                                x-on:click="$wire.deletingId = {{ $staff->id }}; $flux.modal('delete-staff').show()" />
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No staff records found.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $staffMembers->links() }}</div>

    <flux:modal name="delete-staff" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Staff Record?') }}</flux:heading>
                <flux:subheading>
                    {{ __('Are you sure you want to delete this staff record? This action cannot be undone.') }}
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