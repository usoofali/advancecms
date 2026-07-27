<?php

use App\Models\Course;
use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\CourseRegistration;
use App\Models\Result;
use App\Services\GradingService;
use App\Exports\LecturerResultsExport;
use App\Imports\LecturerResultsImport;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Result Entry')] class extends Component {
    use WithFileUploads;
    public int|string $session_id = '';
    public int|string $semester_id = '';
    public int|string $course_id = '';
    public int|string $institution_id = '';
    public int|string $filter_program = '';
    public int|string $filter_level = '';
    public $importFile;
    public array $importFailures = [];
    public int $importedCount = 0;
    public int $scoreVersion = 0;

    public array $scores = []; // student_id => [ca, exam]

    public function mount(): void
    {
        Gate::authorize('results.enter');

        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedSessionId(): void
    {
        $this->semester_id = '';
        $this->course_id = '';
        $this->loadScores();
    }

    public function updatedSemesterId(): void
    {
        $this->course_id = '';
        $this->loadScores();
    }

    public function updatedCourseId(): void
    {
        $this->loadScores();
    }

    public function loadScores(): void
    {
        if (!$this->course_id || !$this->semester_id || $this->course_id === 'null' || $this->semester_id === 'null') {
            $this->scores = [];
            return;
        }

        $this->scoreVersion++;

        $studentIds = CourseRegistration::where('course_id', $this->course_id)
            ->where('semester_id', $this->semester_id)
            ->pluck('student_id');

        $existingResults = Result::whereIn('student_id', $studentIds)
            ->where('course_id', $this->course_id)
            ->where('academic_session_id', $this->session_id)
            ->where('semester_id', $this->semester_id)
            ->get()
            ->keyBy('student_id');

        $this->scores = [];
        foreach ($studentIds as $studentId) {
            $result = $existingResults->get($studentId);
            $this->scores[$studentId] = [
                'ca' => (float) ($result?->ca_score ?? 0),
                'exam' => (float) ($result?->exam_score ?? 0),
            ];
        }
    }

    public function saveSingleResult(int|string $studentId, GradingService $gradingService): void
    {
        if (!isset($this->scores[$studentId])) {
            return;
        }

        $this->validate([
            "scores.{$studentId}.ca" => ['nullable', 'numeric', 'min:0', 'max:100'],
            "scores.{$studentId}.exam" => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $course = Course::find($this->course_id);
        if (!$course) {
            return;
        }

        // Ensure we save to the correct semester ID based on the course mapping within this session
        $targetSem = Semester::where('academic_session_id', $this->session_id)
            ->where('name', $course->semester == 1 ? 'first' : 'second')
            ->first();

        $result = Result::updateOrCreate(
            [
                'student_id' => $studentId,
                'course_id' => $this->course_id,
                'semester_id' => $targetSem?->id ?? $this->semester_id,
            ],
            [
                'institution_id' => $this->institution_id ?: auth()->user()->institution_id,
                'academic_session_id' => $this->session_id,
                'ca_score' => (float) ($this->scores[$studentId]['ca'] ?: 0),
                'exam_score' => (float) ($this->scores[$studentId]['exam'] ?: 0),
            ]
        );

        $gradingService->grade($result);
    }

    public function saveResults(GradingService $gradingService): void
    {
        Gate::authorize('results.enter');

        $this->validate([
            'session_id' => ['required'],
            'semester_id' => ['required'],
            'course_id' => ['required'],
            'scores.*.ca' => ['numeric', 'min:0', 'max:100'],
            'scores.*.exam' => ['numeric', 'min:0', 'max:100'],
        ]);

        foreach ($this->scores as $studentId => $values) {
            $this->saveSingleResult($studentId, $gradingService);
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Results saved & graded successfully!',
        ]);
        $this->dispatch('results-saved');
    }

    public function exportCsv()
    {
        Gate::authorize('results.export');

        $this->validate([
            'session_id' => 'required',
            'semester_id' => 'required',
            'course_id' => 'required',
        ]);

        $export = new LecturerResultsExport(
            $this->institution_id ?: auth()->user()->institution_id,
            $this->session_id,
            $this->semester_id,
            $this->course_id
        );

        return $export->download();
    }

    public function exportExcel()
    {
        Gate::authorize('results.export');

        $this->validate([
            'session_id' => 'required',
            'semester_id' => 'required',
            'course_id' => 'required',
        ]);

        $export = new LecturerResultsExport(
            $this->institution_id ?: auth()->user()->institution_id,
            $this->session_id,
            $this->semester_id,
            $this->course_id
        );

        return $export->downloadExcel();
    }

    public function importCsv()
    {
        Gate::authorize('results.import');

        $this->validate([
            'session_id' => 'required',
            'semester_id' => 'required',
            'course_id' => 'required',
            'importFile' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $this->importFailures = [];
        $this->importedCount = 0;

        $import = new LecturerResultsImport(
            $this->institution_id ?: auth()->user()->institution_id,
            $this->session_id,
            $this->semester_id,
            $this->course_id
        );

        $import->import($this->importFile->getRealPath());

        $this->importedCount = $import->imported;
        $this->importFailures = $import->failures;

        $this->loadScores();
        $this->reset('importFile');

        if (empty($this->importFailures)) {
            $this->dispatch('modal-close', name: 'import-results');
        }

        if (count($this->importFailures) > 0) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "Imported {$this->importedCount} records with " . count($this->importFailures) . " failures.",
            ]);
            // Log failures for debugging
            \Illuminate\Support\Facades\Log::warning('Result Import Failures', $this->importFailures);
        } else {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully imported {$this->importedCount} records.",
            ]);
        }
    }

    public function updatedFilterProgram(): void
    {
        $this->course_id = '';
        $this->loadScores();
    }

    public function updatedFilterLevel(): void
    {
        $this->course_id = '';
        $this->loadScores();
    }

    public function with(): array
    {
        $selectedSession = ($this->session_id && $this->session_id !== 'null') ? \App\Models\AcademicSession::find($this->session_id) : null;
        $students = collect();

        if ($this->course_id && $this->semester_id && $this->course_id !== 'null' && $this->semester_id !== 'null') {
            $students = CourseRegistration::with('student.program')
                ->where('course_id', $this->course_id)
                ->where('semester_id', $this->semester_id)
                ->whereHas('student', function ($query) use ($selectedSession) {
                    $query->when($this->filter_program, fn($q) => $q->where('program_id', $this->filter_program))
                        ->when($this->filter_level && $selectedSession, fn($q) => $q->atLevel($this->filter_level, $selectedSession));
                })
                ->get()
                ->sortBy(fn($reg) => $reg->student->matric_number)
                ->map(fn($reg) => $reg->student);
        }

        return [
            'sessions' => AcademicSession::query()->orderByDesc('name')->get(),
            'semesters' => ($this->session_id && $this->session_id !== 'null') ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'institutions' => auth()->user()->institution_id
                ? []
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'programs' => \App\Models\Program::query()
                ->when($this->institution_id ?: auth()->user()->institution_id, function ($q, $id) {
                    $q->whereHas('department', fn($dq) => $dq->where('institution_id', $id));
                })
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()
                ->when($this->institution_id && $this->institution_id !== 'null', fn($q) => $q->where('institution_id', $this->institution_id))
                ->when($this->filter_program, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('program_id', $this->filter_program)
                            ->orWhereHas('programs', fn($pq) => $pq->where('programs.id', $this->filter_program));
                    });
                })
                ->when($this->filter_level, function ($q) {
                    $q->where(function ($sq) {
                        $sq->where('level', $this->filter_level)
                            ->orWhereHas('programs', fn($pq) => $pq->where('program_courses.level', $this->filter_level));
                    });
                })
                ->when(!auth()->user()->hasAnyRole(['Super Admin', 'Institutional Admin']), function ($q) {
                    $user = auth()->user();
                    $q->whereHas('allocations', function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->when($this->session_id && $this->session_id !== 'null', fn($sq) => $sq->where('academic_session_id', $this->session_id))
                            ->when($this->semester_id && $this->semester_id !== 'null', fn($sq) => $sq->where('semester_id', $this->semester_id));
                    });
                })
                ->when($this->semester_id && $this->semester_id !== 'null', function ($q) {
                    $semester = Semester::find($this->semester_id);
                    if ($semester) {
                        $q->where('semester', $semester->name === 'first' ? 1 : 2);
                    }
                })
                ->orderBy('course_code')
                ->get(),
            'students' => $students,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-xl sm:text-2xl font-black">{{ __('Result Entry') }}</flux:heading>
            <flux:subheading class="text-xs sm:text-sm">{{ __('Record scores for students registered in a course') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <x-action-message on="results-saved">
                <flux:badge color="green">{{ __('Results saved & graded successfully!') }}</flux:badge>
            </x-action-message>

            @if ($course_id && $course_id !== 'null' && count($students) > 0)
                @can('results.import')
                    <flux:button size="sm" variant="ghost" icon="document-arrow-up"
                        x-on:click="$flux.modal('import-results').show()">
                        {{ __('Import Excel / CSV') }}
                    </flux:button>
                @endcan
                @can('results.export')
                    <flux:button size="sm" variant="ghost" icon="table-cells" wire:click="exportExcel">
                        {{ __('Export Excel') }}
                    </flux:button>
                    <flux:button size="sm" variant="ghost" icon="document-arrow-down" wire:click="exportCsv">
                        {{ __('Export CSV') }}
                    </flux:button>
                @endcan
            @endif
        </div>
    </div>

    <flux:card class="mb-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
            @if (!auth()->user()->institution_id)
                <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                    <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                    @foreach ($institutions as $inst)
                        <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:select wire:model.live="session_id" :label="__('Academic Session')" required
                :disabled="!$institution_id && !auth()->user()->institution_id">
                <flux:select.option value="null">{{ __('Select session...') }}</flux:select.option>
                @foreach ($sessions as $session)
                    <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_program" :label="__('Program')" :disabled="!$session_id">
                <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
                @foreach ($programs as $prog)
                    <flux:select.option :value="$prog->id">{{ $prog->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter_level" :label="__('Level')" :disabled="!$session_id">
                <flux:select.option value="">{{ __('All Levels') }}</flux:select.option>
                @foreach ([100, 200, 300] as $lvl)
                    <flux:select.option :value="$lvl">{{ $lvl }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="semester_id" :label="__('Semester')" :disabled="!$session_id">
                <flux:select.option value="null">{{ __('Select semester...') }}</flux:select.option>
                @foreach ($semesters as $semester)
                    <flux:select.option :value="$semester->id">{{ ucfirst($semester->name) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="course_id" :label="__('Course')" :disabled="!$semester_id">
                <flux:select.option value="null">{{ __('Select course...') }}</flux:select.option>
                @foreach ($courses as $course)
                    <flux:select.option :value="$course->id">{{ $course->course_code }}: {{ $course->title }}
                    </flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    @if ($course_id && $course_id !== 'null' && count($students) > 0)
        <div class="space-y-6">
            <flux:card>
                <div
                    class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                            <tr>
                                <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ __('Student') }}</th>
                                <th
                                    class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 w-32 min-w-[100px]">
                                    {{ __('CA (30)') }}</th>
                                <th
                                    class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 w-32 min-w-[100px]">
                                    {{ __('Exam (70)') }}</th>
                                <th
                                    class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 text-center w-24 min-w-[80px]">
                                    {{ __('Total') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($students as $stu)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors"
                                    wire:key="{{ $session_id }}-{{ $semester_id }}-{{ $course_id }}-{{ $stu->id }}-{{ $scoreVersion }}"
                                    x-data="{
                                        ca: {{ (float) (($scores[$stu->id]['ca'] ?? 0) ?: 0) }},
                                        exam: {{ (float) (($scores[$stu->id]['exam'] ?? 0) ?: 0) }},
                                        get total() {
                                            return ((parseFloat(this.ca) || 0) + (parseFloat(this.exam) || 0));
                                        }
                                    }">
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $stu->full_name }}
                                        </div>
                                        <div class="font-mono text-xs text-zinc-500 dark:text-zinc-400 uppercase mt-0.5">
                                            {{ $stu->matric_number }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:input type="number" step="0.5" x-model.number="ca" wire:model.blur="scores.{{ $stu->id }}.ca"
                                            wire:change="saveSingleResult({{ $stu->id }})" size="sm" class="w-24" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <flux:input type="number" step="0.5" x-model.number="exam" wire:model.blur="scores.{{ $stu->id }}.exam"
                                            wire:change="saveSingleResult({{ $stu->id }})" size="sm" class="w-24" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-center text-zinc-900 dark:text-zinc-100" x-text="total">
                                            {{ (float) (($scores[$stu->id]['ca'] ?? 0) ?: 0) + (float) (($scores[$stu->id]['exam'] ?? 0) ?: 0) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>

            <div
                class="sticky bottom-6 p-4 bg-white/90 dark:bg-zinc-900/90 backdrop-blur border border-zinc-200 dark:border-zinc-700 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xl z-30">
                <div class="flex items-center gap-2 text-xs text-zinc-500">
                    <flux:icon.check-circle class="size-4 text-green-500 shrink-0" />
                    <span>{{ __('Results are auto-saved as you move between fields.') }}</span>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                    <flux:button variant="primary" wire:click="saveResults" icon="check" class="w-full sm:w-auto">
                        {{ __('Finalize & Grade All') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @elseif ($course_id && $course_id !== 'null')
        <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-500">
            {{ __('No students registered for this course in the selected semester.') }}
        </div>
    @endif

    <flux:modal name="import-results" class="min-w-[28rem]">
        <form wire:submit="importCsv" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Results (Excel / CSV)') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload an Excel (.xlsx, .xls) or CSV file containing result scores. The grades will be applied to the specifically
                    selected Session, Semester, and Course.') }}
                    <a href="{{ asset('templates/lecturer-results-template.csv') }}"
                        class="text-blue-500 hover:underline mt-1 block" download>
                        {{ __('Download sample template') }}
                    </a>
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="importFile" accept=".csv,.xlsx,.xls" required />
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
                <flux:button type="submit" variant="primary">
                    {{ __('Import Records') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>