<?php

use App\Imports\StaffImport;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Role;
use App\Models\Staff;
use App\Services\ActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Staff Management')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';
    public int|string $role_id = '';
    public int|string $filterInstitution = '';
    public int|string|null $deletingId = null;
    public array $scopedDepartmentIds = [];
    public $importFile = null;
    public array $importFailures = [];
    public int $importedCount = 0;

    public function mount(): void
    {
        Gate::authorize('staff.view');

        $user = auth()->user();

        // 1. Check new polymorphic scoped roles
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));

        if (!empty($scopedDeptIds)) {
            $this->scopedDepartmentIds = $scopedDeptIds;
            return;
        }

        // 2. Fallback: Legacy hod_id check
        $staff = Staff::where('email', $user->email)->first();
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

    public function import(): void
    {
        Gate::authorize('staff.import');

        $institutionId = $this->filterInstitution ?: auth()->user()->institution_id;

        if (! $institutionId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('Please select an institution before importing.'),
            ]);

            return;
        }

        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120']]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $importer = new StaffImport($institutionId);
        $importer->import($this->importFile->getRealPath());

        $this->importedCount = $importer->imported;
        $this->importFailures = $importer->failures;
        $this->importFile = null;

        ActivityLogger::log(
            action: 'imported',
            module: 'Staff',
            description: "Bulk imported {$this->importedCount} staff records from file"
        );

        if ($this->importedCount > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __(':count staff member(s) imported successfully.', ['count' => $this->importedCount]),
            ]);
        }
    }

    public function confirmDelete(): void
    {
        Gate::authorize('staff.delete');

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
                ->when(!empty($this->scopedDepartmentIds), fn($q) => $q->where(function ($sub) {
                    // Filter: show staff who are HOD of the scoped departments
                    $sub->whereHas('hodDepartments', fn($dq) => $dq->whereIn('id', $this->scopedDepartmentIds));
                }))
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
            'institutions' => auth()->user()->institution_id
                ? collect()
                : Institution::query()->where('status', 'active')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Staff Directory') }}</flux:heading>
            <flux:subheading>{{ __('Manage academic and non-academic staff members') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('staff.import')
                <flux:modal.trigger name="import-staff">
                    <flux:button icon="arrow-up-tray" variant="outline" class="w-full sm:w-auto">
                        {{ __('Import Excel/CSV') }}
                    </flux:button>
                </flux:modal.trigger>
            @endcan
            @can('staff.create')
                <flux:button icon="plus" variant="primary" :href="route('cms.staff.create')" wire:navigate class="w-full sm:w-auto">
                    {{ __('Add Staff') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search staff...')"
            class="w-full sm:max-w-md" />
        <flux:select wire:model.live="role_id" class="w-full sm:max-w-xs">
            <flux:select.option value="">{{ __('All Roles') }}</flux:select.option>
            @foreach ($roles as $role)
            <flux:select.option :value="$role->role_id">{{ $role->role_name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$staffMembers">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Staff ID') }}</flux:table.column>
            <flux:table.column>{{ __('Contact') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Designation') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($staffMembers as $staff)
                <flux:table.row wire:key="{{ $staff->id }}">
                    <flux:table.cell>
                        <a href="{{ route('cms.staff.show', $staff->id) }}" wire:navigate class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                            {{ $staff->first_name }} {{ $staff->last_name }}
                        </a>
                        <div class="mt-0.5">
                            <a href="{{ route('cms.staff.show', $staff->id) }}" wire:navigate class="text-xs text-zinc-500 dark:text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 font-mono uppercase transition-colors">
                                {{ $staff->staff_number }}
                            </a>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="font-mono uppercase">
                        <a href="{{ route('cms.staff.show', $staff->id) }}" wire:navigate class="text-zinc-600 dark:text-zinc-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                            {{ $staff->staff_number }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        <div>{{ $staff->email }}</div>
                        <div class="text-xs">{{ $staff->phone }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $staff->role->role_name ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400 italic">
                        {{ $staff->designation }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$staff->status === 'active' ? 'green' : 'zinc'" size="sm">
                            {{ ucfirst($staff->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" aria-label="{{ __('Actions') }}" />
                            <flux:menu>
                                <flux:menu.item icon="eye" :href="route('cms.staff.show', $staff->id)" wire:navigate>
                                    {{ __('View Staff') }}
                                </flux:menu.item>
                                @can('staff.edit')
                                    <flux:menu.item icon="pencil-square" :href="route('cms.staff.edit', $staff->id)" wire:navigate>
                                        {{ __('Edit Staff') }}
                                    </flux:menu.item>
                                @endcan
                                @can('staff.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        x-on:click="$wire.deletingId = {{ $staff->id }}; $flux.modal('delete-staff').show()">
                                        {{ __('Delete Staff') }}
                                    </flux:menu.item>
                                @endcan
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-12 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No staff records found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Import Modal --}}
    <flux:modal name="import-staff" variant="filled" class="min-w-[28rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Staff from Excel / CSV') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload an Excel (.xlsx, .xls) or CSV file with the required staff columns.') }}
                    <a href="/templates/staff-import-template.csv" class="text-accent underline" download>
                        {{ __('Download template') }}
                    </a>
                </flux:subheading>
            </div>

            @if (! auth()->user()->institution_id)
                <flux:select wire:model="filterInstitution" :label="__('Target Institution')" required>
                    <flux:select.option value="">{{ __('Select institution...') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls" :label="__('File (Excel or CSV)')" />
            <flux:error name="importFile" />

            @if (!empty($importFailures))
                <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-4 space-y-1 max-h-48 overflow-y-auto">
                    <p class="text-sm font-medium text-red-700 dark:text-red-400">
                        {{ count($importFailures) }} {{ __('row(s) failed:') }}
                    </p>
                    @foreach ($importFailures as $failure)
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $failure }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Import Staff') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

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