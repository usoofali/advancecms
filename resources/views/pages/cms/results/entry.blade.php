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
    public $importFile;
    public array $importFailures = [];
    public int $importedCount = 0;
    
    public array $scores = []; // student_id => [ca, exam]

    public function mount(): void
    {
        if (auth()->user()->institution_id) {
            $this->institution_id = auth()->user()->institution_id;
        }
    }

    public function updatedCourseId(): void
    {
        $this->loadScores();
    }

    public function loadScores(): void
    {
        if (!$this->course_id || !$this->semester_id) {
            $this->scores = [];
            return;
        }

        $registrations = CourseRegistration::where('course_id', $this->course_id)
            ->where('semester_id', $this->semester_id)
            ->get();

        foreach ($registrations as $reg) {
            $result = Result::where('student_id', $reg->student_id)
                ->where('course_id', $this->course_id)
                ->where('semester_id', $this->semester_id)
                ->first();

            $this->scores[$reg->student_id] = [
                'ca' => $result?->ca_score ?? 0,
                'exam' => $result?->exam_score ?? 0,
            ];
        }
    }

    public function saveResults(GradingService $gradingService): void
    {
        $this->validate([
            'session_id'  => ['required'],
            'semester_id' => ['required'],
            'course_id'   => ['required'],
            'scores.*.ca'   => ['numeric', 'min:0', 'max:100'],
            'scores.*.exam' => ['numeric', 'min:0', 'max:100'],
        ]);

        foreach ($this->scores as $studentId => $values) {
            $result = Result::updateOrCreate(
                [
                    'institution_id'      => $this->institution_id,
                    'student_id'          => $studentId,
                    'course_id'           => $this->course_id,
                    'academic_session_id' => $this->session_id,
                    'semester_id'         => $this->semester_id,
                ],
                [
                    'ca_score'   => $values['ca'],
                    'exam_score' => $values['exam'],
                ]
            );

            $gradingService->grade($result);
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Results saved & graded successfully!',
        ]);
        $this->dispatch('results-saved');
    }

    public function exportCsv()
    {
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

    public function importCsv()
    {
        $this->validate([
            'session_id' => 'required',
            'semester_id' => 'required',
            'course_id' => 'required',
            'importFile' => 'required|file|mimes:csv,txt|max:2048',
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

    public function with(): array
    {
        $students = [];
        if ($this->course_id && $this->semester_id) {
            $students = CourseRegistration::with('student')
                ->where('course_id', $this->course_id)
                ->where('semester_id', $this->semester_id)
                ->get()
                ->map(fn($reg) => $reg->student);
        }

        return [
            'sessions' => AcademicSession::query()->orderByDesc('name')->get(),
            'semesters' => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'institutions' => auth()->user()->institution_id 
                ? [] 
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'courses' => Course::query()
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->when(!auth()->user()->hasAnyRole(['Super Admin', 'Institutional Admin']), function ($q) {
                    $user = auth()->user();
                    $q->whereHas('allocations', function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                              ->where('academic_session_id', $this->session_id)
                              ->where('semester_id', $this->semester_id);
                    });
                })
                ->orderBy('course_code')
                ->get(),
            'students' => $students,
        ];
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <div class="mb-8 items-center justify-between flex">
        <div>
            <flux:heading size="xl">{{ __('Result Entry') }}</flux:heading>
            <flux:subheading>{{ __('Record scores for students registered in a course') }}</flux:subheading>
        </div>

        <div class="flex items-center gap-3">
            <x-action-message on="results-saved">
                <flux:badge color="green">{{ __('Results saved & graded successfully!') }}</flux:badge>
            </x-action-message>

            @if ($course_id && count($students) > 0)
            <flux:button size="sm" variant="ghost" icon="document-arrow-down" wire:click="exportCsv">
                {{ __('Export CSV') }}
            </flux:button>
            <flux:button size="sm" variant="ghost" icon="document-arrow-up"
                x-on:click="$flux.modal('import-results').show()">
                {{ __('Import CSV') }}
            </flux:button>
            @endif
        </div>
    </div>

    <flux:card class="mb-8">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
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

    @if ($course_id && count($students) > 0)
    <flux:card>
        <div
            class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-sm">
            <table class="w-full text-left border-collapse">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Student
                            Name') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100">{{ __('Matric
                            Number') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 w-32">{{ __('CA
                            (40)') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 w-32">{{ __('Exam
                            (60)') }}</th>
                        <th class="px-4 py-3 font-semibold text-sm text-zinc-900 dark:text-zinc-100 w-24">{{ __('Total')
                            }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($students as $stu)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/20 transition-colors" wire:key="{{ $stu->id }}">
                        <td class="px-4 py-3 text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $stu->full_name }}
                        </td>
                        <td class="px-4 py-3 font-mono text-sm text-zinc-600 dark:text-zinc-400 uppercase">
                            {{ $stu->matric_number }}
                        </td>
                        <td class="px-4 py-3">
                            <flux:input type="number" step="0.5" wire:model="scores.{{ $stu->id }}.ca" size="sm"
                                class="w-24" />
                        </td>
                        <td class="px-4 py-3">
                            <flux:input type="number" step="0.5" wire:model="scores.{{ $stu->id }}.exam" size="sm"
                                class="w-24" />
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-zinc-900 dark:text-zinc-100">
                                {{ ($scores[$stu->id]['ca'] ?? 0) + ($scores[$stu->id]['exam'] ?? 0) }}
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mt-8">
            <flux:button variant="primary" wire:click="saveResults">
                {{ __('Save & Grade All') }}
            </flux:button>
        </div>
    </flux:card>
    @elseif ($course_id)
    <div class="p-12 text-center border-2 border-dashed rounded-2xl text-zinc-500">
        {{ __('No students registered for this course in the selected semester.') }}
    </div>
    @endif

    <flux:modal name="import-results" class="min-w-[28rem]">
        <form wire:submit="importCsv" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Import Results (CSV)') }}</flux:heading>
                <flux:subheading>
                    {{ __('Upload a CSV file containing result scores. The grades will be applied to the specifically
                    selected Session, Semester, and Course.') }}
                    <a href="{{ asset('templates/lecturer-results-template.csv') }}"
                        class="text-blue-500 hover:underline mt-1 block" download>
                        {{ __('Download sample template') }}
                    </a>
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="importFile" accept=".csv" required />
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