<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Program;
use App\Models\Result;
use App\Models\Semester;
use App\Imports\ResultsImport;
use App\Services\GradingService;
use App\Services\ResultsFilterService;
use App\Services\ResultsPresentationBuilder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('View Results')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';
    public int|string $institution_id = '';
    public int|string $department_id = '';
    public int|string $program_id = '';
    public int|string $session_id = '';
    public int|string $level = '';
    public int|string $semester_id = '';
    public int|string $course_id = '';

    public $importFile = null;
    /** @var array<int, string> */
    public array $importFailures = [];
    public int $importedCount = 0;

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedInstitutionId(): void
    {
        $this->department_id = '';
        $this->program_id = '';
        $this->session_id = '';
        $this->level = '';
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedDepartmentId(): void
    {
        $this->program_id = '';
        $this->session_id = '';
        $this->level = '';
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedProgramId(): void
    {
        $this->session_id = '';
        $this->level = '';
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedSessionId(): void
    {
        $this->level = '';
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedLevel(): void
    {
        $this->semester_id = '';
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedSemesterId(): void
    {
        $this->course_id = '';
        $this->resetPage();
    }

    public function updatedCourseId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @param  int|string|null  $value
     */
    private function filterActive(mixed $value): bool
    {
        return ResultsFilterService::filterActive($value);
    }

    private function hasInstitutionContext(): bool
    {
        return (bool) auth()->user()->institution_id || $this->filterActive($this->institution_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterPayload(): array
    {
        return [
            'institution_id' => $this->institution_id,
            'department_id' => $this->department_id,
            'program_id' => $this->program_id,
            'session_id' => $this->session_id,
            'level' => $this->level,
            'semester_id' => $this->semester_id,
            'course_id' => $this->course_id,
            'search' => $this->search,
        ];
    }

    #[Computed]
    public function exportCsvUrl(): string
    {
        if (! $this->filterActive($this->semester_id)) {
            return '#';
        }

        $params = array_filter([
            'institution_id' => $this->institution_id,
            'department_id' => $this->department_id,
            'program_id' => $this->program_id,
            'session_id' => $this->session_id,
            'level' => $this->level,
            'semester_id' => $this->semester_id,
            'course_id' => $this->course_id,
            'search' => $this->search !== '' ? $this->search : null,
        ], fn ($v) => ResultsFilterService::filterActive($v));

        return route('cms.results.export.csv', $params);
    }

    /**
     * Human-readable filter chain for printed reports (institution through course).
     *
     * @return list<array{label: string, value: string}>
     */
    private function buildPrintFilterSummary(): array
    {
        $rows = [];

        $institutionId = auth()->user()->institution_id
            ?: ($this->filterActive($this->institution_id) ? (int) $this->institution_id : null);

        if ($institutionId) {
            $name = Institution::query()->find($institutionId)?->name;
            if ($name) {
                $rows[] = ['label' => __('Institution'), 'value' => $name];
            }
        }

        if ($this->filterActive($this->department_id)) {
            $rows[] = [
                'label' => __('Department'),
                'value' => Department::query()->find((int) $this->department_id)?->name ?? '—',
            ];
        }

        if ($this->filterActive($this->program_id)) {
            $rows[] = [
                'label' => __('Program'),
                'value' => Program::query()->find((int) $this->program_id)?->name ?? '—',
            ];
        }

        if ($this->filterActive($this->session_id)) {
            $rows[] = [
                'label' => __('Session'),
                'value' => AcademicSession::query()->find((int) $this->session_id)?->name ?? '—',
            ];
        }

        if ($this->filterActive($this->level)) {
            $rows[] = ['label' => __('Level'), 'value' => (string) $this->level];
        }

        if ($this->filterActive($this->semester_id)) {
            $semester = Semester::query()->find((int) $this->semester_id);
            $rows[] = [
                'label' => __('Semester'),
                'value' => $semester ? ucfirst((string) $semester->name) : '—',
            ];
        }

        if ($this->filterActive($this->course_id)) {
            $course = Course::query()->find((int) $this->course_id);
            $rows[] = [
                'label' => __('Course'),
                'value' => $course
                    ? strtoupper((string) $course->course_code).' — '.$course->title
                    : '—',
            ];
        } elseif ($this->filterActive($this->semester_id)) {
            $rows[] = [
                'label' => __('Results scope'),
                'value' => __('Whole semester (all courses)'),
            ];
        }

        if (trim($this->search) !== '') {
            $rows[] = ['label' => __('Search'), 'value' => $this->search];
        }

        return $rows;
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $importer = new ResultsImport(
            auth()->user()->institution_id,
            app(GradingService::class)
        );
        $importer->import($this->importFile->getRealPath());

        $this->importedCount = $importer->imported;
        $this->importFailures = $importer->failures;
        $this->importFile = null;

        if ($this->importedCount > 0) {
            $this->dispatch('notify', [
                'type'    => 'success',
                'message' => "{$this->importedCount} result(s) imported successfully.",
            ]);
        }
    }

    public function with(): array
    {
        $hasInstitution = $this->hasInstitutionContext();

        $departments = $hasInstitution
            ? Department::query()
                ->where('institution_id', auth()->user()->institution_id ?: $this->institution_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : collect();

        $programs = $this->filterActive($this->department_id)
            ? Program::query()
                ->where('department_id', $this->department_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
            : collect();

        $sessions = $this->filterActive($this->program_id)
            ? AcademicSession::query()
                ->whereHas('results', function ($rq) {
                    $rq->whereHas('course', function ($cq) {
                        $cq->where('program_id', $this->program_id)
                            ->when($this->filterActive($this->department_id), fn ($q) => $q->where('department_id', $this->department_id));
                    })
                        ->when($this->filterActive($this->institution_id), fn ($q) => $q->where('institution_id', $this->institution_id));
                })
                ->orderByDesc('name')
                ->get()
            : collect();

        $levels = ($this->filterActive($this->program_id) && $this->filterActive($this->session_id))
            ? Course::query()
                ->where('program_id', $this->program_id)
                ->when($this->filterActive($this->department_id), fn ($q) => $q->where('department_id', $this->department_id))
                ->when($this->filterActive($this->institution_id), fn ($q) => $q->where('institution_id', $this->institution_id))
                ->whereHas('results', function ($rq) {
                    $rq->where('academic_session_id', $this->session_id)
                        ->when($this->filterActive($this->institution_id), fn ($q) => $q->where('institution_id', $this->institution_id));
                })
                ->distinct()
                ->orderBy('level')
                ->pluck('level')
                ->values()
            : collect();

        $semesters = $this->filterActive($this->session_id)
            ? Semester::query()->where('academic_session_id', $this->session_id)->orderBy('name')->get()
            : collect();

        $filters = $this->filterPayload();
        $courses = ($this->filterActive($this->session_id)
            && $this->filterActive($this->level)
            && $this->filterActive($this->semester_id))
            ? ResultsPresentationBuilder::catalogCourses($filters)
            : collect();

        $cascade = [
            'department' => $hasInstitution,
            'program' => $this->filterActive($this->department_id),
            'session' => $this->filterActive($this->program_id),
            'level' => $this->filterActive($this->session_id),
            'semester' => $this->filterActive($this->level),
            'course' => $this->filterActive($this->semester_id),
        ];

        $institutions = auth()->user()->institution_id
            ? []
            : Institution::query()->where('status', 'active')->orderBy('name')->get();

        $printSummary = $this->buildPrintFilterSummary();

        $resultsReady = $this->filterActive($this->semester_id);

        if (! $resultsReady) {
            return [
                'printSummary' => $printSummary,
                'resultsReady' => false,
                'viewMode' => null,
                'matrixPaginator' => null,
                'catalogCourses' => collect(),
                'courseResults' => Result::query()->whereRaw('1 = 0')->paginate(20),
                'courseResultsForPrint' => collect(),
                'matrixRowsForPrint' => collect(),
                'matrixPrintPassFailSummary' => [
                    'total_passes' => 0,
                    'total_fails' => 0,
                    'pass_percentage' => 0.0,
                    'fail_percentage' => 0.0,
                ],
                'metrics' => [
                    'mode' => 'none',
                    'total' => 0,
                    'students' => 0,
                    'courses' => 0,
                    'pass' => 0,
                    'fail' => 0,
                    'pass_percentage' => 0,
                    'fail_percentage' => 0,
                    'grades' => [],
                ],
                'cascade' => $cascade,
                'departments' => $departments,
                'programs' => $programs,
                'sessions' => $sessions,
                'levels' => $levels,
                'semesters' => $semesters,
                'courses' => $courses,
                'institutions' => $institutions,
            ];
        }

        if ($this->filterActive($this->course_id)) {
            $baseQuery = ResultsFilterService::newFilteredQuery($filters)
                ->with(['student', 'course', 'academicSession', 'semester']);

            $courseResults = (clone $baseQuery)->latest()->paginate(20);
            // Full course list for print (same filters as paginated list).
            $courseResultsForPrint = (clone $baseQuery)->latest()->get();
            $allForMetrics = (clone $baseQuery)->select(['grade', 'remark'])->get();
            $metrics = ResultsPresentationBuilder::courseModeMetrics($allForMetrics);

            return [
                'printSummary' => $printSummary,
                'resultsReady' => true,
                'viewMode' => 'course',
                'matrixPaginator' => null,
                'catalogCourses' => $courses,
                'courseResults' => $courseResults,
                'courseResultsForPrint' => $courseResultsForPrint,
                'matrixRowsForPrint' => collect(),
                'matrixPrintPassFailSummary' => [
                    'total_passes' => 0,
                    'total_fails' => 0,
                    'pass_percentage' => 0.0,
                    'fail_percentage' => 0.0,
                ],
                'metrics' => $metrics,
                'cascade' => $cascade,
                'departments' => $departments,
                'programs' => $programs,
                'sessions' => $sessions,
                'levels' => $levels,
                'semesters' => $semesters,
                'courses' => $courses,
                'institutions' => $institutions,
            ];
        }

        $catalogCourses = $courses;
        // Full matrix for print loads all rows in the Livewire payload (see hidden print:block table).
        $matrixPaginator = ResultsPresentationBuilder::paginatedMatrixRows($filters, $catalogCourses, 20);
        $matrixRowsForPrint = ResultsPresentationBuilder::allMatrixRows($filters, $catalogCourses);
        $matrixPrintPassFailSummary = ResultsPresentationBuilder::matrixRowsPassFailSummary($matrixRowsForPrint);
        $metrics = ResultsPresentationBuilder::matrixModeMetrics($filters, $catalogCourses);

        return [
            'printSummary' => $printSummary,
            'resultsReady' => true,
            'viewMode' => 'matrix',
            'matrixPaginator' => $matrixPaginator,
            'catalogCourses' => $catalogCourses,
            'courseResults' => Result::query()->whereRaw('1 = 0')->paginate(20),
            'courseResultsForPrint' => collect(),
            'matrixRowsForPrint' => $matrixRowsForPrint,
            'matrixPrintPassFailSummary' => $matrixPrintPassFailSummary,
            'metrics' => $metrics,
            'cascade' => $cascade,
            'departments' => $departments,
            'programs' => $programs,
            'sessions' => $sessions,
            'levels' => $levels,
            'semesters' => $semesters,
            'courses' => $courses,
            'institutions' => $institutions,
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Academic Results') }}</flux:heading>
            <flux:subheading>{{ __('Overview of all student results and grades') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2 no-print">
            <flux:button variant="ghost" icon="printer" onclick="window.print()">
                {{ __('Print Results') }}
            </flux:button>
            <flux:button icon="arrow-down-tray" :href="$this->exportCsvUrl" :disabled="$this->exportCsvUrl === '#'">
                {{ __('Export CSV') }}
            </flux:button>
            <flux:button icon="arrow-up-tray" x-on:click="$flux.modal('import-results').show()">
                {{ __('Import CSV') }}
            </flux:button>
            <flux:button icon="plus" variant="primary" :href="route('cms.results.entry')" wire:navigate>
                {{ __('Enter Result') }}
            </flux:button>
        </div>
    </div>

    <div class="space-y-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            :placeholder="__('Search student/course...')" class="results-filters" />

        <div
            class="results-filters grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            @if (!auth()->user()->institution_id)
            <flux:select wire:model.live="institution_id" :label="__('Institution')">
                <flux:select.option value="null">{{ __('Select institution…') }}</flux:select.option>
                @foreach ($institutions as $inst)
                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <flux:select wire:model.live="department_id" :label="__('Department')"
                :disabled="!$cascade['department']">
                <flux:select.option value="null">{{ __('Select department…') }}</flux:select.option>
                @foreach ($departments as $dept)
                <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="program_id" :label="__('Program')"
                :disabled="!$cascade['program']">
                <flux:select.option value="null">{{ __('Select program…') }}</flux:select.option>
                @foreach ($programs as $prog)
                <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="session_id" :label="__('Session')"
                :disabled="!$cascade['session']">
                <flux:select.option value="null">{{ __('Select session…') }}</flux:select.option>
                @foreach ($sessions as $session)
                <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="level" :label="__('Level')" :disabled="!$cascade['level']">
                <flux:select.option value="null">{{ __('Select level…') }}</flux:select.option>
                @foreach ($levels as $lvl)
                <flux:select.option :value="$lvl">{{ $lvl }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')"
                :disabled="!$cascade['semester']">
                <flux:select.option value="null">{{ __('Select semester…') }}</flux:select.option>
                @foreach ($semesters as $semester)
                <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="course_id" :label="__('Course')"
                :disabled="!$cascade['course']">
                <flux:select.option value="null">{{ __('Whole semester (all courses)') }}</flux:select.option>
                @foreach ($courses as $course)
                <flux:select.option :value="$course->id">{{ $course->course_code }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Printed report: filter context (screen-hidden, visible when printing) --}}
    <div
        class="results-print-header hidden print:block border-b border-zinc-300 pb-4 mb-4 text-black">
        <h1 class="text-xl font-bold tracking-tight">{{ __('Academic Results') }}</h1>
        @if (!empty($printSummary))
        <dl class="mt-3 space-y-1 text-sm">
            @foreach ($printSummary as $line)
            <div class="flex flex-wrap gap-x-2 gap-y-0.5">
                <dt class="font-semibold shrink-0">{{ $line['label'] }}:</dt>
                <dd class="min-w-0">{{ $line['value'] }}</dd>
            </div>
            @endforeach
        </dl>
        @endif
        @if ($resultsReady ?? false)
        <p class="mt-2 text-sm font-medium text-zinc-700 print:text-black">
            @if (($viewMode ?? '') === 'course')
            {{ __('Single course breakdown') }}
            @else
            {{ __('Semester overview (matrix by course)') }}
            @endif
        </p>
        @endif
    </div>

    @if(!$resultsReady)
    <flux:callout variant="secondary" icon="information-circle">
        {{ __('Choose session, level, and semester to load results. Optionally pick one course for a detailed CA / exam breakdown.') }}
    </flux:callout>
    @elseif($metrics['mode'] === 'matrix' && ($metrics['students'] ?? 0) > 0)
    <flux:card class="mt-2 print:border-none print:shadow-none print:mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-6 gap-x-4">
            <div
                class="col-span-2 sm:col-span-1 text-center md:text-left flex flex-col justify-center border-b sm:border-b-0 sm:border-r border-zinc-100 dark:border-zinc-800 pb-4 sm:pb-0 sm:pr-4 print:border-none">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Students') }}</div>
                <div class="mt-1">
                    <span class="text-3xl font-semibold text-zinc-900 dark:text-white">{{ $metrics['students']
                        }}</span>
                </div>
            </div>
            <div class="text-center sm:border-r border-zinc-100 dark:border-zinc-800 sm:pr-4 print:border-none">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Courses (columns)') }}</div>
                <div class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">{{ $metrics['courses'] }}
                </div>
            </div>
        </div>
    </flux:card>
    @elseif($metrics['mode'] === 'course' && ($metrics['total'] ?? 0) > 0)
    <flux:card class="mt-2 print:border-none print:shadow-none print:mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-7 gap-y-6 gap-x-4">
            <div
                class="col-span-2 sm:col-span-3 md:col-span-1 text-center md:text-left flex flex-col justify-center border-b md:border-b-0 md:border-r border-zinc-100 dark:border-zinc-800 pb-4 md:pb-0 md:pr-4 print:border-none">
                <div class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Result rows') }}</div>
                <div class="mt-1">
                    <span class="text-3xl font-semibold text-zinc-900 dark:text-white">{{ $metrics['total'] }}</span>
                </div>
            </div>
            <div class="text-center md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4 print:border-none">
                <div class="text-xs font-medium text-green-600 dark:text-green-400">{{ __('Pass') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $metrics['pass'] }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['pass_percentage'] }}%</div>
            </div>
            <div class="text-center md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4 print:border-none">
                <div class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('Fail') }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $metrics['fail'] }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['fail_percentage'] }}%</div>
            </div>
            @foreach(['A', 'B', 'C', 'D'] as $grade)
            <div
                class="text-center {{ !$loop->last ? 'md:border-r border-zinc-100 dark:border-zinc-800 md:pr-4' : '' }} print:border-none">
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Grade') }} {{ $grade }}</div>
                <div class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{
                    $metrics['grades'][$grade]['count'] ?? 0 }}</div>
                <div class="text-xs text-zinc-500">{{ $metrics['grades'][$grade]['percentage'] ?? 0 }}%</div>
            </div>
            @endforeach
        </div>
    </flux:card>
    @endif

    @if($resultsReady && $viewMode === 'matrix')
    {{-- Paginated table (screen); full matrix is loaded again for browser print (hidden on screen). --}}
    <div class="print:hidden">
        <div
            class="results-print-table overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th
                            class="sticky left-0 z-10 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-3 font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                            {{ __('Student') }}</th>
                        @foreach ($catalogCourses as $colCourse)
                        <th class="px-2 py-3 font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                            <span class="font-mono uppercase">{{ $colCourse->course_code }}</span>
                        </th>
                        @endforeach
                        <th class="px-3 py-3 font-semibold text-green-700 dark:text-green-400 whitespace-nowrap">{{
                            __('Passes') }}</th>
                        <th class="px-3 py-3 font-semibold text-red-700 dark:text-red-400 whitespace-nowrap">{{ __('Fails')
                            }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($matrixPaginator as $row)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors"
                        wire:key="m-{{ $row['student']->id }}">
                        <td
                            class="sticky left-0 z-10 bg-white dark:bg-zinc-800 px-3 py-3 border-r border-zinc-100 dark:border-zinc-700">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['student']->full_name }}
                            </div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">{{ $row['student']->matric_number
                                }}</div>
                        </td>
                        @foreach ($catalogCourses as $colCourse)
                        <td class="px-2 py-3 text-center text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                            {{ $row['cells'][$colCourse->id] ?? '' }}
                        </td>
                        @endforeach
                        <td class="px-3 py-3 text-center font-semibold text-green-700 dark:text-green-400">{{ $row['passes']
                            }}</td>
                        <td class="px-3 py-3 text-center font-semibold text-red-700 dark:text-red-400">{{ $row['fails'] }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ 3 + $catalogCourses->count() }}"
                            class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No students with results for this selection.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 no-print">{{ $matrixPaginator->links() }}</div>
    </div>
    <div
        class="hidden print:block results-print-table overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th
                        class="sticky left-0 z-10 bg-zinc-50 dark:bg-zinc-900/50 px-3 py-3 font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                        {{ __('Student') }}</th>
                    @foreach ($catalogCourses as $colCourse)
                    <th class="px-2 py-3 font-semibold text-zinc-900 dark:text-zinc-100 whitespace-nowrap">
                        <span class="font-mono uppercase">{{ $colCourse->course_code }}</span>
                    </th>
                    @endforeach
                    <th class="px-3 py-3 font-semibold text-green-700 dark:text-green-400 whitespace-nowrap">{{
                        __('Passes') }}</th>
                    <th class="px-3 py-3 font-semibold text-red-700 dark:text-red-400 whitespace-nowrap">{{ __('Fails')
                        }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($matrixRowsForPrint as $row)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors"
                    wire:key="m-print-{{ $row['student']->id }}">
                    <td
                        class="sticky left-0 z-10 bg-white dark:bg-zinc-800 px-3 py-3 border-r border-zinc-100 dark:border-zinc-700">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $row['student']->full_name }}
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">{{ $row['student']->matric_number
                            }}</div>
                    </td>
                    @foreach ($catalogCourses as $colCourse)
                    <td class="px-2 py-3 text-center text-zinc-800 dark:text-zinc-200 whitespace-nowrap">
                        {{ $row['cells'][$colCourse->id] ?? '' }}
                    </td>
                    @endforeach
                    <td class="px-3 py-3 text-center font-semibold text-green-700 dark:text-green-400">{{ $row['passes']
                        }}</td>
                    <td class="px-3 py-3 text-center font-semibold text-red-700 dark:text-red-400">{{ $row['fails'] }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 3 + $catalogCourses->count() }}"
                        class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No students with results for this selection.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if ($matrixRowsForPrint->isNotEmpty())
            <tfoot class="border-t-2 border-zinc-300 bg-zinc-100 dark:bg-zinc-900/80 print:bg-zinc-100">
                <tr>
                    <td colspan="{{ 1 + $catalogCourses->count() }}"
                        class="px-3 py-3 text-sm font-semibold text-zinc-900 dark:text-zinc-100 print:text-black">
                        {{ __('Summary (all students, graded course outcomes)') }}
                    </td>
                    <td
                        class="px-3 py-3 text-center text-sm font-semibold text-green-700 dark:text-green-400 print:text-green-800">
                        {{ $matrixPrintPassFailSummary['total_passes'] }}
                        ({{ $matrixPrintPassFailSummary['pass_percentage'] }}%)
                    </td>
                    <td
                        class="px-3 py-3 text-center text-sm font-semibold text-red-700 dark:text-red-400 print:text-red-800">
                        {{ $matrixPrintPassFailSummary['total_fails'] }}
                        ({{ $matrixPrintPassFailSummary['fail_percentage'] }}%)
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    @elseif($resultsReady && $viewMode === 'course')
    <div class="print:hidden">
        <div
            class="results-print-table overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Student') }}
                        </th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('CA') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Exam') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Total') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Grade') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Remark') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($courseResults as $res)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $res->id }}">
                        <td class="px-4 py-4">
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $res->student->full_name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $res->student->matric_number }}
                            </div>
                        </td>
                        <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->ca_score }}</td>
                        <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->exam_score }}</td>
                        <td class="px-4 py-4 font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->total_score
                            }}</td>
                        <td class="px-4 py-4 text-sm">
                            <flux:badge variant="outline" size="sm" color="zinc">{{ $res->grade }}</flux:badge>
                        </td>
                        <td class="px-4 py-4 text-sm">
                            <flux:badge
                                :color="$res->remark === 'pass' ? 'green' : ($res->remark === 'fail' ? 'red' : 'zinc')"
                                size="sm">
                                {{ $res->remark ? ucfirst($res->remark) : '—' }}
                            </flux:badge>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                            {{ __('No results found.') }}
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 no-print">{{ $courseResults->links() }}</div>
    </div>
    <div
        class="hidden print:block results-print-table overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
        <table class="w-full text-left border-collapse">
            <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                <tr>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Student') }}
                    </th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('CA') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Exam') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Total') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Grade') }}</th>
                    <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Remark') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($courseResultsForPrint as $res)
                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="c-print-{{ $res->id }}">
                    <td class="px-4 py-4">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $res->student->full_name }}</div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $res->student->matric_number }}
                        </div>
                    </td>
                    <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->ca_score }}</td>
                    <td class="px-4 py-4 text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->exam_score }}</td>
                    <td class="px-4 py-4 font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ (float) $res->total_score
                        }}</td>
                    <td class="px-4 py-4 text-sm">
                        <flux:badge variant="outline" size="sm" color="zinc">{{ $res->grade }}</flux:badge>
                    </td>
                    <td class="px-4 py-4 text-sm">
                        <flux:badge
                            :color="$res->remark === 'pass' ? 'green' : ($res->remark === 'fail' ? 'red' : 'zinc')"
                            size="sm">
                            {{ $res->remark ? ucfirst($res->remark) : '—' }}
                        </flux:badge>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                        {{ __('No results found.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- Import Modal --}}
    <flux:modal name="import-results" variant="filled" class="min-w-[28rem]">
        <form wire:submit="import" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Results from CSV') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload a CSV file to bulk import student results. Grades and GPA will be automatically
                    computed.') }}
                    <a href="/templates/results-import-template.csv" class="text-accent underline" download>
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
</div>

@push('styles')
<style>
    @media print {

        nav,
        aside,
        footer,
        header {
            display: none !important;
        }

        .no-print,
        .results-filters,
        button,
        a[href]:not([href^="#"]) {
            display: none !important;
        }

        body {
            background-color: white !important;
            color: black !important;
        }

        .results-print-table table {
            border: 1px solid #ccc !important;
            width: 100% !important;
        }

        .results-print-table th,
        .results-print-table td {
            color: black !important;
            border: 1px solid #ccc !important;
        }

        .results-print-table .sticky {
            position: static !important;
            background: white !important;
        }

        .rounded-xl,
        .shadow-sm,
        .dark\:bg-zinc-800,
        .bg-white {
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
            border-radius: 0 !important;
        }

        .results-print-table {
            overflow: visible !important;
        }

        .results-print-header {
            display: block !important;
            color: black !important;
        }

        .results-print-header dl,
        .results-print-header dt,
        .results-print-header dd {
            color: black !important;
        }
    }
</style>
@endpush
