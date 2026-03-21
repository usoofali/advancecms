<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Setup')] #[Layout('layouts.guest')] class extends Component
{
    public string $step = 'welcome';

    public bool $db_connected = false;

    public bool $storage_linked = false;

    public string $php_version = '';

    public array $folder_perms = [];

    public string $last_output = '';

    // Admin Form
    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public array $target_folders = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function mount(): void
    {
        // Security Check: If a Super Admin already exists, redirect to login.
        try {
            $hasSuperAdmin = User::whereHas('roles', fn ($q) => $q->where('role_name', 'Super Admin'))->exists();
            if ($hasSuperAdmin) {
                redirect()->route('login');
            }
        } catch (Exception $e) {
            // Database might not even be migrated yet, which is fine for setup.
        }

        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $this->php_version = PHP_VERSION;

        try {
            DB::connection()->getPdo();
            $this->db_connected = true;
        } catch (Exception $e) {
            $this->db_connected = false;
        }

        $this->storage_linked = File::exists(public_path('storage'));

        foreach ($this->target_folders as $folder) {
            $path = base_path($folder);
            if (File::exists($path)) {
                $this->folder_perms[$folder] = substr(sprintf('%o', fileperms($path)), -4);
            } else {
                $this->folder_perms[$folder] = 'missing';
            }
        }
    }

    public function fixPermission(string $folder): void
    {
        $path = base_path($folder);
        if (File::exists($path)) {
            @chmod($path, 0775);
            $this->refreshStats();
        }
    }

    public function createStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
            $this->refreshStats();
            $this->dispatch('notify', [
                'message' => __('Storage symbolic link created successfully.'),
                'variant' => 'success',
            ]);
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'message' => __('Failed to create storage link: ').$e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }

    public function initializeDatabase(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            $this->last_output = Artisan::output();
            $this->step = 'admin';
            $this->dispatch('notify', [
                'message' => __('Database initialized successfully.'),
                'variant' => 'success',
            ]);
        } catch (Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dispatch('notify', [
                'message' => __('Database initialization failed.'),
                'variant' => 'error',
            ]);
        }
    }

    public function createAdmin(): void
    {
        $this->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8|confirmed',
        ]);

        try {
            $user = User::create([
                'name' => $this->admin_name,
                'email' => $this->admin_email,
                'password' => Hash::make($this->admin_password),
                'email_verified_at' => now(),
            ]);

            $role = Role::where('role_name', 'Super Admin')->first();
            if ($role) {
                $user->roles()->attach($role->role_id);
            }

            $this->dispatch('notify', [
                'message' => __('Super Admin created successfully.'),
                'variant' => 'success',
            ]);

            // Final redirect
            redirect()->route('login')->with('status', __('Setup complete! You can now log in.'));
        } catch (Exception $e) {
            $this->dispatch('notify', [
                'message' => __('Failed to create admin: ').$e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }
}; ?>

<x-layouts::auth :title="__('System Setup')">
    <div class="space-y-6">
        <x-auth-header :title="__('System Setup Wizard')" :description="__('Complete the following steps to initialize your CMS environment.')" />

        @if($step === 'welcome')
            <div class="space-y-4">
                <flux:card class="p-4 space-y-4 border-zinc-100 dark:border-zinc-800">
                    <flux:text>{{ __('Welcome to the CMS Setup Wizard. This tool will help you configure your database and create your first administrative account.') }}</flux:text>
                    <flux:button wire:click="$set('step', 'environment')" variant="primary" class="w-full">{{ __('Start Setup') }}</flux:button>
                </flux:card>
            </div>
        @elseif($step === 'environment')
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Environment Audit') }}</flux:heading>
                
                <flux:card class="p-0 border-zinc-100 dark:border-zinc-800 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                    <!-- PHP & DB -->
                    <div class="p-4 flex items-center justify-between">
                        <flux:text size="sm">{{ __('PHP Version') }}</flux:text>
                        <flux:badge size="sm" color="blue">{{ $php_version }}</flux:badge>
                    </div>
                    <div class="p-4 flex items-center justify-between">
                        <flux:text size="sm">{{ __('Database Connection') }}</flux:text>
                        <flux:badge size="sm" :color="$db_connected ? 'green' : 'red'">
                            {{ $db_connected ? __('Connected') : __('Failed') }}
                        </flux:badge>
                    </div>
                    <div class="p-4 flex items-center justify-between">
                        <flux:text size="sm">{{ __('Storage Link') }}</flux:text>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$storage_linked ? 'green' : 'amber'">
                                {{ $storage_linked ? __('Linked') : __('Missing') }}
                            </flux:badge>
                            @if(!$storage_linked)
                                <flux:button wire:click="createStorageLink" size="xs" variant="ghost" icon="link" />
                            @endif
                        </div>
                    </div>

                    <!-- Folders -->
                    @foreach($target_folders as $folder)
                    <div class="p-4 flex items-center justify-between">
                        <flux:text size="xs" class="font-mono">{{ $folder }}</flux:text>
                        <div class="flex items-center gap-2">
                            <flux:text size="xs" class="font-mono {{ in_array($folder_perms[$folder], ['0775', '0755']) ? 'text-green-600' : 'text-red-600' }}">
                                {{ $folder_perms[$folder] }}
                            </flux:text>
                            @if(!in_array($folder_perms[$folder], ['0775', '0755', 'missing']))
                                <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="ghost" icon="wrench" />
                            @endif
                        </div>
                    </div>
                    @endforeach
                </flux:card>

                <div class="flex gap-2">
                    <flux:button wire:click="$set('step', 'welcome')" variant="ghost" class="flex-1">{{ __('Back') }}</flux:button>
                    <flux:button wire:click="$set('step', 'database')" variant="primary" class="flex-1" :disabled="!$db_connected">{{ __('Next: Database') }}</flux:button>
                </div>
            </div>
        @elseif($step === 'database')
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Database Initialization') }}</flux:heading>
                
                <flux:card class="p-4 space-y-4 border-zinc-100 dark:border-zinc-800 text-center">
                    <flux:icon.table-cells class="size-12 mx-auto text-zinc-300" />
                    <flux:text size="sm">{{ __('This will run all system migrations and seed the database with required roles and permissions.') }}</flux:text>
                    <flux:button wire:click="initializeDatabase" variant="primary" class="w-full" icon="play">{{ __('Initialize Database') }}</flux:button>
                </flux:card>

                @if($last_output)
                    <pre class="p-3 bg-zinc-900 text-zinc-300 rounded-lg text-[10px] overflow-auto max-h-40 whitespace-pre-wrap">{{ $last_output }}</pre>
                @endif

                <div class="flex gap-2">
                    <flux:button wire:click="$set('step', 'environment')" variant="ghost" class="flex-1">{{ __('Back') }}</flux:button>
                </div>
            </div>
        @elseif($step === 'admin')
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Create Super Admin') }}</flux:heading>
                
                <form wire:submit="createAdmin" class="space-y-4">
                    <flux:input wire:model="admin_name" :label="__('Full Name')" placeholder="Super Admin" required />
                    <flux:input wire:model="admin_email" type="email" :label="__('Email Address')" placeholder="admin@example.com" required />
                    <flux:input wire:model="admin_password" type="password" :label="__('Password')" required viewable />
                    <flux:input wire:model="admin_password_confirmation" type="password" :label="__('Confirm Password')" required viewable />

                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Complete Setup') }}</flux:button>
                </form>
            </div>
        @endif
    </div>
</x-layouts::auth>
