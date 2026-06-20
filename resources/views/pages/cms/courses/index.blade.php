<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Staff;
use App\Exports\CoursesExport;
use App\Imports\CoursesImport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Courses')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';
    public ?int $institutionId = null;
    public ?int $departmentId = null;
    public ?int $programId = null;
    public ?int $level = null;
    public ?int $semester = null;

    public bool $isHod = false;
    public array $hodDepartmentIds = [];

    public int|string|null $deletingId = null;
    public $importFile = null;
    /** @var array<int, string> */
    public array $importFailures = [];
    public int $importedCount = 0;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->cannot('courses.view') && $user->cannot('courses.view_dept')) {
            abort(403, 'Unauthorized action.');
        }

        // 1. Check if user has a scoped role for specific departments (new polymorphic system)
        $scopedDeptIds = array_unique(array_merge(
            $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
            $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
            $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
        ));

        if (!empty($scopedDeptIds)) {
            $this->hodDepartmentIds = $scopedDeptIds;
            $this->isHod = true;
            if (count($scopedDeptIds) === 1) {
                $this->departmentId = $scopedDeptIds[0];
            }
            return;
        }

        // 2. Fallback: Legacy check via hod_id column
        $staff = Staff::where('email', $user->email)->first();

        if ($staff) {
            $this->hodDepartmentIds = Department::where('hod_id', $staff->id)->pluck('id')->toArray();
            if (!empty($this->hodDepartmentIds)) {
                $this->isHod = true;
                // If only one department, auto-select it
                if (count($this->hodDepartmentIds) === 1) {
                    $this->departmentId = $this->hodDepartmentIds[0];
                }
            }
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedInstitutionId(): void
    {
        $this->departmentId = null;
        $this->programId = null;
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->programId = null;
        $this->resetPage();
    }

    public function updatedProgramId(): void
    {
        $this->resetPage();
    }

    public function updatedLevel(): void
    {
        $this->resetPage();
    }

    public function updatedSemester(): void
    {
        $this->resetPage();
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('courses.export');
        return (new CoursesExport(auth()->user()->institution_id))->download();
    }

    public function import(): void
    {
        Gate::authorize('courses.import');
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $importer = new CoursesImport(auth()->user()->institution_id);
        $importer->import($this->importFile->getRealPath());

        $this->importedCount = $importer->imported;
        $this->importFailures = $importer->failures;
        $this->importFile = null;

        if ($this->importedCount > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "{$this->importedCount} course(s) imported successfully.",
            ]);
        }
    }

    public function confirmDelete(): void
    {
        Gate::authorize('courses.delete');

        if (!$this->deletingId) {
            return;
        }

        $course = Course::find($this->deletingId);
        if ($course) {
            $course->delete();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Course deleted successfully.',
            ]);
        }

        $this->deletingId = null;
        $this->dispatch('modal-close', name: 'delete-course');
    }

    public function with(): array
    {
        $user = auth()->user();
        $institutionId = $user->institution_id ?: $this->institutionId;

        $departmentsQuery = Department::query()->when($institutionId, fn($q) => $q->where('institution_id', $institutionId));
        if ($this->isHod) {
            $departmentsQuery->whereIn('id', $this->hodDepartmentIds);
        }
        $departments = $departmentsQuery->get();

        return [
            'institutions' => $user->institution_id ? [] : Institution::all(),
            'departments' => $departments,
            'programs' => Program::when($this->departmentId, fn($q) => $q->where('department_id', $this->departmentId))
                ->when($this->isHod && !$this->departmentId, fn($q) => $q->whereIn('department_id', $this->hodDepartmentIds))
                ->get(),
            'levels' => Course::query()
                ->when($institutionId, fn($q) => $q->where('institution_id', $institutionId))
                ->when($this->isHod, fn($q) => $q->whereIn('department_id', $this->hodDepartmentIds))
                ->distinct()
                ->orderBy('level')
                ->pluck('level')
                ->filter()
                ->values(),
            'semesters' => [1 => '1st Semester', 2 => '2nd Semester'],
            'courses' => Course::query()
                ->with('department.institution')
                ->when($institutionId, fn($q) => $q->where('institution_id', $institutionId))
                ->when($this->isHod && !$this->departmentId, fn($q) => $q->whereIn('department_id', $this->hodDepartmentIds))
                ->when($this->departmentId, fn($q) => $q->where('department_id', $this->departmentId))
                ->when($this->programId, fn($q) => $q->where('program_id', $this->programId))
                ->when($this->level, fn($q) => $q->where('level', $this->level))
                ->when($this->semester, fn($q) => $q->where('semester', $this->semester))
                ->when($this->search, function ($q) {
                    $q->where(fn($sq) => $sq->where('title', 'like', "%{$this->search}%")
                        ->orWhere('course_code', 'like', "%{$this->search}%"));
                })
                ->latest()
                ->paginate(15),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Courses') }}</flux:heading>
            <flux:subheading>{{ __('Manage course offerings') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('courses.export')
                <flux:button icon="arrow-down-tray" wire:click="export" class="flex-1 sm:flex-none">{{ __('Export CSV') }}
                </flux:button>
            @endcan
            @can('courses.import')
                <flux:button icon="arrow-up-tray" x-on:click="$flux.modal('import-courses').show()"
                    class="flex-1 sm:flex-none">
                    {{ __('Import CSV') }}
                </flux:button>
            @endcan
            @can('courses.create')
                <flux:button icon="plus" variant="primary" :href="route('cms.courses.create')" wire:navigate
                    class="w-full sm:w-auto">
                    {{ __('Add Course') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
        <div class="md:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="__('Search courses by title or code...')" />
        </div>

        @if(!auth()->user()->institution_id)
            <flux:select wire:model.live="institutionId" :label="__('Institution')">
                <option value="">{{ __('All Institutions') }}</option>
                @foreach($institutions as $institution)
                    <option value="{{ $institution->id }}">{{ $institution->acronym }}</option>
                @endforeach
            </flux:select>
        @endif

        @if(!$this->isHod || count($this->hodDepartmentIds) > 1)
            <flux:select wire:model.live="departmentId" :label="__('Department')">
                <option value="">{{ $this->isHod ? __('All My Departments') : __('All Departments') }}</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </flux:select>
        @endif

        <flux:select wire:model.live="programId" :label="__('Program')" :disabled="!$departmentId">
            <option value="">{{ __('All Programs') }}</option>
            @foreach($programs as $prog)
                <option value="{{ $prog->id }}">{{ $prog->name }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="level" :label="__('Level')">
            <option value="">{{ __('All Levels') }}</option>
            @foreach($levels as $lvl)
                <option value="{{ $lvl }}">{{ $lvl }}L</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="semester" :label="__('Semester')">
            <option value="">{{ __('All Semesters') }}</option>
            @foreach($semesters as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$courses">
        <flux:table.columns>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Title') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Units') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Level') }}</flux:table.column>
            <flux:table.column class="text-center">{{ __('Semester') }}</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">{{ __('Department') }}</flux:table.column>
            @canany(['courses.edit', 'courses.delete'])
                <flux:table.column class="text-right">{{ __('Actions') }}</flux:table.column>
            @endcanany
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($courses as $course)
                <flux:table.row wire:key="{{ $course->id }}">
                    <flux:table.cell class="font-medium font-mono uppercase">
                        <a href="{{ route('cms.courses.show', $course) }}" wire:navigate
                            class="text-blue-600 dark:text-blue-400 hover:underline font-black">
                            {{ $course->course_code }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell class="text-sm">
                        <div class="flex flex-col gap-1 items-start">
                            <a href="{{ route('cms.courses.show', $course) }}" wire:navigate
                                class="text-zinc-900 dark:text-zinc-100 hover:text-blue-600 dark:hover:text-blue-400 hover:underline transition-colors">
                                {{ $course->title }}
                            </a>
                            @if($course->course_type)
                                <flux:badge size="sm" variant="pill" color="zinc">{{ ucfirst($course->course_type) }}</flux:badge>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="text-center font-mono">
                        {{ $course->credit_unit }}
                    </flux:table.cell>
                    <flux:table.cell class="text-center font-mono">
                        {{ $course->level }}L
                    </flux:table.cell>
                    <flux:table.cell class="text-center">
                        {{ $course->semester == 1 ? '1st' : '2nd' }}
                    </flux:table.cell>
                    <flux:table.cell class="hidden lg:table-cell">
                        <div class="text-sm font-medium">{{ $course->department->name }}</div>
                        <div class="text-xs text-zinc-500 mt-0.5">{{ $course->department->institution->acronym }}</div>
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <div class="flex items-center justify-end gap-2">
                            @can('courses.edit')
                                <flux:button size="sm" variant="ghost" icon="pencil"
                                    :href="route('cms.courses.edit', $course)" wire:navigate />
                            @endcan
                            @can('courses.delete')
                                <flux:button size="sm" variant="ghost" icon="trash"
                                    x-on:click="$wire.deletingId = {{ $course->id }}; $flux.modal('delete-course').show()" />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No courses found.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>


    {{-- Import Modal --}}
    <flux:modal name="import-courses" variant="filled" class="min-w-[28rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Courses from CSV') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload a CSV file to bulk import course records.') }}
                    <a href="/templates/courses-import-template.csv" class="text-accent underline" download>
                        {{ __('Download template') }}
                    </a>
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="importFile" accept=".csv,text/csv" :label="__('CSV File')" />
            <flux:error name="importFile" />

            @if (!empty($importFailures))
                <div
                    class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-4 space-y-1 max-h-48 overflow-y-auto">
                    <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ count($importFailures) }}
                        {{ __('row(s) failed:') }}</p>
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
    <flux:modal name="delete-course" variant="filled" class="min-w-[22rem]">
        <form wire:submit="confirmDelete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Course?') }}</flux:heading>
                <flux:subheading>
                    {{ __('This action cannot be undone. All student registrations and results associated with this course will be permanently removed.') }}
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