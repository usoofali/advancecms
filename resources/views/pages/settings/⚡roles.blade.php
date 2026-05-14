<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\Permission;
use App\Models\Role;
use Flux\Flux;

new #[Title('Role Management')] class extends Component {

    public string $tab = 'roles';

    // Role state
    public bool $showRoleEditModal = false;
    public bool $showRoleDeleteModal = false;
    public bool $showRoleViewModal = false;
    public ?int $roleEditingId = null;
    public string $roleName = '';
    public string $roleDescription = '';
    public array $selectedPermissions = [];
    public ?int $rolePendingDeleteId = null;
    public string $rolePendingDeleteName = '';
    public ?int $viewingRoleId = null;

    public function mount(): void
    {
        Gate::authorize('roles.view');
    }

    #[Computed]
    public function roles()
    {
        return Role::query()->with('permissions')->orderBy('role_name')->get();
    }

    #[Computed]
    public function allPermissions()
    {
        return Permission::query()
            ->orderBy('permission_name')
            ->get()
            ->groupBy(function ($p) {
                $parts = explode('.', $p->permission_name);
                return count($parts) > 1 ? str_replace('_', ' ', $parts[0]) : 'other';
            });
    }

    #[Computed]
    public function viewingRole(): ?Role
    {
        if ($this->viewingRoleId === null) {
            return null;
        }
        return Role::query()->with('permissions')->find($this->viewingRoleId);
    }

    // Role Methods
    public function openRoleViewModal(int $roleId): void
    {
        $this->viewingRoleId = $roleId;
        $this->showRoleViewModal = true;
    }

    public function openRoleCreateModal(): void
    {
        $this->resetRoleEditForm();
        $this->showRoleEditModal = true;
    }

    public function openRoleEditModal(int $roleId): void
    {
        $role = Role::query()->with('permissions')->findOrFail($roleId);
        $this->roleEditingId = $role->role_id;
        $this->roleName = $role->role_name;
        $this->roleDescription = $role->description ?? '';
        $this->selectedPermissions = $role->permissions->pluck('permission_name')->toArray();
        $this->showRoleEditModal = true;
    }

    public function saveRole(): void
    {
        if ($this->roleEditingId) {
            Gate::authorize('roles.edit');
        } else {
            Gate::authorize('roles.create');
        }

        $this->validate([
            'roleName' => 'required|string|max:255|unique:roles,role_name,' . $this->roleEditingId . ',role_id',
            'roleDescription' => 'nullable|string|max:500',
            'selectedPermissions' => 'array',
        ]);

        if ($this->roleEditingId) {
            $role = Role::findOrFail($this->roleEditingId);
            $role->update(['role_name' => $this->roleName, 'description' => $this->roleDescription]);
        } else {
            $role = Role::create(['role_name' => $this->roleName, 'description' => $this->roleDescription]);
        }

        $permissionIds = Permission::whereIn('permission_name', $this->selectedPermissions)->pluck('permission_id')->toArray();
        $role->permissions()->sync($permissionIds);

        $this->showRoleEditModal = false;
        $this->resetRoleEditForm();
        Flux::toast($this->roleEditingId ? __('Role updated') : __('Role created'), variant: 'success');
    }

    public function openRoleDeleteModal(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->rolePendingDeleteId = $role->role_id;
        $this->rolePendingDeleteName = $role->role_name;
        $this->showRoleDeleteModal = true;
    }

    public function deleteRole(): void
    {
        Gate::authorize('roles.delete');

        if ($this->rolePendingDeleteId) {
            $role = Role::findOrFail($this->rolePendingDeleteId);
            $role->delete();
            $this->showRoleDeleteModal = false;
            $this->rolePendingDeleteId = null;
            $this->rolePendingDeleteName = '';
            Flux::toast(__('Role deleted'), variant: 'success');
        }
    }

    private function resetRoleEditForm(): void
    {
        $this->roleEditingId = null;
        $this->roleName = '';
        $this->roleDescription = '';
        $this->selectedPermissions = [];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Role Management') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Roles & Permissions')" :subheading="__('Manage system roles and their associated permissions.')">
        <div class="flex items-center gap-6 border-b border-zinc-200 dark:border-zinc-800 mb-6">
            <button wire:click="$set('tab', 'roles')" class="flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 {{ $tab === 'roles' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon.shield-check class="size-4" />
                {{ __('Roles') }}
            </button>
            <button wire:click="$set('tab', 'permissions')" class="flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2 {{ $tab === 'permissions' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' }}">
                <flux:icon.key class="size-4" />
                {{ __('Permissions') }}
            </button>
        </div>

        @if ($tab === 'roles')
            <div class="space-y-6">
                @can('roles.create')
                <div class="flex justify-end">
                    <flux:button variant="primary" icon="plus" wire:click="openRoleCreateModal">
                        {{ __('Create Role') }}
                    </flux:button>
                </div>
                @endcan

                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                            <tr>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Role') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Permissions') }}</th>
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->roles as $role)
                                <tr wire:key="role-row-{{ $role->role_id }}" class="bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="whitespace-nowrap px-4 py-4 align-middle">
                                        <div class="flex items-center gap-3">
                                            <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                                                <flux:icon.shield-check class="size-4 text-indigo-500" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-zinc-900 dark:text-zinc-100 capitalize">
                                                    {{ str_replace('_', ' ', $role->role_name) }}
                                                </span>
                                                @if($role->description)
                                                    <span class="text-xs text-zinc-500">{{ $role->description }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-middle">
                                        @php $count = $role->permissions->count(); @endphp
                                        @if ($count > 0)
                                            <button type="button"
                                                wire:click="openRoleViewModal({{ $role->role_id }})"
                                                class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-200 transition-colors group">
                                                <flux:icon.key class="size-3.5 opacity-70 group-hover:opacity-100" />
                                                {{ $count }} {{ Str::plural('permission', $count) }}
                                            </button>
                                        @else
                                            <span class="text-zinc-400 italic text-xs">{{ __('No permissions') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-end align-middle">
                                        <div class="flex items-center justify-end gap-1">
                                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="openRoleViewModal({{ $role->role_id }})" :tooltip="__('View Permissions')" />
                                            @can('roles.edit')
                                            <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openRoleEditModal({{ $role->role_id }})" :tooltip="__('Edit Role')" />
                                            @endcan
                                            @if($role->role_name !== 'Super Admin')
                                                @can('roles.delete')
                                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="openRoleDeleteModal({{ $role->role_id }})" :tooltip="__('Delete Role')" />
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($tab === 'permissions')
            <div class="space-y-6">
                <div class="space-y-8">
                    @foreach ($this->allPermissions as $group => $permissions)
                        <div class="space-y-3">
                            <flux:heading size="sm" class="uppercase tracking-wider text-zinc-500 font-bold opacity-75">
                                {{ $group }}
                            </flux:heading>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach ($permissions as $permission)
                                    <div class="group flex items-center justify-between p-3 rounded-lg border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm">
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                            {{ Str::after($permission->permission_name, '.') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </x-pages::settings.layout>

    {{-- View Role Modal --}}
    <flux:modal wire:model.self="showRoleViewModal" class="max-w-xl">
        @if ($this->viewingRole)
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/20 flex items-center justify-center shrink-0">
                        <flux:icon.shield-check class="size-5 text-indigo-500" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="capitalize">{{ str_replace('_', ' ', $this->viewingRole->role_name) }}</flux:heading>
                        <flux:subheading>
                            {{ $this->viewingRole->permissions->count() }} {{ Str::plural('permission', $this->viewingRole->permissions->count()) }} {{ __('assigned') }}
                        </flux:subheading>
                    </div>
                </div>

                @php
                    $grouped = $this->viewingRole->permissions
                        ->groupBy(fn ($p) => str_replace('_', ' ', explode('.', $p->permission_name)[0]))
                        ->sortKeys();
                @endphp

                <div class="space-y-5 max-h-[55vh] overflow-y-auto pr-1 -mr-1">
                    @forelse ($grouped as $group => $permissions)
                        <div class="space-y-2">
                            <flux:text size="xs" class="uppercase tracking-widest font-bold text-zinc-400 dark:text-zinc-500">
                                {{ $group }}
                            </flux:text>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($permissions as $permission)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                                        {{ Str::after($permission->permission_name, '.') }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @if (!$loop->last)
                            <flux:separator />
                        @endif
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                            <flux:icon.shield-exclamation class="size-10 mb-2 opacity-30" />
                            <flux:text size="sm">{{ __('No permissions assigned to this role.') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-zinc-100 dark:border-zinc-800">
                    @can('roles.edit')
                    <flux:button variant="ghost" icon="pencil-square" wire:click="openRoleEditModal({{ $this->viewingRole->role_id }}); $set('showRoleViewModal', false)">
                        {{ __('Edit Role') }}
                    </flux:button>
                    @endcan
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Edit/Create Role Modal --}}
    <flux:modal wire:model.self="showRoleEditModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $roleEditingId ? __('Edit Role') : __('Create Role') }}</flux:heading>
                <flux:subheading>{{ __('Define the role name and select the permissions it should have.') }}</flux:subheading>
            </div>

            <form wire:submit="saveRole" class="space-y-6">
                <flux:input wire:model="roleName" :label="__('Role Name')" placeholder="{{ __('e.g. Manager') }}" required />
                <flux:input wire:model="roleDescription" :label="__('Description')" placeholder="{{ __('Brief description of the role') }}" />

                <div class="space-y-6">
                    @foreach ($this->allPermissions as $group => $permissions)
                        <div class="space-y-3">
                            <flux:heading size="sm" class="uppercase tracking-wider text-zinc-500 font-bold opacity-75">
                                {{ $group }}
                            </flux:heading>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach ($permissions as $permission)
                                    <label class="group flex items-center gap-2 cursor-pointer p-2 rounded-lg border border-zinc-100 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->permission_name }}" class="rounded border-zinc-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-zinc-700 dark:bg-zinc-900 cursor-pointer">
                                        <span class="text-xs text-zinc-700 dark:text-zinc-300 group-hover:text-zinc-900 dark:group-hover:text-white transition-colors">
                                            {{ Str::after($permission->permission_name, '.') }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if (!$loop->last)
                                <flux:separator class="mt-4" />
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Role Modal --}}
    <flux:modal wire:model.self="showRoleDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Role?') }}</flux:heading>
                <flux:subheading>{{ __('Are you sure you want to delete the role ":name"? This action cannot be undone.', ['name' => $rolePendingDeleteName]) }}</flux:subheading>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteRole" wire:loading.attr="disabled">
                    {{ __('Delete Role') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

</section>
