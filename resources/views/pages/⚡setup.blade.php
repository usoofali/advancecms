<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('System Setup')] #[Layout('layouts.guest')] class extends Component {
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
            $hasSuperAdmin = User::whereHas('roles', fn($q) => $q->where('role_name', 'Super Admin'))->exists();
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
                'message' => __('Failed to create storage link: ') . $e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }

    public function initializeDatabase(): void
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
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
                'message' => __('Failed to create admin: ') . $e->getMessage(),
                'variant' => 'error',
            ]);
        }
    }
}; ?>

<div class="max-w-3xl mx-auto py-8 lg:py-12">
    <div class="text-center mb-10 space-y-3">
        <div class="size-16 bg-zinc-100 dark:bg-zinc-800/50 rounded-2xl flex items-center justify-center mx-auto mb-2">
            <flux:icon name="cog-6-tooth" class="size-8 text-zinc-500 dark:text-zinc-400" />
        </div>
        <flux:heading size="xl" level="1">{{ __('System Setup') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Initialize your environment, database, and admin account.') }}
        </flux:subheading>
    </div>

    <flux:card class="p-0 overflow-hidden border-zinc-200 dark:border-zinc-800 space-y-0 shadow-sm">

        <!-- Step 1: Environment -->
        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-6">
                <div
                    class="flex items-center justify-center size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                    1</div>
                <div>
                    <flux:heading size="lg">{{ __('Environment Audit') }}</flux:heading>
                    <flux:subheading>{{ __('Ensuring your server meets requirements.') }}</flux:subheading>
                </div>
            </div>

            <div class="grid gap-3">
                <div
                    class="flex items-center justify-between p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800/60">
                    <flux:text size="sm" weight="medium">{{ __('PHP Version') }}</flux:text>
                    <flux:badge size="sm" color="blue">{{ $php_version }}</flux:badge>
                </div>

                <div
                    class="flex items-center justify-between p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800/60">
                    <flux:text size="sm" weight="medium">{{ __('Database Connection') }}</flux:text>
                    <flux:badge size="sm" :color="$db_connected ? 'green' : 'red'">
                        {{ $db_connected ? __('Connected') : __('Failed') }}
                    </flux:badge>
                </div>

                <div
                    class="flex items-center justify-between p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-800/60">
                    <flux:text size="sm" weight="medium">{{ __('Storage Link') }}</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" :color="$storage_linked ? 'green' : 'amber'">
                            {{ $storage_linked ? __('Linked') : __('Missing') }}
                        </flux:badge>
                        @if(!$storage_linked)
                            <flux:button wire:click="createStorageLink" size="xs" variant="outline" icon="link" />
                        @endif
                    </div>
                </div>

                <div
                    class="mt-2 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800/60">
                    @foreach($target_folders as $folder)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-zinc-900/40">
                            <flux:text size="xs" class="font-mono text-zinc-500">{{ $folder }}</flux:text>
                            <div class="flex items-center gap-3">
                                <flux:text size="xs"
                                    class="font-mono {{ in_array($folder_perms[$folder], ['0775', '0755']) ? 'text-green-600' : 'text-red-500' }}">
                                    {{ $folder_perms[$folder] }}
                                </flux:text>
                                @if(!in_array($folder_perms[$folder], ['0775', '0755', 'missing']))
                                    <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="subtle"
                                        icon="wrench" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <!-- Step 2: Database -->
        <div class="p-6 sm:p-8 bg-zinc-50/30 dark:bg-zinc-800/10">
            <div class="flex items-center gap-3 mb-6">
                <div
                    class="flex items-center justify-center size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                    2</div>
                <div>
                    <flux:heading size="lg">{{ __('Database Initialization') }}</flux:heading>
                    <flux:subheading>{{ __('Run system migrations and seeders.') }}</flux:subheading>
                </div>
            </div>

            <div class="p-5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900/50">
                <div class="flex flex-col sm:flex-row gap-5 items-start">
                    <div class="mt-1 hidden sm:block">
                        <flux:icon name="circle-stack" class="size-8 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <div class="flex-1 space-y-4 w-full">
                        <flux:text size="sm" class="text-zinc-600 dark:text-zinc-400">
                            {{ __('This action will create all necessary tables and insert default roles. Ensure your database is empty to avoid conflicts.') }}
                        </flux:text>
                        <flux:button wire:click="initializeDatabase" variant="primary" :disabled="!$db_connected"
                            icon="play">
                            {{ __('Initialize Database') }}
                        </flux:button>
                        @if($last_output)
                            <pre
                                class="p-4 bg-zinc-950 text-emerald-400 rounded-lg text-xs overflow-auto max-h-40 whitespace-pre-wrap border border-zinc-800">{{ $last_output }}</pre>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <!-- Step 3: Admin -->
        <div class="p-6 sm:p-8">
            <div class="flex items-center gap-3 mb-6">
                <div
                    class="flex items-center justify-center size-8 rounded-full bg-zinc-100 dark:bg-zinc-800 text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                    3</div>
                <div>
                    <flux:heading size="lg">{{ __('Super Admin') }}</flux:heading>
                    <flux:subheading>{{ __('Create the primary administrative account.') }}</flux:subheading>
                </div>
            </div>

            <form wire:submit="createAdmin" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <flux:field>
                        <flux:label>{{ __('Full Name') }}</flux:label>
                        <flux:input wire:model="admin_name" placeholder="System Admin" required />
                        <flux:error name="admin_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Email Address') }}</flux:label>
                        <flux:input wire:model="admin_email" type="email" placeholder="admin@example.com" required />
                        <flux:error name="admin_email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Password') }}</flux:label>
                        <flux:input wire:model="admin_password" type="password" required viewable />
                        <flux:error name="admin_password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Confirm Password') }}</flux:label>
                        <flux:input wire:model="admin_password_confirmation" type="password" required viewable />
                        <flux:error name="admin_password_confirmation" />
                    </flux:field>
                </div>

                <div class="pt-4 flex justify-end">
                    <flux:button type="submit" variant="primary" class="w-full sm:w-auto" icon="check-badge">
                        {{ __('Complete Setup') }}
                    </flux:button>
                </div>
            </form>
        </div>

    </flux:card>
</div>