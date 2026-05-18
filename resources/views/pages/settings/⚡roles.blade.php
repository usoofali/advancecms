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

    public string $search = '';
    
    // Role state
    public bool $showRoleEditModal = false;
    public bool $showRoleDeleteModal = false;
    public bool $showRoleViewModal = false;
    public ?int $roleEditingId = null;
    public string $roleName = '';
    public string $roleDescription = '';
    public $selectedPermissions = [];
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
        return Role::query()
            ->with('permissions')
            ->when($this->search, function($q) {
                $q->where('role_name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->orderBy('role_name')
            ->get();
    }

    #[Computed]
    public function allPermissions()
    {
        return Permission::query()
            ->when($this->search, function($q) {
                $q->where('permission_name', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            })
            ->orderBy('permission_name')
            ->get()
            ->groupBy(function ($p) {
                $parts = explode('.', $p->permission_name);
                return count($parts) > 1 ? str_replace('_', ' ', $parts[0]) : 'other';
            });
    }

    public function toggleGroup(string $group): void
    {
        $groupPermissions = $this->allPermissions[$group]->pluck('permission_name')->toArray();
        $allPresent = collect($groupPermissions)->every(fn($p) => in_array($p, $this->selectedPermissions));

        if ($allPresent) {
            $this->selectedPermissions = array_diff($this->selectedPermissions, $groupPermissions);
        } else {
            $this->selectedPermissions = array_unique(array_merge($this->selectedPermissions, $groupPermissions));
        }
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

    <x-pages::settings.layout :heading="__('Roles & Permissions')" :subheading="__('Manage system roles and their associated permissions.')">
        <div x-data="{ currentTab: @entangle('tab') }" class="w-full">
            <div class="flex items-center justify-between mb-6 gap-4 flex-wrap border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex gap-6">
                    <button @click="currentTab = 'roles'" :class="currentTab === 'roles' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'" class="flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2">
                        <flux:icon.shield-check class="size-4" />
                        {{ __('Roles') }}
                    </button>
                    <button @click="currentTab = 'permissions'" :class="currentTab === 'permissions' ? 'border-primary-500 text-primary-600 dark:text-primary-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'" class="flex items-center gap-2 pb-3 text-sm font-medium transition-colors border-b-2">
                        <flux:icon.key class="size-4" />
                        {{ __('Permissions') }}
                    </button>
                </div>

                <div class="flex items-center gap-3 grow sm:grow-0 mb-3">
                    <flux:input wire:model.live.debounce.300ms="search" 
                        icon="magnifying-glass" 
                        size="sm" 
                        placeholder="{{ __('Search...') }}"
                        class="w-full sm:w-64"
                    />
                    
                    <div x-show="currentTab === 'roles'">
                        @can('roles.create')
                        <flux:button variant="primary" size="sm" icon="plus" wire:click="openRoleCreateModal">
                            <span class="hidden sm:inline">{{ __('Create') }}</span>
                        </flux:button>
                        @endcan
                    </div>
                </div>
            </div>

            <div x-show="currentTab === 'roles'" class="space-y-6">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden bg-white dark:bg-zinc-900 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-zinc-50/50 dark:bg-zinc-800/50 border-b border-zinc-200 dark:border-zinc-800">
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-widest text-zinc-500">{{ __('Role') }}</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-widest text-zinc-500 hidden sm:table-cell">{{ __('Description') }}</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-widest text-zinc-500 text-center">{{ __('Permissions') }}</th>
                                    <th class="px-4 py-3 text-xs font-bold uppercase tracking-widest text-zinc-500"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($this->roles as $role)
                                    <tr wire:key="role-row-{{ $role->role_id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                <div class="size-8 rounded-lg bg-primary-50 dark:bg-primary-900/20 flex items-center justify-center shrink-0">
                                                    <flux:icon.shield-check class="size-4 text-primary-500" />
                                                </div>
                                                <span class="font-semibold text-zinc-900 dark:text-zinc-100 capitalize">
                                                    {{ str_replace('_', ' ', $role->role_name) }}
                                                </span>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4 hidden sm:table-cell">
                                            <span class="text-xs text-zinc-500">{{ $role->description ?: __('No description') }}</span>
                                        </td>

                                        <td class="px-4 py-4 text-center">
                                            @php $count = $role->permissions->count(); @endphp
                                            <button type="button" wire:click="openRoleViewModal({{ $role->role_id }})" class="hover:opacity-80 transition-opacity">
                                                <flux:badge size="sm" :color="$count > 0 ? 'primary' : 'zinc'" inset="top bottom">
                                                    {{ $count }}
                                                </flux:badge>
                                            </button>
                                        </td>

                                        <td class="px-4 py-4 text-right">
                                            <flux:dropdown align="end">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />

                                                <flux:menu>
                                                    <flux:menu.item icon="eye" wire:click="openRoleViewModal({{ $role->role_id }})">{{ __('View Details') }}</flux:menu.item>
                                                    
                                                    @can('roles.edit')
                                                    <flux:menu.item icon="pencil-square" wire:click="openRoleEditModal({{ $role->role_id }})">{{ __('Edit Role') }}</flux:menu.item>
                                                    @endcan

                                                    @if($role->role_name !== 'Super Admin')
                                                        @can('roles.delete')
                                                        <flux:menu.item icon="trash" variant="danger" wire:click="openRoleDeleteModal({{ $role->role_id }})">{{ __('Delete Role') }}</flux:menu.item>
                                                        @endcan
                                                    @endif
                                                </flux:menu>
                                            </flux:dropdown>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-12 text-center text-zinc-500">
                                            {{ __('No roles found.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div x-show="currentTab === 'permissions'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($this->allPermissions as $group => $permissions)
                    <flux:card class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm" class="uppercase tracking-wider text-primary-500 font-bold">
                                {{ $group }}
                            </flux:heading>
                            <flux:badge size="sm" inset="top bottom">{{ $permissions->count() }}</flux:badge>
                        </div>

                        <div class="space-y-1">
                            @foreach ($permissions as $p)
                                <div class="flex items-center gap-2 py-1 border-b border-zinc-100 dark:border-zinc-800 last:border-0 group">
                                    <flux:icon.key class="size-3 text-zinc-400 group-hover:text-primary-500 transition-colors" />
                                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ Str::after($p->permission_name, '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @empty
                    <div class="col-span-full py-20 text-center">
                        <flux:icon.key class="size-12 mx-auto mb-4 text-zinc-300" />
                        <flux:heading size="lg">{{ __('No permissions found') }}</flux:heading>
                        <flux:subheading>{{ __('Adjust your search to see more results.') }}</flux:subheading>
                    </div>
                @endforelse
            </div>
        </div>
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
                                    <flux:tooltip :content="$permission->description ?? __('No description')">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800 cursor-default">
                                            {{ Str::after($permission->permission_name, '.') }}
                                        </span>
                                    </flux:tooltip>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:input wire:model="roleName" :label="__('Role Name')" placeholder="{{ __('e.g. Manager') }}" required />
                    <flux:input wire:model="roleDescription" :label="__('Description')" placeholder="{{ __('Brief description') }}" />
                </div>

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                        <flux:text size="xs" class="text-zinc-500">{{ __('Grouped by module') }}</flux:text>
                    </div>

                    <div class="space-y-4 max-h-[50vh] overflow-y-auto pr-2 -mr-2">
                        @foreach ($this->allPermissions as $group => $permissions)
                            <div class="p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 space-y-4">
                                <div class="flex items-center justify-between">
                                    <flux:heading size="sm" class="uppercase tracking-wider text-primary-500 font-bold">
                                        {{ $group }}
                                    </flux:heading>
                                    
                                    <flux:button variant="ghost" size="xs" wire:click="toggleGroup('{{ $group }}')">
                                        {{ __('Select All') }}
                                    </flux:button>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                    @foreach ($permissions as $permission)
                                        <label class="group flex items-center gap-2 cursor-pointer p-2 rounded-lg border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                                            <flux:checkbox wire:model="selectedPermissions" :value="$permission->permission_name" />
                                            <flux:tooltip :content="$permission->description ?? __('No description')" class="flex-1 min-w-0">
                                                <span class="text-[11px] font-medium text-zinc-600 dark:text-zinc-400 group-hover:text-primary-500 transition-colors truncate block">
                                                    {{ Str::after($permission->permission_name, '.') }}
                                                </span>
                                            </flux:tooltip>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
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
