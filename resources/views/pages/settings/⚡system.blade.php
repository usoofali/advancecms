<?php

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('System Configuration')] class extends Component
{
    use WithFileUploads;

    public $system_logo;

    public ?string $current_logo = null;

    public string $php_version;

    public string $environment;

    public bool $debug_mode;

    public bool $storage_link_exists;

    public int $pending_migrations = 0;

    public array $folder_perms = [];

    public array $available_seeders = [];

    public array $backups = [];

    public string $last_output = '';

    public array $target_folders = [
        'storage',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function mount(): void
    {
        if (Gate::denies('manage_system')) {
            abort(403);
        }

        $this->current_logo = Cache::rememberForever('system_logo', fn () => SystemSetting::where('key', 'system_logo')->value('value'));

        $this->refreshStats();
        $this->discoverSeeders();
        $this->fetchBackups();
    }

    public function updateLogo(): void
    {
        $this->validate([
            'system_logo' => 'image|max:2048', // 2MB Max
        ]);

        if ($this->system_logo) {
            $base64 = base64_encode(file_get_contents($this->system_logo->getRealPath()));
            SystemSetting::updateOrCreate(
                ['key' => 'system_logo'],
                ['value' => $base64]
            );

            Cache::forget('system_logo');
            $this->current_logo = $base64;
            $this->system_logo = null;

            $this->dispatch('notify', message: __('System logo updated successfully.'), variant: 'success');
        }
    }

    public function removeLogo(): void
    {
        SystemSetting::where('key', 'system_logo')->delete();
        Cache::forget('system_logo');
        $this->current_logo = null;
        $this->dispatch('notify', message: __('System logo removed.'), variant: 'success');
    }

    public function discoverSeeders(): void
    {
        $path = database_path('seeders');
        if (File::exists($path)) {
            $files = File::files($path);
            $this->available_seeders = collect($files)
                ->map(fn ($file) => $file->getFilenameWithoutExtension())
                ->filter(fn ($name) => $name !== 'DatabaseSeeder')
                ->values()
                ->toArray();
        }
    }

    public function fetchBackups(): void
    {
        $path = storage_path('app/backups');
        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $files = File::files($path);
        $this->backups = collect($files)
            ->map(function ($file) {
                return [
                    'name' => $file->getFilename(),
                    'size' => number_format($file->getSize() / 1024, 2).' KB',
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                    'timestamp' => $file->getMTime(),
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->toArray();
    }

    public function createBackup(): void
    {
        $filename = 'backup-'.date('Y-m-d-His').'.sql';
        $path = storage_path('app/backups/'.$filename);

        $conn = config('database.connections.mysql');
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            $this->fetchBackups();
            $this->dispatch('notify', message: __('Backup created successfully: :name', ['name' => $filename]), variant: 'success');
        } else {
            $this->dispatch('notify', message: __('Backup failed. Ensure mysqldump is installed and accessible.'), variant: 'danger');
        }
    }

    public function restoreBackup(string $filename): void
    {
        $path = storage_path('app/backups/'.$filename);
        if (! File::exists($path)) {
            $this->dispatch('notify', message: __('Backup file not found.'), variant: 'danger');

            return;
        }

        $conn = config('database.connections.mysql');
        $command = sprintf(
            'mysql --user=%s --password=%s --host=%s %s < %s',
            escapeshellarg($conn['username']),
            escapeshellarg($conn['password']),
            escapeshellarg($conn['host']),
            escapeshellarg($conn['database']),
            escapeshellarg($path)
        );

        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);

        if ($resultCode === 0) {
            $this->refreshStats();
            $this->dispatch('notify', message: __('Database restored successfully from :name', ['name' => $filename]), variant: 'success');
        } else {
            $this->dispatch('notify', message: __('Restoration failed. Ensure mysql CLI is accessible.'), variant: 'danger');
        }
    }

    public function deleteBackup(string $filename): void
    {
        $path = storage_path('app/backups/'.$filename);
        if (File::exists($path)) {
            File::delete($path);
            $this->fetchBackups();
            $this->dispatch('notify', message: __('Backup deleted.'), variant: 'success');
        }
    }

    public function downloadBackup(string $filename)
    {
        $path = storage_path('app/backups/'.$filename);
        if (File::exists($path)) {
            return response()->download($path);
        }
    }

    public function refreshStats(): void
    {
        $this->php_version = PHP_VERSION;
        $this->environment = app()->environment();
        $this->debug_mode = config('app.debug');
        $this->storage_link_exists = File::exists(public_path('storage'));

        // Detect pending migrations
        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            $rows = explode("\n", trim($output));
            // Filter rows that match "| No" indicating a pending migration
            $this->pending_migrations = count(array_filter($rows, fn ($row) => str_contains($row, '| No')));
        } catch (Exception $e) {
            $this->pending_migrations = 0;
        }

        $this->refreshPermissions();
    }

    public function refreshPermissions(): void
    {
        $this->folder_perms = [];
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
        if (! File::exists($path)) {
            $this->dispatch('notify', message: __(':folder is missing.', ['folder' => $folder]), variant: 'danger');

            return;
        }

        try {
            // Attempt to set 775 (0775 octal)
            if (chmod($path, 0775)) {
                $this->refreshPermissions();
                $this->dispatch('notify', message: __('Permissions for :folder set to 0775.', ['folder' => $folder]), variant: 'success');
            } else {
                throw new Exception(__('chmod failed.'));
            }
        } catch (Exception $e) {
            $this->dispatch('notify', message: __('Failed to change permissions for :folder. This might be restricted by your host.', ['folder' => $folder]), variant: 'danger');
        }
    }

    public function migrateFresh(): void
    {
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $this->last_output = Artisan::output();
            $this->refreshStats();
            $this->dispatch('notify', message: __('Database wiped and fresh migrations executed.'), variant: 'success');
        } catch (Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dispatch('notify', message: __('Fresh migration failed: ').$e->getMessage(), variant: 'danger');
        }
    }

    public function runSeeder(?string $class = null): void
    {
        try {
            $params = ['--force' => true];
            if ($class) {
                $params['--class'] = $class;
            }
            Artisan::call('db:seed', $params);
            $this->last_output = Artisan::output();
            $this->dispatch('notify', message: $class ? __(':class seeder completed.', ['class' => $class]) : __('All database seeders completed.'), variant: 'success');
        } catch (Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dispatch('notify', message: __('Seeding failed: ').$e->getMessage(), variant: 'danger');
        }
    }

    public function clearOptimization(): void
    {
        try {
            Artisan::call('optimize:clear');
            $this->last_output = Artisan::output();
            $this->dispatch('notify', message: __('Optimization cache cleared.'), variant: 'success');
        } catch (Exception $e) {
            $this->last_output = $e->getMessage();
            $this->dispatch('notify', message: __('Failed to clear optimization cache.'), variant: 'danger');
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('System Configuration') }}</flux:heading>

    <x-pages::settings.layout :heading="__('System Configuration')" :subheading="__('Manage production environment and system-wide maintenance tasks.')">
        <div class="space-y-6">
            <!-- Environment Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:card class="p-4 flex flex-col justify-center items-center text-center space-y-2 border-zinc-100 dark:border-zinc-800">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('PHP Version') }}</flux:text>
                    <flux:heading size="xl" class="font-mono">{{ $php_version }}</flux:heading>
                </flux:card>

                <flux:card class="p-4 flex flex-col justify-center items-center text-center space-y-2 border-zinc-100 dark:border-zinc-800">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('Environment') }}</flux:text>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" :color="$environment === 'production' ? 'green' : 'blue'">{{ ucfirst($environment) }}</flux:badge>
                        @if($debug_mode)
                            <flux:badge size="sm" color="orange" inset="top bottom">{{ __('Debug Active') }}</flux:badge>
                        @endif
                    </div>
                </flux:card>
            </div>

            <!-- System Branding -->
            <flux:card class="space-y-4 border-zinc-100 dark:border-zinc-800">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('System Branding') }}</flux:heading>
                <div class="flex flex-col sm:flex-row gap-6 items-start">
                    <div class="flex-shrink-0 flex flex-col items-center gap-2">
                        <div class="size-24 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-700 flex items-center justify-center bg-zinc-50 dark:bg-zinc-900 overflow-hidden">
                            @if($system_logo)
                                <img src="{{ $system_logo->temporaryUrl() }}" class="w-full h-full object-contain p-2" />
                            @elseif($current_logo)
                                <img src="data:image/png;base64,{{ $current_logo }}" class="w-full h-full object-contain p-2" />
                            @else
                                <flux:icon.photo class="size-8 text-zinc-300 dark:text-zinc-600" />
                            @endif
                        </div>
                        @if($current_logo && !$system_logo)
                            <flux:button wire:click="removeLogo" size="xs" variant="danger" icon="trash">{{ __('Remove Logo') }}</flux:button>
                        @endif
                    </div>
                    
                    <div class="flex-1 space-y-4 w-full">
                        <form wire:submit="updateLogo" class="space-y-4">
                            <flux:field>
                                <flux:label>{{ __('Upload New Logo') }}</flux:label>
                                <flux:input type="file" wire:model="system_logo" accept="image/*" />
                                <flux:error name="system_logo" />
                                <flux:description>{{ __('Recommended size: 200x200px. Max: 2MB. Updates the application icon globally.') }}</flux:description>
                            </flux:field>
                            
                            <flux:button type="submit" variant="primary" :disabled="!$system_logo" wire:loading.attr="disabled">{{ __('Save Logo') }}</flux:button>
                        </form>
                    </div>
                </div>
            </flux:card>

            <!-- Detailed Status Indicators -->
            <flux:card class="space-y-4 border-zinc-100 dark:border-zinc-800">
                <div class="flex items-center justify-between py-2 border-b border-zinc-50 dark:border-zinc-800/50">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:icon.link class="size-4 text-zinc-400" />
                        </div>
                        <div>
                            <flux:heading size="sm">{{ __('Storage Link') }}</flux:heading>
                            <flux:text size="xs">{{ __('Requirement for public file accessibility.') }}</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge size="sm" :color="$storage_link_exists ? 'green' : 'red'" inset="top bottom">
                            {{ $storage_link_exists ? __('Healthy') : __('Broken/Missing') }}
                        </flux:badge>
                        @if(!$storage_link_exists)
                            <flux:button wire:click="createStorageLink" size="xs" variant="primary">{{ __('Fix Now') }}</flux:button>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between py-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                            <flux:icon.table-cells class="size-4 text-zinc-400" />
                        </div>
                        <div>
                            <flux:heading size="sm">{{ __('Database Migrations') }}</flux:heading>
                            <flux:text size="xs">{{ __('Consistency between code and database structure.') }}</flux:text>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($pending_migrations > 0)
                            <flux:badge size="sm" color="orange" class="font-bold">{{ $pending_migrations }} {{ __('Pending') }}</flux:badge>
                            <flux:button wire:click="runMigrations" size="xs" variant="primary" icon="play">{{ __('Upgrade') }}</flux:button>
                        @else
                            <flux:badge size="sm" color="green" inset="top bottom">{{ __('Up to date') }}</flux:badge>
                        @endif
                    </div>
                </div>
            </flux:card>

            <!-- Directory Permissions -->
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Directory Permissions') }}</flux:heading>
                <flux:card class="divide-y divide-zinc-100 dark:divide-zinc-800 p-0 border-zinc-100 dark:border-zinc-800 overflow-hidden">
                    @foreach($target_folders as $folder)
                    <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-900/50">
                        <div class="flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                                <flux:icon.folder class="size-4 text-zinc-400" />
                            </div>
                            <div>
                                <flux:heading size="sm" class="font-mono text-xs">{{ $folder }}</flux:heading>
                                <flux:text size="xs">{{ __('Current: ') }} <span class="font-mono font-bold @if($folder_perms[$folder] === '0775' || $folder_perms[$folder] === '0755') text-green-600 @else text-orange-600 @endif">{{ $folder_perms[$folder] ?? '---' }}</span></flux:text>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($folder_perms[$folder] !== '0775' && $folder_perms[$folder] !== 'missing')
                                <flux:button wire:click="fixPermission('{{ $folder }}')" size="xs" variant="subtle" icon="wrench-screwdriver" class="text-[10px]">{{ __('Fix (775)') }}</flux:button>
                            @elseif($folder_perms[$folder] === 'missing')
                                <flux:badge size="sm" color="red" inset="top bottom">{{ __('Missing') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green" icon="check" inset="top bottom">{{ __('Optimal') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </flux:card>
            </div>

            <!-- Maintenance & Seeders -->
            <div class="space-y-4">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Maintenance & Seeding') }}</flux:heading>
                
                <div class="grid grid-cols-1 gap-4">
                    <flux:card class="p-4 space-y-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-start gap-3">
                            <flux:icon.sparkles class="size-5 text-blue-500" />
                            <div class="flex-1">
                                <flux:heading size="sm">{{ __('Optimization') }}</flux:heading>
                                <flux:text size="xs">{{ __('Clear configuration, routing, and application caches to reflect recent changes.') }}</flux:text>
                            </div>
                            <flux:button wire:click="clearOptimization" size="sm" variant="subtle">{{ __('Clear All Caches') }}</flux:button>
                        </div>
                    </flux:card>

                    <flux:card class="p-4 space-y-4 border-zinc-100 dark:border-zinc-800">
                        <div class="flex items-start gap-3 border-b border-zinc-50 dark:border-zinc-800 pb-4">
                            <flux:icon.beaker class="size-5 text-purple-500" />
                            <div class="flex-1">
                                <flux:heading size="sm">{{ __('Database Seeding') }}</flux:heading>
                                <flux:text size="xs">{{ __('Populate the database with initial or dummy data.') }}</flux:text>
                            </div>
                            <flux:button wire:click="runSeeder()" size="sm" variant="subtle" icon="play-circle">{{ __('Run Base Seeds') }}</flux:button>
                        </div>

                        <div class="space-y-3">
                            <flux:text size="xs" weight="medium" class="text-zinc-500 uppercase tracking-wider">{{ __('Selective Seeders') }}</flux:text>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($available_seeders as $seeder)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-100 dark:border-zinc-800">
                                    <flux:text size="xs" class="font-mono truncate mr-2">{{ $seeder }}</flux:text>
                                    <flux:button wire:click="runSeeder('{{ $seeder }}')" size="xs" variant="ghost" icon="play" class="shrink-0" />
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </flux:card>
                </div>
            </div>

            <!-- Data Backups -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-zinc-400">{{ __('Data Backups') }}</flux:heading>
                    <flux:button wire:click="createBackup" size="xs" variant="primary" icon="plus">{{ __('Create New Backup') }}</flux:button>
                </div>

                <flux:card class="p-0 border-zinc-100 dark:border-zinc-800 overflow-hidden">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Filename') }}</flux:table.column>
                            <flux:table.column>{{ __('Size') }}</flux:table.column>
                            <flux:table.column>{{ __('Created At') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse($backups as $backup)
                                <flux:table.row :key="$backup['name']">
                                    <flux:table.cell class="font-mono text-xs">{{ $backup['name'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $backup['size'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $backup['created_at'] }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex items-center justify-end gap-2">
                                            <flux:button wire:click="downloadBackup('{{ $backup['name'] }}')" size="xs" variant="ghost" icon="arrow-down-tray" tooltip="{{ __('Download') }}" />

                                            <flux:modal.trigger name="confirm-restore-{{ str_replace('.', '-', $backup['name']) }}">
                                                <flux:button size="xs" variant="subtle" icon="arrow-path" tooltip="{{ __('Restore') }}" />
                                            </flux:modal.trigger>

                                            <flux:button wire:click="deleteBackup('{{ $backup['name'] }}')" wire:confirm="{{ __('Are you sure you want to delete this backup?') }}" size="xs" variant="ghost" icon="trash" color="red" tooltip="{{ __('Delete') }}" />

                                            <flux:modal name="confirm-restore-{{ str_replace('.', '-', $backup['name']) }}" class="min-w-[22rem] space-y-6">
                                                <div class="space-y-2">
                                                    <flux:heading size="lg">{{ __('Confirm Restoration') }}</flux:heading>
                                                    <flux:subheading>{{ __('Are you sure you want to restore the database from :name? This will overwrite all current data.', ['name' => $backup['name']]) }}</flux:subheading>
                                                </div>

                                                <div class="flex gap-2">
                                                    <flux:spacer />
                                                    <flux:modal.close>
                                                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                                    </flux:modal.close>
                                                    <flux:button wire:click="restoreBackup('{{ $backup['name'] }}')" variant="danger">{{ __('Yes, Restore Now') }}</flux:button>
                                                </div>
                                            </flux:modal>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="4" class="text-center py-8 text-zinc-400">
                                        {{ __('No backups found.') }}
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            </div>

            <!-- Danger Zone -->
            <div class="space-y-4 pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:heading size="sm" weight="semibold" class="uppercase tracking-wider text-red-500">{{ __('Danger Zone') }}</flux:heading>
                
                <flux:card class="p-4 bg-red-50/50 dark:bg-red-950/10 border-red-100 dark:border-red-900/50 space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="p-2 rounded-lg bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-500">
                            <flux:icon.fire class="size-6" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="sm" class="text-red-900 dark:text-red-400">{{ __('Fresh Reinstall') }}</flux:heading>
                            <flux:text size="xs" class="text-red-700 dark:text-red-500/80">
                                {{ __('This will drop all tables and re-run all migrations. ALL DATA WILL BE LOST PERMANENTLY. Use with extreme caution.') }}
                            </flux:text>
                        </div>
                        <flux:modal.trigger name="confirm-migrate-fresh">
                            <flux:button variant="danger" size="sm" icon="exclamation-triangle">
                                {{ __('Migrate Fresh') }}
                            </flux:button>
                        </flux:modal.trigger>
                    </div>

                    <flux:modal name="confirm-migrate-fresh" class="min-w-[22rem] space-y-6">
                        <div class="space-y-2">
                            <flux:heading size="lg">{{ __('Confirm Fresh Migration') }}</flux:heading>
                            <flux:subheading>{{ __('Are you absolutely sure? This will permanently delete all data in the database. This action cannot be undone.') }}</flux:subheading>
                        </div>

                        <div class="flex gap-2">
                            <flux:spacer />
                            <flux:modal.close>
                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                            </flux:modal.close>
                            <flux:button wire:click="migrateFresh" variant="danger">{{ __('Yes, Delete Everything') }}</flux:button>
                        </div>
                    </flux:modal>
                </flux:card>
            </div>

            <!-- Terminal Output (Optional/Last Action) -->
            @if($last_output)
            <div class="mt-8 space-y-2">
                <div class="flex items-center justify-between">
                    <flux:text size="xs" class="uppercase tracking-widest text-zinc-400 font-bold">{{ __('Last Command Output') }}</flux:text>
                    <flux:button variant="ghost" size="xs" wire:click="$set('last_output', '')">{{ __('Clear Output') }}</flux:button>
                </div>
                <pre class="p-4 bg-zinc-900 text-zinc-300 rounded-xl font-mono text-[10px] overflow-x-auto whitespace-pre-wrap leading-relaxed shadow-inner">{{ $last_output }}</pre>
            </div>
            @endif

            <!-- Warning for Shared Hosting -->
            <div class="p-4 rounded-xl bg-orange-50 dark:bg-orange-950/20 border border-orange-100 dark:border-orange-900/50 flex gap-3">
                <flux:icon.exclamation-triangle class="size-5 text-orange-600 dark:text-orange-500 shrink-0 mt-0.5" />
                <div>
                    <flux:text size="xs" class="text-orange-800 dark:text-orange-300 font-bold">{{ __('Shared Hosting Tip') }}</flux:text>
                    <flux:text size="xs" class="text-orange-700 dark:text-orange-400 mt-1">
                        {{ __('Some systems may restrict symlink creation or CLI execution. If "Fix Now" fails, you might need to contact support to create the storage link manually.') }}
                    </flux:text>
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
