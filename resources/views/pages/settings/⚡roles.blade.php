<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Role Management')] class extends Component
{
    use WithPagination;

    public string $tab = 'roles';

    public string $search = '';

    public string $modalPermissionSearch = '';

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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('role_name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('role_name')
            ->paginate(15);
    }

    #[Computed]
    public function allPermissions(): Collection
    {
        return $this->groupedPermissions($this->search);
    }

    #[Computed]
    public function modalPermissions(): Collection
    {
        return $this->groupedPermissions($this->modalPermissionSearch);
    }

    #[Computed]
    public function totalPermissionsCount(): int
    {
        return Permission::count();
    }

    protected function groupedPermissions(string $search): Collection
    {
        return Permission::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('permission_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('permission_name')
            ->get()
            ->groupBy(function (Permission $p) {
                $parts = explode('.', $p->permission_name);

                return count($parts) > 1 ? str_replace('_', ' ', $parts[0]) : 'other';
            })
            ->sortKeys();
    }

    public function formatGroupTitle(string $group): string
    {
        $title = ucwords($group);

        return str_replace(['Cbt ', 'Ca ', 'Id '], ['CBT ', 'C.A ', 'ID '], $title.' ');
    }

    public function formatPermissionTitle(string $permissionName): string
    {
        $action = Str::after($permissionName, '.');

        $custom = [
            'view_dept' => 'View Department',
            'view_assigned' => 'View Assigned',
            'view_personal' => 'View Own',
            'view_own' => 'View Own',
            'view_own_payslip' => 'View Own Payslips',
            'view_all' => 'View All',
            'view_all_data' => 'View All Institutions',
            'view_history' => 'View History',
            'view_lecturers' => 'View Course Lecturers',
            'mass_action' => 'Approve / Reject All',
            'manage_tokens' => 'Manage Access Keys',
            'manage_sessions' => 'Manage Sessions',
            'manage_students' => 'Manage Student Accounts',
            'assign_supervisors' => 'Assign Supervisors',
            'assign_officers' => 'Assign Room Officers',
            'assign_roles' => 'Assign Scoped Roles',
            'review_topics' => 'Review Topics',
            'change_status' => 'Change Status',
            'print_letter' => 'Print Letter',
            'print_receipt' => 'Print Receipt',
            'print_form' => 'Print Registration Form',
            'print_exam_card' => 'Print Exam Card',
            'print_report' => 'Print Report',
            'revoke_allocation' => 'Remove Course Allocation',
            'create_run' => 'Prepare Monthly Payroll',
            'mark_paid' => 'Mark Payroll Paid',
            'reconcile' => 'Stock Count & Adjust',
        ];

        if (isset($custom[$action])) {
            return $custom[$action];
        }

        return ucwords(str_replace('_', ' ', $action));
    }

    public function toggleGroup(string $group): void
    {
        $groupPermissions = ($this->modalPermissions[$group] ?? collect())
            ->pluck('permission_name')
            ->toArray();

        if (empty($groupPermissions)) {
            return;
        }

        $allPresent = collect($groupPermissions)->every(fn ($p) => in_array($p, $this->selectedPermissions));

        if ($allPresent) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPermissions));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPermissions)));
        }
    }

    public function selectAllModalPermissions(): void
    {
        $visible = $this->modalPermissions
            ->flatten(1)
            ->pluck('permission_name')
            ->toArray();

        $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $visible)));
    }

    public function clearAllModalPermissions(): void
    {
        $this->selectedPermissions = [];
    }

    #[Computed]
    public function viewingRole(): ?Role
    {
        if ($this->viewingRoleId === null) {
            return null;
        }

        return Role::query()
            ->withCount('users')
            ->with('permissions')
            ->find($this->viewingRoleId);
    }

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
        $this->modalPermissionSearch = '';
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
            'roleName' => 'required|string|max:255|unique:roles,role_name,'.$this->roleEditingId.',role_id',
            'roleDescription' => 'nullable|string|max:500',
            'selectedPermissions' => 'array',
        ]);

        if ($this->roleEditingId) {
            $role = Role::findOrFail($this->roleEditingId);
            $newName = $role->role_name === 'Super Admin' ? 'Super Admin' : $this->roleName;
            $role->update(['role_name' => $newName, 'description' => $this->roleDescription]);
        } else {
            $role = Role::create(['role_name' => $this->roleName, 'description' => $this->roleDescription]);
        }

        $permissionIds = Permission::whereIn('permission_name', $this->selectedPermissions)
            ->pluck('permission_id')
            ->toArray();
        $role->permissions()->sync($permissionIds);

        $wasEditing = (bool) $this->roleEditingId;
        $this->showRoleEditModal = false;
        $this->resetRoleEditForm();
        Flux::toast($wasEditing ? __('Role updated successfully.') : __('Role created successfully.'), variant: 'success');
    }

    public function openRoleDeleteModal(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        if ($role->role_name === 'Super Admin') {
            return;
        }

        $this->rolePendingDeleteId = $role->role_id;
        $this->rolePendingDeleteName = $role->role_name;
        $this->showRoleDeleteModal = true;
    }

    public function deleteRole(): void
    {
        Gate::authorize('roles.delete');

        if ($this->rolePendingDeleteId) {
            $role = Role::findOrFail($this->rolePendingDeleteId);
            if ($role->role_name === 'Super Admin') {
                Flux::toast(__('Super Admin role cannot be deleted.'), variant: 'danger');
                $this->showRoleDeleteModal = false;

                return;
            }

            $role->delete();
            $this->showRoleDeleteModal = false;
            $this->rolePendingDeleteId = null;
            $this->rolePendingDeleteName = '';
            Flux::toast(__('Role deleted successfully.'), variant: 'success');
        }
    }

    private function resetRoleEditForm(): void
    {
        $this->roleEditingId = null;
        $this->roleName = '';
        $this->roleDescription = '';
        $this->selectedPermissions = [];
        $this->modalPermissionSearch = '';
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        :heading="__('Roles & Permissions')"
        :subheading="__('Manage user roles and control what each role can view or do across the system.')"
        :wide="true"
    >
        <div x-data="{ currentTab: @entangle('tab') }" class="w-full space-y-6">
            {{-- Tabs & Search Bar --}}
            <div class="flex flex-col gap-4 border-b border-zinc-200 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-6">
                    <button
                        type="button"
                        @click="currentTab = 'roles'"
                        :class="currentTab === 'roles' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
                        class="flex items-center gap-2 border-b-2 pb-3 text-sm font-medium transition-colors"
                    >
                        <flux:icon.shield-check class="size-4" />
                        <span>{{ __('Roles') }}</span>
                        <flux:badge size="sm" color="zinc">{{ $this->roles->total() }}</flux:badge>
                    </button>

                    <button
                        type="button"
                        @click="currentTab = 'permissions'"
                        :class="currentTab === 'permissions' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200'"
                        class="flex items-center gap-2 border-b-2 pb-3 text-sm font-medium transition-colors"
                    >
                        <flux:icon.key class="size-4" />
                        <span>{{ __('Permissions') }}</span>
                        <flux:badge size="sm" color="zinc">{{ $this->totalPermissionsCount }}</flux:badge>
                    </button>
                </div>

                <div class="flex items-center gap-2 pb-3 sm:pb-2">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        icon="magnifying-glass"
                        size="sm"
                        placeholder="{{ __('Search roles or permissions...') }}"
                        class="w-full sm:w-64"
                    />

                    <div x-show="currentTab === 'roles'" class="shrink-0">
                        @can('roles.create')
                            <flux:button variant="primary" size="sm" icon="plus" wire:click="openRoleCreateModal">
                                <span class="hidden sm:inline">{{ __('New Role') }}</span>
                            </flux:button>
                        @endcan
                    </div>
                </div>
            </div>

            {{-- Roles Tab --}}
            <div x-show="currentTab === 'roles'" class="space-y-4">
                <flux:card>
                    <div class="relative overflow-x-auto">
                        <flux:table :paginate="$this->roles">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Role') }}</flux:table.column>
                        <flux:table.column class="text-center">{{ __('Users') }}</flux:table.column>
                        <flux:table.column class="text-center">{{ __('Permissions') }}</flux:table.column>
                        <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->roles as $role)
                            <flux:table.row wire:key="role-row-{{ $role->role_id }}">
                                <flux:table.cell>
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                            <flux:icon.shield-check class="size-4" />
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="openRoleViewModal({{ $role->role_id }})"
                                            class="text-left font-semibold text-zinc-900 hover:text-indigo-600 dark:text-zinc-100 dark:hover:text-indigo-400"
                                        >
                                            {{ str_replace('_', ' ', $role->role_name) }}
                                        </button>
                                    </div>
                                </flux:table.cell>

                                <flux:table.cell class="text-center">
                                    <flux:badge size="sm" color="zinc">{{ $role->users_count }}</flux:badge>
                                </flux:table.cell>

                                <flux:table.cell class="text-center">
                                    <button
                                        type="button"
                                        wire:click="openRoleViewModal({{ $role->role_id }})"
                                        class="transition-opacity hover:opacity-80"
                                    >
                                        <flux:badge size="sm" :color="$role->permissions_count > 0 ? 'indigo' : 'zinc'">
                                            {{ $role->permissions_count }}
                                        </flux:badge>
                                    </button>
                                </flux:table.cell>

                                <flux:table.cell class="text-right">
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" aria-label="{{ __('Actions') }}" />

                                        <flux:menu>
                                            <flux:menu.item icon="eye" wire:click="openRoleViewModal({{ $role->role_id }})">
                                                {{ __('View Permissions') }}
                                            </flux:menu.item>

                                            @can('roles.edit')
                                                <flux:menu.item icon="pencil-square" wire:click="openRoleEditModal({{ $role->role_id }})">
                                                    {{ __('Edit Role') }}
                                                </flux:menu.item>
                                            @endcan

                                            @if ($role->role_name !== 'Super Admin')
                                                @can('roles.delete')
                                                    <flux:menu.separator />
                                                    <flux:menu.item icon="trash" variant="danger" wire:click="openRoleDeleteModal({{ $role->role_id }})">
                                                        {{ __('Delete Role') }}
                                                    </flux:menu.item>
                                                @endcan
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4" class="py-12 text-center text-zinc-500">
                                    {{ __('No roles found matching your search.') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                        </flux:table>
                    </div>
                </flux:card>
            </div>

            {{-- Permissions Reference Tab --}}
            <div x-show="currentTab === 'permissions'" class="space-y-3">
                @if ($this->allPermissions->isNotEmpty())
                    <div class="flex items-center justify-end gap-2">
                        <flux:button type="button" size="xs" variant="ghost" x-on:click="$dispatch('toggle-all-permissions', true)">
                            {{ __('Expand All') }}
                        </flux:button>
                        <flux:button type="button" size="xs" variant="ghost" x-on:click="$dispatch('toggle-all-permissions', false)">
                            {{ __('Collapse All') }}
                        </flux:button>
                    </div>
                @endif

                @forelse ($this->allPermissions as $group => $permissions)
                    <div
                        x-data="{ open: @js($loop->first || filled($search)) }"
                        @toggle-all-permissions.window="open = $event.detail"
                        wire:key="perm-acc-{{ Str::slug($group) }}"
                        class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900"
                    >
                        <button
                            type="button"
                            @click="open = !open"
                            :aria-expanded="open"
                            class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ trim($this->formatGroupTitle($group)) }}
                                </span>
                                <flux:badge size="sm" color="zinc">{{ $permissions->count() }}</flux:badge>
                            </div>
                            <flux:icon.chevron-down
                                class="size-4 shrink-0 text-zinc-400 transition-transform duration-200"
                                x-bind:class="open ? 'rotate-180 text-indigo-500' : ''"
                            />
                        </button>

                        <div
                            x-show="open"
                            x-collapse
                            class="border-t border-zinc-100 bg-zinc-50/40 p-4 dark:border-zinc-800 dark:bg-zinc-950/30"
                        >
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($permissions as $p)
                                    <div class="rounded-lg border border-zinc-200/80 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.key class="size-3.5 shrink-0 text-indigo-500 dark:text-indigo-400" />
                                            <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                                {{ $this->formatPermissionTitle($p->permission_name) }}
                                            </span>
                                        </div>
                                        <p class="mt-1 pl-5 text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">
                                            {{ $p->description ?: __('No description available.') }}
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-16 text-center">
                        <flux:icon.key class="mx-auto mb-3 size-10 text-zinc-300 dark:text-zinc-600" />
                        <flux:heading size="lg">{{ __('No permissions found') }}</flux:heading>
                        <flux:subheading>{{ __('Try a different search term.') }}</flux:subheading>
                    </div>
                @endforelse
            </div>
        </div>
    </x-pages::settings.layout>

    {{-- View Role Modal --}}
    <flux:modal wire:model.self="showRoleViewModal" class="w-full max-w-3xl">
        @if ($this->viewingRole)
            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                        <flux:icon.shield-check class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:heading size="lg">{{ str_replace('_', ' ', $this->viewingRole->role_name) }}</flux:heading>
                        <flux:subheading>{{ $this->viewingRole->description ?: __('No description provided.') }}</flux:subheading>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <flux:badge size="sm" color="zinc">{{ $this->viewingRole->users_count }} {{ Str::plural('user', $this->viewingRole->users_count) }}</flux:badge>
                            <flux:badge size="sm" color="indigo">{{ $this->viewingRole->permissions->count() }} {{ Str::plural('permission', $this->viewingRole->permissions->count()) }}</flux:badge>
                        </div>
                    </div>
                </div>

                @php
                    $grouped = $this->viewingRole->permissions
                        ->groupBy(fn ($p) => str_replace('_', ' ', explode('.', $p->permission_name)[0]))
                        ->sortKeys();
                @endphp

                <div class="max-h-[55vh] space-y-4 overflow-y-auto pr-1">
                    @forelse ($grouped as $group => $permissions)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/50 p-3.5 dark:border-zinc-800 dark:bg-zinc-900/50">
                            <div class="mb-2.5 flex items-center justify-between">
                                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    {{ trim($this->formatGroupTitle($group)) }}
                                </span>
                                <flux:badge size="sm" color="zinc">{{ $permissions->count() }}</flux:badge>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($permissions as $permission)
                                    <div class="rounded-lg border border-zinc-200/80 bg-white p-2.5 dark:border-zinc-800 dark:bg-zinc-900">
                                        <div class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $this->formatPermissionTitle($permission->permission_name) }}
                                        </div>
                                        <div class="mt-0.5 text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                            {{ $permission->description ?: __('No description available.') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-10 text-zinc-400">
                            <flux:icon.shield-exclamation class="mb-2 size-10 opacity-40" />
                            <flux:text size="sm">{{ __('No permissions assigned to this role.') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
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
    <flux:modal wire:model.self="showRoleEditModal" class="w-full max-w-4xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $roleEditingId ? __('Edit Role') : __('Create New Role') }}</flux:heading>
                <flux:subheading>{{ __('Name the role, describe its purpose, and choose what users with this role can do.') }}</flux:subheading>
            </div>

            <form wire:submit="saveRole" class="space-y-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <flux:input
                        wire:model="roleName"
                        :label="__('Role Name')"
                        placeholder="{{ __('e.g. Bursar Assistant') }}"
                        :disabled="$roleName === 'Super Admin'"
                        required
                    />
                    <flux:input
                        wire:model="roleDescription"
                        :label="__('Description')"
                        placeholder="{{ __('Brief summary of who gets this role') }}"
                    />
                </div>

                <div class="space-y-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ __('Permissions') }}</flux:heading>
                            <flux:badge size="sm" color="indigo">
                                {{ count($selectedPermissions) }} / {{ $this->totalPermissionsCount }} {{ __('selected') }}
                            </flux:badge>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:input
                                wire:model.live.debounce.200ms="modalPermissionSearch"
                                icon="magnifying-glass"
                                size="xs"
                                placeholder="{{ __('Filter permissions...') }}"
                                class="w-full sm:w-52"
                            />
                            <flux:button type="button" size="xs" variant="ghost" wire:click="selectAllModalPermissions">
                                {{ __('Select All') }}
                            </flux:button>
                            <flux:button type="button" size="xs" variant="ghost" wire:click="clearAllModalPermissions">
                                {{ __('Clear') }}
                            </flux:button>
                        </div>
                    </div>

                    <div class="max-h-[50vh] space-y-4 overflow-y-auto pr-1">
                        @forelse ($this->modalPermissions as $group => $permissions)
                            @php
                                $groupNames = $permissions->pluck('permission_name')->toArray();
                                $groupSelectedCount = count(array_intersect($groupNames, $selectedPermissions));
                                $allGroupSelected = $groupSelectedCount === count($groupNames) && count($groupNames) > 0;
                            @endphp
                            <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <flux:heading size="sm" class="font-bold text-indigo-600 dark:text-indigo-400">
                                            {{ trim($this->formatGroupTitle($group)) }}
                                        </flux:heading>
                                        <flux:badge size="sm" :color="$groupSelectedCount > 0 ? 'indigo' : 'zinc'">
                                            {{ $groupSelectedCount }} / {{ count($groupNames) }}
                                        </flux:badge>
                                    </div>

                                    <flux:button type="button" variant="ghost" size="xs" wire:click="toggleGroup('{{ $group }}')">
                                        {{ $allGroupSelected ? __('Deselect All') : __('Select All') }}
                                    </flux:button>
                                </div>

                                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                    @foreach ($permissions as $permission)
                                        <label class="group flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200/80 bg-white p-3 transition-colors hover:border-indigo-300 hover:bg-indigo-50/30 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20">
                                            <flux:checkbox wire:model.live="selectedPermissions" :value="$permission->permission_name" class="mt-0.5" />
                                            <div class="min-w-0 flex-1">
                                                <div class="text-xs font-semibold text-zinc-900 group-hover:text-indigo-600 dark:text-zinc-100 dark:group-hover:text-indigo-400">
                                                    {{ $this->formatPermissionTitle($permission->permission_name) }}
                                                </div>
                                                <p class="mt-0.5 text-[11px] leading-relaxed text-zinc-500 dark:text-zinc-400">
                                                    {{ $permission->description ?: __('No description available.') }}
                                                </p>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="py-10 text-center text-sm text-zinc-500">
                                {{ __('No permissions match ":search".', ['search' => $modalPermissionSearch]) }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800 sm:flex-row sm:justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="w-full sm:w-auto">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" type="submit" wire:loading.attr="disabled" class="w-full sm:w-auto">
                        {{ __('Save Role') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Delete Role Modal --}}
    <flux:modal wire:model.self="showRoleDeleteModal" class="w-full max-w-md">
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
