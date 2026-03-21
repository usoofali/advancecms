<?php

use App\Models\Role;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Roles & Permissions')] class extends Component {
    public function render(): \Illuminate\View\View
    {
        return view('pages.cms.roles.index', [
            'roles' => Role::withCount('permissions')->orderBy('role_name')->get(),
        ]);
    }
}; ?>

<div class="mx-auto max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Roles & Permissions') }}</flux:heading>
            <flux:subheading>{{ __('Manage system roles and their assigned permissions') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
            <thead>
                <tr class="bg-zinc-50 dark:bg-zinc-800/50">
                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900 dark:text-zinc-100">Role</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900 dark:text-zinc-100">Description</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-zinc-900 dark:text-zinc-100">Permissions</th>
                    <th class="px-4 py-3 text-right text-sm font-semibold text-zinc-900 dark:text-zinc-100">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach($roles as $role)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors">
                        <td class="px-4 py-4 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $role->role_name }}
                        </td>
                        <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $role->description }}
                        </td>
                        <td class="px-4 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $role->permissions_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-4 text-right">
                            <flux:button size="sm" variant="ghost" icon="shield-check" :href="route('cms.roles.permissions', $role->role_id)" wire:navigate title="Manage Permissions">
                                Manage
                            </flux:button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </flux:card>
</div>
