<?php

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Department;
use App\Models\Program;
use App\Models\RegistrationStatus;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\BatchCourseRegistrationService;
use App\Services\GradingService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Course Registration')] class extends Component {
    public int|string $student_id = '';
    public int|string $session_id = '';
    public string $student_search = '';
    public $selected_courses = [];
    public $courses_to_drop = [];
    public int|string $institution_id = '';
    public ?int $department_id = null;

    // Batch Registration Modal & Chunked Progress State
    public bool $showBatchModal = false;
    public int|string $batch_session_id = '';
    public int|string $batch_semester_id = '';
    public int|string $batch_department_id = '';
    public int|string $batch_program_id = '';
    public string $batch_level = '';
    public string $batch_student_search = '';
    public array $batch_selected_student_ids = [];
    public bool $batch_select_all = true;
    public bool $batch_include_level = true;
    public bool $batch_include_carryovers = true;
    public bool $batchRunning = false;
    public bool $batchCompleted = false;
    public array $batchQueueIds = [];
    public int $batchTotalStudents = 0;
    public int $batchProcessedCount = 0;
    public int $batchLevelCreated = 0;
    public int $batchCarryoverCreated = 0;
    public int $batchSkipped = 0;

    public function mount(): void
    {
        Gate::authorize('registrations.view');

        $user = auth()->user();

        if ($user->institution_id) {
            $this->institution_id = $user->institution_id;
        }

        if (!$user->hasRole('Super Admin') && !$user->hasRole('Institutional Admin')) {
            $scopedDeptIds = array_merge(
                $user->getScopedModelIds('Academic Secretary', \App\Models\Department::class),
                $user->getScopedModelIds('Head of Department (HOD)', \App\Models\Department::class),
                $user->getScopedModelIds('Exam Officer', \App\Models\Department::class)
            );

            if (!empty($scopedDeptIds)) {
                $this->department_id = $scopedDeptIds[0];
                return;
            }

            // Fallback: Legacy check for HOD via hod_id column
            $staff = \App\Models\Staff::where('email', $user->email)->first();
            if ($staff) {
                $hodDept = \App\Models\Department::where('hod_id', $staff->id)->first();
                if ($hodDept) {
                    $this->department_id = $hodDept->id;
                }
            }
        }
    }

    public function updatedSessionId(): void
    {
        $this->selected_courses = [];
        $this->syncCarryovers();
    }

    public function updatedStudentId(): void
    {
        $this->selected_courses = [];
        $this->syncCarryovers();
    }

    protected function syncCarryovers(): void
    {
        if ($this->student_id && $this->session_id && $this->session_id !== 'null') {
            $student = Student::find($this->student_id);
            $semesters = Semester::where('academic_session_id', $this->session_id)->get();
            
            $allCarryoverIds = [];
            foreach ($semesters as $sem) {
                $carryovers = app(GradingService::class)->getCarryoverCourses(
                    $student,
                    (int) $this->institution_id,
                    (int) $this->session_id,
                    (int) $sem->id
                );
                $allCarryoverIds = array_merge($allCarryoverIds, $carryovers->pluck('id')->toArray());
            }

            $carryoverIds = collect($allCarryoverIds)->unique()->map(fn($id) => (string) $id)->toArray();
            
            $this->selected_courses = collect($this->selected_courses)
                ->merge($carryoverIds)
                ->unique()
                ->toArray();
        }
    }

    public function register(): void
    {
        Gate::authorize('registrations.create');

        $this->validate([
            'student_id'       => ['required', 'exists:students,id'],
            'session_id'       => ['required', 'exists:academic_sessions,id'],
            'selected_courses' => ['required', 'array', 'min:1'],
        ]);

        // Admin view: We don't block registration even if closed, but we keep the status for awareness

        // Verify all mandatory carryover courses are included
        $student = Student::find($this->student_id);
        $semesters = Semester::where('academic_session_id', $this->session_id)->get();
        
        $allCarryovers = collect();
        foreach ($semesters as $sem) {
            $semCarryovers = app(GradingService::class)->getCarryoverCourses(
                $student,
                (int) $this->institution_id,
                (int) $this->session_id,
                (int) $sem->id
            );
            $allCarryovers = $allCarryovers->merge($semCarryovers);
        }
        $selectedIds = collect($this->selected_courses)->map(fn($id) => (int) $id)->toArray();
        $missingCarryovers = $allCarryovers->whereNotIn('id', $selectedIds);

        if ($missingCarryovers->isNotEmpty()) {
            $this->addError('selected_courses', 'You must include all mandatory carryover courses: ' .
                $missingCarryovers->pluck('course_code')->implode(', '));

            return;
        }

        $carryoverIds = $allCarryovers->pluck('id')->toArray();
        $newlyRegistered = 0;
        $alreadyRegistered = 0;

        $courses = Course::whereIn('id', $this->selected_courses)->get();

        foreach ($courses as $course) {
            $targetSem = Semester::where('academic_session_id', $this->session_id)
                ->where('name', $course->semester == 1 ? 'first' : 'second')
                ->first();

            if (!$targetSem) continue;

            $exists = CourseRegistration::query()
                ->where('institution_id', $this->institution_id)
                ->where('student_id', $this->student_id)
                ->where('course_id', $course->id)
                ->where('academic_session_id', $this->session_id)
                ->where('semester_id', $targetSem->id)
                ->exists();

            if ($exists) {
                $alreadyRegistered++;
            } else {
                CourseRegistration::create([
                    'institution_id'      => $this->institution_id,
                    'student_id'          => $this->student_id,
                    'course_id'           => $course->id,
                    'academic_session_id' => $this->session_id,
                    'semester_id'         => $targetSem->id,
                    'status'              => 'registered',
                    'is_carryover'        => in_array($course->id, $carryoverIds),
                ]);
                $newlyRegistered++;
            }
        }

        $this->reset(['selected_courses']);
        $this->syncCarryovers();

        if ($newlyRegistered > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully registered {$newlyRegistered} course(s).",
            ]);
        }

        if ($alreadyRegistered > 0) {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "{$alreadyRegistered} course(s) were already registered.",
            ]);
        }
    }

    public function drop(): void
    {
        Gate::authorize('registrations.delete');

        $this->validate([
            'student_id'      => ['required', 'exists:students,id'],
            'session_id'      => ['required', 'exists:academic_sessions,id'],
            'courses_to_drop' => ['required', 'array', 'min:1'],
        ]);

        // Admin view: We don't block dropping even if closed

        $dropped = CourseRegistration::query()
            ->where('institution_id', $this->institution_id)
            ->where('student_id', $this->student_id)
            ->where('academic_session_id', $this->session_id)
            ->whereIn('course_id', $this->courses_to_drop)
            ->delete();

        $this->reset(['courses_to_drop']);
        $this->syncCarryovers();

        if ($dropped > 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully dropped {$dropped} course(s).",
            ]);
        }
    }

    public function openBatchModal(): void
    {
        Gate::authorize('registrations.create');

        if (! $this->institution_id || $this->institution_id === 'null') {
            $this->institution_id = auth()->user()->institution_id
                ?: (\App\Models\Institution::query()->where('status', 'active')->orderBy('name')->value('id') ?? '');
        }

        $activeSession = AcademicSession::where('status', 'active')->first()
            ?? AcademicSession::latest('id')->first();

        $this->batch_session_id = $activeSession?->id ?? '';
        $this->batch_semester_id = $this->batch_session_id
            ? (Semester::where('academic_session_id', $this->batch_session_id)->value('id') ?? '')
            : '';
        $this->batch_department_id = $this->department_id ?? '';
        $this->batch_program_id = '';
        $this->batch_level = '';
        $this->batch_student_search = '';
        $this->batch_select_all = true;
        $this->batch_include_level = true;
        $this->batch_include_carryovers = true;
        $this->batchRunning = false;
        $this->batchCompleted = false;
        $this->batchQueueIds = [];
        $this->batchTotalStudents = 0;
        $this->batchProcessedCount = 0;
        $this->batchLevelCreated = 0;
        $this->batchCarryoverCreated = 0;
        $this->batchSkipped = 0;

        $this->syncBatchSelection();
        $this->showBatchModal = true;
    }

    public function updatedInstitutionId(): void
    {
        $this->batch_department_id = $this->department_id ?? '';
        $this->batch_program_id = '';
        if ($this->showBatchModal) {
            $this->syncBatchSelection();
        }
    }

    public function updatedBatchSessionId(): void
    {
        $this->batch_semester_id = $this->batch_session_id
            ? (Semester::where('academic_session_id', $this->batch_session_id)->value('id') ?? '')
            : '';
        $this->syncBatchSelection();
    }

    public function updatedBatchDepartmentId(): void
    {
        $this->batch_program_id = '';
        $this->syncBatchSelection();
    }

    public function updatedBatchProgramId(): void
    {
        $this->syncBatchSelection();
    }

    public function updatedBatchLevel(): void
    {
        $this->syncBatchSelection();
    }

    public function updatedBatchSelectAll(bool $value): void
    {
        if ($value) {
            $this->batch_selected_student_ids = $this->getBatchMatchingStudents()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->batch_selected_student_ids = [];
        }
    }

    protected function syncBatchSelection(): void
    {
        $students = $this->getBatchMatchingStudents();
        if ($this->batch_select_all) {
            $this->batch_selected_student_ids = $students
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->batch_selected_student_ids = [];
        }
    }

    protected function getBatchMatchingStudents(): Collection
    {
        $instId = $this->institution_id && $this->institution_id !== 'null' ? (int) $this->institution_id : null;
        if (! $instId || ! $this->batch_session_id) {
            return collect();
        }

        $session = AcademicSession::find($this->batch_session_id);
        if (! $session) {
            return collect();
        }

        $deptId = $this->department_id ?: ($this->batch_department_id ?: null);

        return Student::query()
            ->with('program.department')
            ->where('institution_id', $instId)
            ->where('status', 'active')
            ->when($deptId, fn ($q) => $q->whereHas('program', fn ($pq) => $pq->where('department_id', $deptId)))
            ->when($this->batch_program_id, fn ($q) => $q->where('program_id', $this->batch_program_id))
            ->when($this->batch_level, fn ($q) => $q->atLevel($this->batch_level, $session))
            ->orderBy('last_name')
            ->get();
    }

    public function startBatchRegistration(BatchCourseRegistrationService $service): void
    {
        Gate::authorize('registrations.create');

        $this->validate([
            'batch_session_id' => ['required', 'exists:academic_sessions,id'],
            'batch_semester_id' => ['required', 'exists:semesters,id'],
            'batch_selected_student_ids' => ['required', 'array', 'min:1'],
        ], [
            'batch_selected_student_ids.required' => __('Please select at least one student.'),
            'batch_selected_student_ids.min' => __('Please select at least one student.'),
        ]);

        if (! $this->batch_include_level && ! $this->batch_include_carryovers) {
            $this->addError('batch_include_level', __('Select at least one registration option (level courses or carryovers).'));

            return;
        }

        $this->batchQueueIds = array_values(array_map('intval', $this->batch_selected_student_ids));
        $this->batchTotalStudents = count($this->batchQueueIds);
        $this->batchProcessedCount = 0;
        $this->batchLevelCreated = 0;
        $this->batchCarryoverCreated = 0;
        $this->batchSkipped = 0;
        $this->batchRunning = true;
        $this->batchCompleted = false;

        $this->processBatchChunk($service);
    }

    public function processBatchChunk(BatchCourseRegistrationService $service): void
    {
        if (! $this->batchRunning || empty($this->batchQueueIds)) {
            $this->batchRunning = false;

            return;
        }

        $instId = $this->institution_id && $this->institution_id !== 'null'
            ? (int) $this->institution_id
            : (int) auth()->user()->institution_id;

        $chunk = array_splice($this->batchQueueIds, 0, 15);
        $result = $service->registerChunk(
            $chunk,
            $instId,
            (int) $this->batch_session_id,
            (int) $this->batch_semester_id,
            $this->batch_include_level,
            $this->batch_include_carryovers
        );

        $this->batchProcessedCount += $result['students_processed'];
        $this->batchLevelCreated += $result['level_created'];
        $this->batchCarryoverCreated += $result['carryover_created'];
        $this->batchSkipped += $result['skipped'];

        if (empty($this->batchQueueIds)) {
            $this->batchRunning = false;
            $this->batchCompleted = true;

            ActivityLogger::log(
                action: 'batch_registered',
                module: 'CourseRegistration',
                description: "Batch registered {$this->batchTotalStudents} student(s): {$this->batchLevelCreated} level courses, {$this->batchCarryoverCreated} carryovers, {$this->batchSkipped} skipped.",
                institutionId: $instId
            );

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => __('Batch complete: :level level & :carry carryover course(s) registered.', [
                    'level' => $this->batchLevelCreated,
                    'carry' => $this->batchCarryoverCreated,
                ]),
            ]);
        } else {
            $this->dispatch('batch-reg-next-chunk');
        }
    }

    public function render(): \Illuminate\View\View
    {
        $student = $this->student_id ? Student::with('program')->find($this->student_id) : null;
        $availableCourses = collect();
        $registeredCourses = collect();
        $carryoverCourses = collect();
        $currentLevel = null;

        if ($student && $this->session_id && $this->session_id !== 'null') {
            $session = AcademicSession::find($this->session_id);
            $currentLevel = $student->currentLevel($session);
            $semesters = Semester::where('academic_session_id', $this->session_id)->get();

            foreach ($semesters as $semester) {
                $semName = $semester->name === 'first' ? '1st' : '2nd';
                
                $allLevelCourses = Course::query()
                    ->where('institution_id', $this->institution_id)
                    ->where('program_id', $student->program_id)
                    ->where('level', $currentLevel)
                    ->where('semester', $semester->name === 'first' ? 1 : 2)
                    ->get();

                $registeredCourseIds = CourseRegistration::query()
                    ->where('institution_id', $this->institution_id)
                    ->where('student_id', $this->student_id)
                    ->where('academic_session_id', $this->session_id)
                    ->where('semester_id', $semester->id)
                    ->pluck('course_id')
                    ->toArray();

                $semCarryovers = app(GradingService::class)->getCarryoverCourses(
                    $student,
                    (int) $this->institution_id,
                    (int) $this->session_id,
                    (int) $semester->id
                );

                $carryoverIds = $semCarryovers->pluck('id');

                $carryoverCourses = $carryoverCourses->merge($semCarryovers->map(function($c) use ($semName) {
                    $arr = $c->toArray();
                    $arr['semester_name'] = $semName;
                    return $arr;
                }));
                
                $available = $allLevelCourses
                    ->whereNotIn('id', $registeredCourseIds)
                    ->whereNotIn('id', $carryoverIds->all());
                
                $availableCourses = $availableCourses->merge($available->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
                
                $allRegisteredInSem = Course::query()
                    ->whereIn('id', $registeredCourseIds)
                    ->get();

                $registeredCourses = $registeredCourses->merge($allRegisteredInSem->map(fn($c) => array_merge($c->toArray(), ['semester_name' => $semName])));
            }
        }

        return view('pages.cms.students.registration', [
            'institutions' => auth()->user()->institution_id
                ? []
                : \App\Models\Institution::query()->where('status', 'active')->orderBy('name')->get(),
            'students'          => Student::query()
                ->with('program.department')
                ->when($this->institution_id, fn($q) => $q->where('institution_id', $this->institution_id))
                ->when($this->department_id, function($q) {
                    $q->whereHas('program', fn($pq) => $pq->where('department_id', $this->department_id));
                })
                ->when($this->student_search, function($q) {
                    $q->where(function($sq) {
                        $sq->where('matric_number', 'like', "%{$this->student_search}%")
                           ->orWhere('first_name', 'like', "%{$this->student_search}%")
                           ->orWhere('last_name', 'like', "%{$this->student_search}%");
                    });
                })
                ->orderBy('last_name')
                ->limit(50)
                ->get(),
            'sessions'          => AcademicSession::query()->orderByDesc('name')->get(),
            'semesters'         => $this->session_id ? Semester::where('academic_session_id', $this->session_id)->get() : [],
            'batchSemesters'    => $this->batch_session_id ? Semester::where('academic_session_id', $this->batch_session_id)->get() : collect(),
            'batchDepartments'  => Department::query()
                                    ->when($this->institution_id && $this->institution_id !== 'null', fn ($q) => $q->where('institution_id', $this->institution_id))
                                    ->when($this->department_id, fn ($q) => $q->where('id', $this->department_id))
                                    ->orderBy('name')->get(),
            'batchPrograms'     => Program::query()
                                    ->when($this->institution_id && $this->institution_id !== 'null', fn ($q) => $q->where('institution_id', $this->institution_id))
                                    ->when($this->department_id ?: $this->batch_department_id, fn ($q, $d) => $q->where('department_id', $d))
                                    ->orderBy('name')->get(),
            'batchStudents'     => $this->showBatchModal ? $this->getBatchMatchingStudents() : collect(),
            'carryoverCourses'  => $carryoverCourses,
            'availableCourses'  => $availableCourses,
            'registeredCourses' => $registeredCourses,
            'currentLevel'      => $currentLevel,
            'isClosed'          => ($this->student_id && $this->session_id && $this->session_id !== 'null')
                                    ? RegistrationStatus::where('student_id', $this->student_id)
                                        ->where('academic_session_id', $this->session_id)
                                        ->where('status', 'closed')
                                        ->exists()
                                    : false,
        ]);
    }
}; ?>



