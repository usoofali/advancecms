<?php

use App\Models\Role;
use App\Models\Permission;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Manage Role Permissions')] class extends Component {
    public Role $role;
    public array $selectedPermissions = [];

    public function mount(Role $role): void
    {
        $this->role = $role;
        $this->selectedPermissions = $role->permissions()->pluck('permissions.permission_id')->map(fn($id) => (string) $id)->toArray();
    }

    public function savePermissions(): void
    {
        // Sync the pivot table
        $this->role->permissions()->sync($this->selectedPermissions);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Permissions updated successfully for ' . $this->role->role_name,
        ]);
        
        $this->dispatch('permissions-updated');
    }

    public function render(): \Illuminate\View\View
    {
        return view('pages.cms.roles.permissions', [
            'allPermissions' => Permission::orderBy('permission_name')->get(),
        ]);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="mb-8">
        <flux:breadcrumbs class="mb-4">
            <flux:breadcrumbs.item icon="shield-check" :href="route('cms.roles.index')">{{ __('Roles') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('Manage Permissions') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Permissions for: ') }} {{ $role->role_name }}</flux:heading>
                <flux:subheading>{{ $role->description }}</flux:subheading>
            </div>
            <flux:button href="{{ route('cms.roles.index') }}" wire:navigate icon="arrow-left">
                {{ __('Back to Roles') }}
            </flux:button>
        </div>
    </div>

    @if($role->role_name === 'Super Admin')
        <flux:card class="mb-6 border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/20">
            <div class="flex items-start gap-3">
                <flux:icon.shield-exclamation class="text-red-600 dark:text-red-400 mt-0.5" />
                <div>
                    <h3 class="text-sm font-semibold text-red-800 dark:text-red-300">{{ __('Super Admin Bypass') }}</h3>
                    <p class="text-sm text-red-700 dark:text-red-400 mt-1">
                        {{ __('The Super Admin role automatically bypasses all permission checks globally. Selecting or deselecting permissions here will not affect a Super Admin\'s actual access, but is preserved for record keeping.') }}
                    </p>
                </div>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <form wire:submit="savePermissions">
            <flux:heading size="lg" class="mb-2">{{ __('Assign Permissions') }}</flux:heading>
            <flux:subheading class="mb-6 max-w-3xl">
                {{ __('Select the precise actions this role can perform. Users assigned to this role (e.g., Staff members) will automatically inherit these capabilities across the institution.') }}
            </flux:subheading>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                @foreach($allPermissions as $permission)
                    <div class="relative flex items-start p-4 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                        <div class="flex items-center h-5">
                            <flux:checkbox 
                                wire:model="selectedPermissions" 
                                :value="(string)$permission->permission_id" 
                                id="perm-{{ $permission->permission_id }}" 
                            />
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="perm-{{ $permission->permission_id }}" class="font-medium text-zinc-900 dark:text-white cursor-pointer select-none">
                                {{ str_replace('_', ' ', Str::title($permission->permission_name)) }}
                            </label>
                            <p class="text-zinc-500 font-mono text-[11px] mt-0.5" onclick="document.getElementById('perm-{{ $permission->permission_id }}').click()">
                                {{ $permission->permission_name }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-4 border-t border-zinc-200 dark:border-zinc-700 pt-4 justify-end">
                <x-action-message on="permissions-updated">
                    <flux:badge color="green">{{ __('Saved Successfully') }}</flux:badge>
                </x-action-message>
                <flux:button type="submit" variant="primary">{{ __('Save Permissions') }}</flux:button>
            </div>
        </form>
    </flux:card>
</div>
