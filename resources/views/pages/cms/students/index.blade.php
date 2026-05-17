<?php

use App\Exports\StudentsExport;
use App\Imports\StudentsImport;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] #[Title('Students')] class extends Component {
    use WithFileUploads, WithPagination;

    public string $search = '';

    public $filterInstitution = '';

    public $filterDepartment = '';

    public $filterProgram = '';

    public $filterLevel = '';

    public $filterStatus = '';

    public int|string|null $deletingId = null;

    public $importFile = null;

    /** @var array<int, string> */
    public array $importFailures = [];

    public int $importedCount = 0;

    public bool $isHod = false;

    public function mount(): void
    {
        Gate::authorize('students.view');

        $user = auth()->user();

        // Super Admins don't have these locked
        if ($user->hasRole('Super Admin')) {
            return;
        }

        // Lock to user's institution
        $this->filterInstitution = $user->institution_id;

        // 1. Check if user has a scoped role for a specific department
        $scopedDeptIds = array_merge(
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class)
        );

        if (!empty($scopedDeptIds)) {
            $this->isHod = true; // Reusing this flag to lock the department dropdown
            $this->filterDepartment = $scopedDeptIds[0];
            return;
        }

        // 2. Fallback: Legacy check for HOD via hod_id column
        $staff = \App\Models\Staff::where('email', $user->email)->first();
        if ($staff) {
            $hodDept = \App\Models\Department::where('hod_id', $staff->id)->first();
            if ($hodDept) {
                $this->isHod = true;
                $this->filterDepartment = $hodDept->id;
            }
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterInstitution(): void
    {
        $this->filterDepartment = '';
        $this->filterProgram = '';
        $this->resetPage();
    }

    public function updatedFilterDepartment(): void
    {
        $this->filterProgram = '';
        $this->resetPage();
    }

    public function updatedFilterProgram(): void
    {
        $this->resetPage();
    }

    public function updatedFilterLevel(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        Gate::authorize('students.export');

        return (new StudentsExport($this->getFilteredQuery()))->download();
    }

    public function import(): void
    {
        Gate::authorize('students.import');

        $institutionId = $this->filterInstitution ?: auth()->user()->institution_id;

        if (!$institutionId) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please select an institution before importing.',
            ]);
            return;
        }

        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $importer = new StudentsImport($institutionId);
        $importer->import($this->importFile->getRealPath());

        $this->importedCount = $importer->imported;
        $this->importFailures = $importer->failures;
        $this->importFile = null;

        if ($this->importedCount > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "{$this->importedCount} student(s) imported successfully.",
            ]);
        }
    }

    public function confirmDelete(): void
    {
        Gate::authorize('students.delete');

        if (!$this->deletingId) {
            return;
        }

        $student = Student::find($this->deletingId);
        if ($student) {
            $student->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Student record deleted successfully.',
            ]);
        }

        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-student');
    }

    private function getFilteredQuery()
    {
        $activeSession = \App\Models\AcademicSession::where('status', 'active')->first();

        return Student::query()
            ->with(['program.department.institution'])
            ->when($this->filterInstitution ?: auth()->user()->institution_id, fn($q, $id) => $q->where('institution_id', $id))
            ->when($this->filterDepartment, function ($q) {
                $q->whereHas('program', fn($pq) => $pq->where('department_id', $this->filterDepartment));
            })
            ->when($this->filterProgram, fn($q) => $q->where('program_id', $this->filterProgram))
            ->when($this->filterLevel, function ($q) use ($activeSession) {
                if ($activeSession) {
                    return $q->whereRaw("entry_level + (CAST(SUBSTRING_INDEX(?, '/', 1) AS UNSIGNED) - admission_year) * 100 = ?", [
                        $activeSession->name,
                        $this->filterLevel
                    ]);
                }
                return $q->where('entry_level', $this->filterLevel);
            })
            ->when($this->filterStatus, fn($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q) {
                $q->where(function ($sq) {
                    $sq->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('matric_number', 'like', "%{$this->search}%");
                });
            });
    }

    public function with(): array
    {
        $activeSession = \App\Models\AcademicSession::where('status', 'active')->first();

        return [
            'activeSession' => $activeSession,
            'students' => $this->getFilteredQuery()->orderBy('matric_number')->paginate(15),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Students') }}</flux:heading>
            <flux:subheading>
                @php
                    $activeFilters = [];
                    if ($filterInstitution)
                        $activeFilters[] = \App\Models\Institution::find($filterInstitution)?->name;
                    if ($filterDepartment)
                        $activeFilters[] = \App\Models\Department::find($filterDepartment)?->name;
                    if ($filterProgram)
                        $activeFilters[] = \App\Models\Program::find($filterProgram)?->name;
                    if ($filterLevel)
                        $activeFilters[] = "Level " . $filterLevel;
                    if ($filterStatus)
                        $activeFilters[] = ucfirst($filterStatus);
                    $filterText = !empty($activeFilters) ? implode(' / ', $activeFilters) : __('All student records');
                @endphp
                {{ $filterText }}
            </flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:button icon="printer" variant="ghost" target="_blank" :href="route('cms.students.print', [
                'institution_id' => $filterInstitution,
                'department_id' => $filterDepartment,
                'program_id' => $filterProgram,
                'level' => $filterLevel,
                'status' => $filterStatus,
                'search' => $search
            ])">
                {{ __('Print List') }}
            </flux:button>
            @can('students.export')
                <flux:button icon="arrow-down-tray" wire:click="export">{{ __('Export CSV') }}</flux:button>
            @endcan
            @can('students.import')
                <flux:button icon="arrow-up-tray" x-on:click="$flux.modal('import-students').show()">
                    {{ __('Import CSV') }}
                </flux:button>
            @endcan
            @can('students.create')
                <flux:button icon="plus" variant="primary" :href="route('cms.students.create')" wire:navigate>
                    {{ __('Add Student') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div
        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 @if(auth()->user()->hasRole('Super Admin')) xl:grid-cols-6 @elseif($isHod) xl:grid-cols-4 @else xl:grid-cols-5 @endif gap-4">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Search students...')" />

        @if(auth()->user()->hasRole('Super Admin'))
            <flux:select wire:model.live="filterInstitution" :placeholder="__('All Institutions')">
                <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                @foreach(\App\Models\Institution::all() as $inst)
                    <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        @if(!$isHod)
            <flux:select wire:model.live="filterDepartment" :placeholder="__('All Departments')"
                :disabled="!$filterInstitution && !auth()->user()->institution_id">
                <flux:select.option value="">{{ __('All Departments') }}</flux:select.option>
                @php
                    $instId = $filterInstitution ?: auth()->user()->institution_id;
                    $depts = $instId ? \App\Models\Department::where('institution_id', $instId)->get() : collect();
                @endphp
                @foreach($depts as $dept)
                    <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="filterProgram" :placeholder="__('All Programs')" :disabled="!$filterDepartment">
            <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
            @php
                $progs = $filterDepartment ? \App\Models\Program::where('department_id', $filterDepartment)->get() :
                    collect();
            @endphp
            @foreach($progs as $prog)
                <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterLevel" :placeholder="__('All Levels')">
            <flux:select.option value="">{{ __('All Levels') }}</flux:select.option>
            @foreach([100, 200, 300] as $lvl)
                <flux:select.option :value="$lvl">{{ $lvl }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="filterStatus" :placeholder="__('All Statuses')">
            <flux:select.option value="">{{ __('All Statuses') }}</flux:select.option>
            <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
            <flux:select.option value="graduated">{{ __('Graduated') }}</flux:select.option>
            <flux:select.option value="suspended">{{ __('Suspended') }}</flux:select.option>
            <flux:select.option value="withdrawn">{{ __('Withdrawn') }}</flux:select.option>
        </flux:select>
    </div>

    <flux:table :paginate="$students">
        <flux:table.columns>
            <flux:table.column>{{ __('Matric Number') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Program') }}</flux:table.column>
            <flux:table.column>{{ __('Level') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($students as $student)
                <flux:table.row wire:key="{{ $student->id }}">
                    <flux:table.cell class="font-medium font-mono uppercase">
                        <a href="{{ route('cms.students.show', $student) }}" wire:navigate
                            class="hover:text-blue-600 transition-colors">
                            {{ $student->matric_number }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <div
                                class="h-10 w-10 flex-shrink-0 rounded-full bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 overflow-hidden flex items-center justify-center">
                                @if ($student->photo_path)
                                    <img src="{{ $student->photo_url }}" class="h-full w-full object-cover">
                                @else
                                    <flux:icon icon="user" class="h-5 w-5 text-zinc-400" />
                                @endif
                            </div>
                            <div>
                                <a href="{{ route('cms.students.show', $student) }}" wire:navigate
                                    class="font-medium text-zinc-900 dark:text-zinc-100 hover:text-blue-600 transition-colors">
                                    {{ $student->full_name }}
                                </a>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $student->email }}</div>
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400">
                        {{ $student->program->name }}
                        <div class="text-xs text-zinc-500 mt-0.5">{{ $student->program->department->institution->acronym }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-600 dark:text-zinc-400 font-mono">
                        {{ $activeSession ? $student->currentLevel($activeSession) : $student->entry_level }}L
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="match($student->status) {
                                    'active' => 'green',
                                    'graduated' => 'indigo',
                                    'suspended' => 'orange',
                                    'withdrawn' => 'red',
                                    default => 'zinc'
                                }" size="sm">
                            {{ ucfirst($student->status) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            <flux:button size="sm" variant="ghost" icon="pencil"
                                :href="route('cms.students.edit', $student)" wire:navigate />
                            <flux:button size="sm" variant="ghost" icon="trash"
                                x-on:click="$wire.deletingId = {{ $student->id }}; $flux.modal('delete-student').show()" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No students found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Import Modal --}}
    <flux:modal name="import-students" variant="filled" class="min-w-[28rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Students from CSV') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload a CSV file to bulk import student records.') }}
                    <a href="/templates/students-import-template.csv" class="text-accent underline" download>
                        {{ __('Download template') }}
                    </a>
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="importFile" accept=".csv,text/csv" :label="__('CSV File')" />
            <flux:error name="importFile" />

            @if (!empty($importFailures))
                <div
                    class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-4 space-y-1 max-h-48 overflow-y-auto">
                    <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ count($importFailures) }} {{ __('row(s)
                        failed:') }}</p>
                    @foreach ($importFailures as $failure)
                        <p class="text-xs text-red-600 dark:text-red-500">{{ $failure }}</p>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    {{ __('Import') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Modal --}}
    <flux:modal name="delete-student" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Student Record?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This action cannot be undone. All result history and registrations for this student will be
                    permanently removed.') }}
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