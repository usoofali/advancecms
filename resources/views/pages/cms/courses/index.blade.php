<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
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

    public int|string|null $deletingId = null;
    public $importFile = null;
    /** @var array<int, string> */
    public array $importFailures = [];
    public int $importedCount = 0;

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
        return (new CoursesExport(auth()->user()->institution_id))->download();
    }

    public function import(): void
    {
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
                'type'    => 'success',
                'message' => "{$this->importedCount} course(s) imported successfully.",
            ]);
        }
    }

    public function confirmDelete(): void
    {
        if (! $this->deletingId) {
            return;
        }

        $course = Course::find($this->deletingId);
        if ($course) {
            $course->delete();
            $this->dispatch('notify', [
                'type'    => 'success',
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

        return [
            'institutions' => $user->institution_id ? [] : Institution::all(),
            'departments' => Department::when($institutionId, fn($q) => $q->where('institution_id', $institutionId))->get(),
            'programs' => Program::when($this->departmentId, fn($q) => $q->where('department_id', $this->departmentId))->get(),
            'levels' => Course::query()
                ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
                ->distinct()
                ->orderBy('level')
                ->pluck('level')
                ->filter()
                ->values(),
            'semesters' => [1 => '1st Semester', 2 => '2nd Semester'],
            'courses' => Course::query()
                ->with('department.institution')
                ->when($institutionId, fn ($q) => $q->where('institution_id', $institutionId))
                ->when($this->departmentId, fn ($q) => $q->where('department_id', $this->departmentId))
                ->when($this->programId, fn ($q) => $q->where('program_id', $this->programId))
                ->when($this->level, fn ($q) => $q->where('level', $this->level))
                ->when($this->semester, fn ($q) => $q->where('semester', $this->semester))
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
                <flux:button icon="arrow-down-tray" wire:click="export" class="flex-1 sm:flex-none">{{ __('Export CSV') }}</flux:button>
                <flux:button icon="arrow-up-tray" x-on:click="$flux.modal('import-courses').show()" class="flex-1 sm:flex-none">
                    {{ __('Import CSV') }}
                </flux:button>
                <flux:button icon="plus" variant="primary" :href="route('cms.courses.create')" wire:navigate class="w-full sm:w-auto">
                    {{ __('Add Course') }}
                </flux:button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
            <div class="md:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search courses by title or code...')" />
            </div>

            @if(!auth()->user()->institution_id)
                <flux:select wire:model.live="institutionId" :label="__('Institution')">
                    <option value="">{{ __('All Institutions') }}</option>
                    @foreach($institutions as $institution)
                        <option value="{{ $institution->id }}">{{ $institution->acronym }}</option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="departmentId" :label="__('Department')">
                <option value="">{{ __('All Departments') }}</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                @endforeach
            </flux:select>

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

        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Code') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Title') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 hidden sm:table-cell">{{ __('Units') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 hidden md:table-cell">{{ __('Level/Semester') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 hidden lg:table-cell">{{ __('Department') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($courses as $course)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $course->id }}">
                            <td class="px-4 py-4 font-medium font-mono text-sm text-zinc-900 dark:text-zinc-100 uppercase">
                                {{ $course->course_code }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">
                                {{ $course->title }}
                            </td>
                             <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 hidden sm:table-cell">
                                {{ $course->credit_unit }}
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 hidden md:table-cell">
                                {{ $course->level }}L / {{ $course->semester == 1 ? '1st' : '2nd' }} Sem
                            </td>
                            <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400 hidden lg:table-cell">
                                <div class="text-sm font-medium">{{ $course->department->name }}</div>
                                <div class="text-xs text-zinc-500 mt-0.5">{{ $course->department->institution->acronym }}</div>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" icon="pencil" :href="route('cms.courses.edit', $course)" wire:navigate />
                                    <flux:button size="sm" variant="ghost" icon="trash" x-on:click="$wire.deletingId = {{ $course->id }}; $flux.modal('delete-course').show()" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                {{ __('No courses found.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $courses->links() }}</div>

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
                    <div class="rounded-lg border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-4 space-y-1 max-h-48 overflow-y-auto">
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">{{ count($importFailures) }} {{ __('row(s) failed:') }}</p>
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