<div class="mx-auto max-w-4xl">
    <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Course Registration') }}</flux:heading>
            <flux:subheading>{{ __('Enroll students into courses for a semester') }}</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-action-message on="registration-success">
                <flux:badge color="green">{{ __('Registration successful!') }}</flux:badge>
            </x-action-message>
            @can('registrations.create')
                <flux:button variant="primary" icon="user-group" wire:click="openBatchModal" class="w-full sm:w-auto">
                    {{ __('Batch Register') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <flux:card class="space-y-8">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
            @if (!auth()->user()->institution_id)
            <flux:select wire:model.live="institution_id" :label="__('Institution')" required>
                <flux:select.option value="null">{{ __('Select institution...') }}</flux:select.option>
                @foreach ($institutions as $inst)
                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                @endforeach
            </flux:select>
            @endif

            <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model.live.debounce.300ms="student_search" :label="__('Search Student')" :placeholder="__('Matric number or name...')" icon="magnifying-glass" />

                <flux:select wire:model.live="student_id" :label="__('Select Student')" required :disabled="!$institution_id">
                    <flux:select.option value="null">{{ __('Select student...') }}</flux:select.option>
                    @foreach ($students as $stu)
                    <flux:select.option :value="$stu->id">{{ $stu->full_name }} ({{ $stu->matric_number }})
                    </flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:select wire:model.live="session_id" :label="__('Academic Session')" required>
                <flux:select.option value="null">{{ __('Select session...') }}</flux:select.option>
                @foreach ($sessions as $session)
                <flux:select.option :value="$session->id">{{ $session->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($isClosed)
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3 text-red-700">
            <flux:icon.lock-closed class="size-5" />
            <div class="text-sm font-semibold uppercase tracking-tight">{{ __('Student registration is locked/verified, but you can still make administrative changes.') }}</div>
        </div>
        @endif

        @if ($student_id && $session_id && $session_id !== 'null')
        <div class="space-y-8">
            <!-- Carryover Courses Section -->
            @if ($carryoverCourses->isNotEmpty())
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md" class="text-red-600 dark:text-red-400">
                        {{ __('Mandatory Carryover Courses') }}
                    </flux:heading>
                    <flux:badge color="red" size="sm" inset="top bottom">
                        {{ __('Action Required') }}
                    </flux:badge>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($carryoverCourses as $course)
                    <label wire:key="carryover-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg bg-red-50/30 dark:bg-red-900/10 border-red-200 dark:border-red-900/30">
                        <div
                            class="flex items-center justify-center w-5 h-5 me-3 rounded border border-red-400 bg-red-500 text-white">
                            <flux:icon.check variant="micro" />
                        </div>
                        <input type="checkbox" wire:model="selected_courses" value="{{ $course['id'] }}" class="hidden"
                            checked disabled />
                        <div>
                            <div class="font-mono text-sm font-bold text-red-700 dark:text-red-400 uppercase">{{
                                $course['course_code'] }}</div>
                            <div class="text-xs text-red-600/80 dark:text-red-500/80">{{ $course['title'] }} ({{
                                $course['credit_unit'] }} units)</div>
                            <div class="text-[10px] mt-1 font-bold uppercase text-red-500/70 italic">{{ $course['semester_name'] }} Semester</div>
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Available Courses Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Available Courses') }}</flux:heading>
                    @if ($currentLevel)
                    <flux:badge color="blue" size="sm">
                        {{ __('Level') }} {{ $currentLevel }}
                    </flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($availableCourses as $course)
                    <label wire:key="available-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model.live="selected_courses" :value="$course['id']" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course['course_code'] }}</div>
                            <div class="text-sm">{{ $course['title'] }}</div>
                            <div class="text-xs text-zinc-500">{{ $course['credit_unit'] }} {{ __('Units') }} ({{ $course['semester_name'] }})</div>
                        </div>
                    </label>
                    @empty
                    <div class="col-span-full p-8 text-center border-2 border-dashed rounded-xl text-zinc-500">
                        {{ __('No more recommended courses available to register for this semester.') }}
                    </div>
                    @endforelse
                </div>

                @if (count($availableCourses) > 0)
                <div class="flex flex-col items-end pt-2">
                    <flux:button variant="primary" wire:click="register">
                        {{ __('Register Selected Courses') }}
                    </flux:button>
                    <flux:error name="selected_courses" class="mt-2" />
                </div>
                @endif
            </div>

            <flux:separator />

            <!-- Registered Courses Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="md">{{ __('Currently Registered') }}</flux:heading>
                    @if (count($registeredCourses) > 0)
                    @php 
                        $selectedStudent = $students->firstWhere('id', $this->student_id);
                        $maxUnits = $selectedStudent?->program?->department?->max_session_units ?? 24;
                    @endphp
                    <flux:badge color="green" size="sm">
                        {{ __('Total Units:') }} {{ $registeredCourses->sum('credit_unit') }} / {{ $maxUnits }}
                    </flux:badge>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($registeredCourses as $course)
                    <label wire:key="registered-{{ $course['id'] }}"
                        class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-red-50 dark:hover:bg-red-950/20 transition-colors border-zinc-200 dark:border-zinc-700">
                        <flux:checkbox wire:model.live="courses_to_drop" :value="$course['id']" class="me-3" />
                        <div>
                            <div class="font-medium font-mono text-sm uppercase">{{ $course['course_code'] }}</div>
                            <div class="text-sm">{{ $course['title'] }}</div>
                            <div class="flex gap-2 items-center mt-1">
                                <flux:badge size="sm" color="green">{{ __('Registered') }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $course['credit_unit'] }} {{ __('Units') }} ({{ $course['semester_name'] }})</span>
                            </div>
                        </div>
                    </label>
                    @empty
                    <div class="col-span-full p-8 text-center border-2 border-dashed rounded-xl text-zinc-500">
                        {{ __('No courses registered yet.') }}
                    </div>
                    @endforelse
                </div>

                @if (count($registeredCourses) > 0)
                <div class="flex flex-col items-end pt-2">
                    <flux:button variant="danger" wire:click="drop">
                        {{ __('Drop Selected Courses') }}
                    </flux:button>
                    <flux:error name="courses_to_drop" class="mt-2" />
                </div>
                @endif
            </div>
        </div>
        @endif
    </flux:card>

    {{-- Batch Course Registration Modal --}}
    <flux:modal
        wire:model.self="showBatchModal"
        class="w-full max-w-3xl"
        x-data
        x-on:batch-reg-next-chunk.window="$wire.processBatchChunk()"
    >
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Batch Course Registration') }}</flux:heading>
                <flux:subheading>
                    {{ __('Register level courses and failed carryover courses for a department, program, or selected students.') }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @if (!auth()->user()->institution_id)
                    <div class="sm:col-span-2">
                        <flux:select wire:model.live="institution_id" :label="__('Institution')" :disabled="$batchRunning">
                            <flux:select.option value="">{{ __('All Institutions') }}</flux:select.option>
                            @foreach ($institutions as $inst)
                                <flux:select.option :value="$inst->id">{{ $inst->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif

                <flux:select wire:model.live="batch_session_id" :label="__('Academic Session')" :disabled="$batchRunning">
                    <flux:select.option value="">{{ __('Select session...') }}</flux:select.option>
                    @foreach ($sessions as $s)
                        <flux:select.option :value="$s->id">{{ $s->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="batch_semester_id" :label="__('Semester')" :disabled="!$batch_session_id || $batchRunning">
                    <flux:select.option value="">{{ __('Select semester...') }}</flux:select.option>
                    @foreach ($batchSemesters as $sem)
                        <flux:select.option :value="$sem->id">{{ ucfirst($sem->name) }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if (!$this->department_id)
                    <flux:select wire:model.live="batch_department_id" :label="__('Department')" :disabled="$batchRunning">
                        <flux:select.option value="">{{ __('All Departments') }}</flux:select.option>
                        @foreach ($batchDepartments as $dept)
                            <flux:select.option :value="$dept->id">{{ $dept->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:select wire:model.live="batch_program_id" :label="__('Program')" :disabled="$batchRunning">
                    <flux:select.option value="">{{ __('All Programs') }}</flux:select.option>
                    @foreach ($batchPrograms as $prog)
                        <flux:select.option :value="$prog->id">{{ $prog->acronym ?: $prog->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="batch_level" :label="__('Class Level')" :disabled="$batchRunning">
                    <flux:select.option value="">{{ __('All Levels (Auto-detect per student)') }}</flux:select.option>
                    @foreach ([100, 200, 300, 400, 500] as $lvl)
                        <flux:select.option value="{{ $lvl }}">{{ $lvl }}L</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Registration Options --}}
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:checkbox wire:model="batch_include_level" :disabled="$batchRunning" class="mt-0.5" />
                    <div class="text-xs">
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Register Current-Level Courses') }}</div>
                        <div class="text-zinc-500 dark:text-zinc-400">{{ __('100L gets 100L courses, 200L gets 200L, etc.') }}</div>
                    </div>
                </label>

                <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:checkbox wire:model="batch_include_carryovers" :disabled="$batchRunning" class="mt-0.5" />
                    <div class="text-xs">
                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Include Failed Carryovers') }}</div>
                        <div class="text-zinc-500 dark:text-zinc-400">{{ __('Evaluated using each department\'s grading system') }}</div>
                    </div>
                </label>
            </div>
            <flux:error name="batch_include_level" />

            {{-- Student Selection --}}
            <div class="space-y-3 rounded-xl border border-zinc-200 bg-zinc-50/60 p-3.5 dark:border-zinc-700 dark:bg-zinc-900/40">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Select Students') }}</span>
                        <flux:badge size="sm" color="indigo">
                            {{ count($batch_selected_student_ids) }} / {{ $batchStudents->count() }}
                        </flux:badge>
                    </div>
                    @if ($batchStudents->isNotEmpty())
                        <flux:checkbox wire:model.live="batch_select_all" :label="__('Select all')" :disabled="$batchRunning" />
                    @endif
                </div>

                @if ($batchStudents->isNotEmpty())
                    <flux:input
                        wire:model.live.debounce.200ms="batch_student_search"
                        icon="magnifying-glass"
                        size="sm"
                        :placeholder="__('Filter by name or matric number...')"
                        :disabled="$batchRunning"
                    />

                    @php
                        $batchSessionObj = $batch_session_id ? $sessions->firstWhere('id', (int) $batch_session_id) : null;
                        $filteredBatchStudents = $batchStudents->filter(function ($s) {
                            if (trim($this->batch_student_search) === '') {
                                return true;
                            }
                            $term = strtolower(trim($this->batch_student_search));

                            return str_contains(strtolower($s->full_name), $term)
                                || str_contains(strtolower((string) $s->matric_number), $term);
                        });
                    @endphp

                    <div class="max-h-48 overflow-y-auto custom-scrollbar divide-y divide-zinc-200/70 rounded-lg border border-zinc-200 bg-white dark:divide-zinc-700/70 dark:border-zinc-700 dark:bg-zinc-900">
                        @forelse ($filteredBatchStudents as $stu)
                            <label class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <input
                                        type="checkbox"
                                        wire:model.live="batch_selected_student_ids"
                                        value="{{ $stu->id }}"
                                        @disabled($batchRunning)
                                        class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800"
                                    />
                                    <div class="min-w-0">
                                        <div class="truncate text-xs font-semibold text-zinc-900 dark:text-zinc-100 sm:text-sm">{{ $stu->full_name }}</div>
                                        <div class="font-mono text-[11px] text-zinc-500">{{ $stu->matric_number }}</div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1.5">
                                    @if ($stu->program?->acronym)
                                        <flux:badge size="xs" color="zinc">{{ $stu->program->acronym }}</flux:badge>
                                    @endif
                                    @if ($batchSessionObj)
                                        <flux:badge size="xs" color="sky">{{ $stu->currentLevel($batchSessionObj) }}L</flux:badge>
                                    @endif
                                </div>
                            </label>
                        @empty
                            <div class="px-3 py-4 text-center text-xs text-zinc-500">
                                {{ __('No students match ":search".', ['search' => $batch_student_search]) }}
                            </div>
                        @endforelse
                    </div>
                @else
                    <p class="py-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('No active students found for the selected filters.') }}
                    </p>
                @endif
                <flux:error name="batch_selected_student_ids" />
            </div>

            {{-- Live Progress Indicator & Metrics --}}
            @if ($batchRunning || $batchCompleted)
                @php
                    $pct = $batchTotalStudents > 0 ? min(100, round(($batchProcessedCount / $batchTotalStudents) * 100)) : 0;
                @endphp
                <div class="space-y-3 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-800/60 dark:bg-indigo-950/20">
                    <div class="flex items-center justify-between text-xs font-semibold">
                        <span class="text-indigo-900 dark:text-indigo-200">
                            {{ $batchRunning ? __('Registering courses...') : __('Batch Registration Complete') }}
                        </span>
                        <span class="text-indigo-700 dark:text-indigo-300">
                            {{ $batchProcessedCount }} / {{ $batchTotalStudents }} ({{ $pct }}%)
                        </span>
                    </div>

                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-950">
                        <div class="h-full rounded-full bg-indigo-600 transition-all duration-300" style="width: {{ $pct }}%"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                        <div class="rounded-lg bg-white p-2.5 text-center shadow-2xs dark:bg-zinc-900">
                            <div class="text-lg font-bold text-zinc-900 dark:text-white">{{ $batchProcessedCount }}</div>
                            <div class="text-[11px] text-zinc-500">{{ __('Students') }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 text-center shadow-2xs dark:bg-zinc-900">
                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $batchLevelCreated }}</div>
                            <div class="text-[11px] text-zinc-500">{{ __('Level Courses') }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 text-center shadow-2xs dark:bg-zinc-900">
                            <div class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ $batchCarryoverCreated }}</div>
                            <div class="text-[11px] text-zinc-500">{{ __('Carryovers') }}</div>
                        </div>
                        <div class="rounded-lg bg-white p-2.5 text-center shadow-2xs dark:bg-zinc-900">
                            <div class="text-lg font-bold text-zinc-500">{{ $batchSkipped }}</div>
                            <div class="text-[11px] text-zinc-500">{{ __('Skipped') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex flex-col-reverse gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" class="w-full sm:w-auto" :disabled="$batchRunning">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                <flux:button
                    variant="primary"
                    icon="check"
                    wire:click="startBatchRegistration"
                    wire:loading.attr="disabled"
                    :disabled="$batchRunning || empty($batch_selected_student_ids)"
                    class="w-full sm:w-auto"
                >
                    {{ $batchRunning ? __('Processing...') : __('Register Selected Students') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>